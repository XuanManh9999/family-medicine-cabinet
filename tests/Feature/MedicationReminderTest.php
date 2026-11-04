<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationLog;
use App\Models\MedicationReminder;
use App\Models\MedicationSchedule;
use App\Models\SmartAlert;
use App\Models\User;
use App\Services\MedicationReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MedicationReminderTest extends TestCase
{
    use RefreshDatabase;

    protected Family $family;
    protected User $familyAdmin;
    protected User $memberUser;
    protected FamilyMember $grandpa;
    protected FamilyMember $hoa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->family = Family::where('code', 'FAM-VN2026')->firstOrFail();
        $this->familyAdmin = User::where('email', 'an.nguyen@gmail.com')->firstOrFail();
        $this->memberUser = User::where('email', 'hoa.tran@gmail.com')->firstOrFail();
        $this->grandpa = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Văn Thịnh')->firstOrFail();
        $this->hoa = $this->memberUser->familyMember;

        // Chỉ giữ lịch tạo trong test để kết quả rõ ràng
        MedicationSchedule::query()->update(['is_active' => false]);
        SmartAlert::query()->delete();
    }

    private function scheduleFor(FamilyMember $member, string $time): MedicationSchedule
    {
        return MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $member->id,
            'medicine_name' => 'Amlodipine 5mg',
            'dosage' => '1 viên',
            'unit' => 'viên',
            'time_of_day' => 'sang',
            'scheduled_time' => $time,
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today()->subDays(3),
            'is_active' => true,
        ]);
    }

    public function test_due_reminder_is_sent_once_to_member_account(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::today()->setTime(7, 35));
        $schedule = $this->scheduleFor($this->hoa, '07:30:00');

        $stats = app(MedicationReminderService::class)->run();
        $this->assertSame(1, $stats['due_sent']);
        $this->assertSame(1, $stats['emails']);

        $alert = SmartAlert::where('type', 'schedule_reminder')->where('medication_schedule_id', $schedule->id)->firstOrFail();
        $this->assertStringContainsString('Trần Thị Hoa', $alert->title);
        $this->assertSame('hoa.tran@gmail.com', MedicationReminder::firstOrFail()->emailed_to);

        // Chạy lại không nhắc trùng
        $again = app(MedicationReminderService::class)->run();
        $this->assertSame(0, $again['due_sent']);
        $this->assertSame(1, SmartAlert::where('type', 'schedule_reminder')->count());
    }

    public function test_member_without_account_is_reminded_via_family_admin_and_missed_dose_escalates(): void
    {
        $this->travelTo(Carbon::today()->setTime(7, 31));
        $schedule = $this->scheduleFor($this->grandpa, '07:30:00');

        app(MedicationReminderService::class)->run();
        $this->assertSame('an.nguyen@gmail.com', MedicationReminder::where('stage', 'due')->value('emailed_to'));

        // 75 phút sau vẫn chưa điểm danh → cảnh báo quá giờ cho chủ hộ, gỡ thông báo "đến giờ"
        $this->travelTo(Carbon::today()->setTime(8, 45));
        $stats = app(MedicationReminderService::class)->run();
        $this->assertSame(1, $stats['missed_sent']);
        $this->assertDatabaseHas('smart_alerts', ['type' => 'missed_dose', 'medication_schedule_id' => $schedule->id, 'level' => 'warning']);
        $this->assertDatabaseMissing('smart_alerts', ['type' => 'schedule_reminder', 'medication_schedule_id' => $schedule->id]);
    }

    public function test_no_reminder_when_dose_already_logged_or_not_due_yet(): void
    {
        $this->travelTo(Carbon::today()->setTime(7, 40));
        $taken = $this->scheduleFor($this->hoa, '07:30:00');
        $future = $this->scheduleFor($this->hoa, '20:00:00');

        MedicationLog::create([
            'schedule_id' => $taken->id,
            'family_member_id' => $this->hoa->id,
            'scheduled_time' => Carbon::today()->setTime(7, 30),
            'actual_taken_time' => Carbon::today()->setTime(7, 32),
            'status' => 'da_uong',
        ]);

        $stats = app(MedicationReminderService::class)->run();
        $this->assertSame(0, $stats['due_sent'] + $stats['missed_sent']);
        $this->assertDatabaseMissing('smart_alerts', ['medication_schedule_id' => $future->id]);
    }

    public function test_recording_log_clears_reminder_alerts_for_that_dose(): void
    {
        $this->travelTo(Carbon::today()->setTime(7, 35));
        $schedule = $this->scheduleFor($this->hoa, '07:30:00');
        app(MedicationReminderService::class)->run();
        $this->assertDatabaseHas('smart_alerts', ['type' => 'schedule_reminder', 'medication_schedule_id' => $schedule->id]);

        $this->actingAs($this->memberUser)->post(route('schedules.log'), [
            'schedule_id' => $schedule->id,
            'scheduled_time' => Carbon::today()->setTime(7, 30)->toDateTimeString(),
            'status' => 'da_uong',
            'deduct_cabinet' => 0,
        ])->assertRedirect();

        $this->assertDatabaseMissing('smart_alerts', ['type' => 'schedule_reminder', 'medication_schedule_id' => $schedule->id]);
    }

    public function test_command_runs_and_records_heartbeat_for_admin_dashboard(): void
    {
        $this->artisan('medication:send-reminders')->assertSuccessful();

        $admin = User::where('email', 'admin@medcabinet.vn')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Bộ lập lịch đang hoạt động');
    }

    public function test_admin_dashboard_warns_when_scheduler_never_ran(): void
    {
        $admin = User::where('email', 'admin@medcabinet.vn')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Bộ lập lịch chưa chạy')
            ->assertSee('register-scheduler-task.bat');
    }
}
