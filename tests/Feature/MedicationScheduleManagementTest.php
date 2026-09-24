<?php

namespace Tests\Feature;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationLog;
use App\Models\MedicationSchedule;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicationScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Family $family;
    protected FamilyMember $grandpa;
    protected FamilyMember $daughter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->family = Family::where('code', 'FAM-VN2026')->first();
        $this->user = User::where('email', 'an.nguyen@gmail.com')->first();
        $this->grandpa = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Văn Thịnh')->first();
        $this->daughter = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Mai Anh')->first();
    }

    public function test_user_can_view_schedules_timeline_with_kpis(): void
    {
        $response = $this->actingAs($this->user)->get(route('schedules.index'));

        $response->assertStatus(200);
        $response->assertSee('Lịch uống thuốc &amp; Nhật ký tuân thủ', false);
        $response->assertSee('Cần uống trong ngày');
        $response->assertSee('Đã uống đúng giờ');
        $response->assertSee('Tỷ lệ tuân thủ');
        $response->assertSee('Cữ Sáng');
        $response->assertSee('Cữ Tối');
    }

    public function test_user_can_filter_schedules_by_member_and_time_of_day(): void
    {
        $response = $this->actingAs($this->user)->get(route('schedules.index', [
            'member_id' => $this->grandpa->id,
            'time_of_day' => 'sang',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Nguyễn Văn Thịnh');
        $response->assertSee('Cữ Sáng');
    }

    public function test_user_can_view_all_schedules_tab(): void
    {
        $response = $this->actingAs($this->user)->get(route('schedules.index', ['view' => 'all']));

        $response->assertStatus(200);
        $response->assertSee('DANH SÁCH LỊCH UỐNG THUỐC ĐỊNH KỲ');
        $response->assertSee('Amlodipine 5mg');
    }

    public function test_user_can_view_logs_history_tab(): void
    {
        $response = $this->actingAs($this->user)->get(route('schedules.index', ['view' => 'logs']));

        $response->assertStatus(200);
        $response->assertSee('LỊCH SỬ NHẬT KÝ UỐNG THUỐC');
    }

    public function test_user_can_record_taken_log_and_auto_deduct_cabinet_stock(): void
    {
        $category = MedicineCategory::first();
        $medicine = Medicine::firstOrCreate(
            ['name' => 'Amlodipine 5mg'],
            [
                'category_id' => $category->id,
                'code' => 'AML5',
                'active_ingredient' => 'Amlodipine',
                'concentration' => '5mg',
                'dosage_form' => 'Viên',
                'default_unit' => 'viên',
            ]
        );

        $cabinetItem = CabinetItem::firstOrCreate(
            [
                'family_id' => $this->family->id,
                'medicine_id' => $medicine->id,
            ],
            [
                'custom_name' => 'Amlodipine 5mg',
                'quantity' => 20,
                'unit' => 'viên',
                'expiry_date' => Carbon::today()->addYear(),
                'status' => 'con_han',
            ]
        );
        $cabinetItem->update(['quantity' => 20]);

        $schedule = MedicationSchedule::where('family_id', $this->family->id)
            ->where('medicine_name', 'like', '%Amlodipine%')
            ->first();

        $this->assertNotNull($schedule);

        $payload = [
            'schedule_id' => $schedule->id,
            'scheduled_time' => Carbon::today()->setTime(7, 30, 0)->toDateTimeString(),
            'status' => 'da_uong',
            'note' => 'Đã uống cùng nước ấm',
            'deduct_cabinet' => '1',
        ];

        $response = $this->actingAs($this->user)->post(route('schedules.log'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check MedicationLog was created
        $log = MedicationLog::where('schedule_id', $schedule->id)
            ->whereDate('scheduled_time', Carbon::today())
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('da_uong', $log->status);
        $this->assertEquals('Đã uống cùng nước ấm', $log->note);
        $this->assertNotNull($log->actual_taken_time);

        // Check stock was decremented from 20 to 19
        $cabinetItem->refresh();
        $this->assertEquals(19, $cabinetItem->quantity);
    }

    public function test_user_can_record_skipped_or_missed_log(): void
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Kẹo ngậm ho thảo dược',
            'dosage' => '1 viên',
            'unit' => 'viên',
            'time_of_day' => 'trua',
            'scheduled_time' => '12:00:00',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today(),
            'is_active' => true,
        ]);

        $payload = [
            'schedule_id' => $schedule->id,
            'scheduled_time' => Carbon::today()->setTime(12, 0, 0)->toDateTimeString(),
            'status' => 'quen_uong',
            'note' => 'Đi công tác quên mang theo thuốc',
        ];

        $response = $this->actingAs($this->user)->post(route('schedules.log'), $payload);

        $response->assertRedirect();

        $log = MedicationLog::where('schedule_id', $schedule->id)
            ->whereDate('scheduled_time', Carbon::today())
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('quen_uong', $log->status);
        $this->assertEquals('Đi công tác quên mang theo thuốc', $log->note);
        $this->assertNull($log->actual_taken_time);
    }

    public function test_user_can_create_custom_medication_schedule(): void
    {
        $payload = [
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Kẹo dẻo Vitamin C Gummy',
            'dosage' => '2 viên',
            'unit' => 'viên',
            'time_of_day' => 'trua',
            'scheduled_time' => '12:30',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addMonths(3)->toDateString(),
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->user)->post(route('schedules.store'), $payload);

        $response->assertRedirect(route('schedules.index'));
        $this->assertDatabaseHas('medication_schedules', [
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Kẹo dẻo Vitamin C Gummy',
            'scheduled_time' => '12:30:00',
        ]);
    }

    public function test_user_can_update_medication_schedule(): void
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Men vi sinh BioGaia',
            'dosage' => '5 giọt',
            'unit' => 'giọt',
            'time_of_day' => 'sang',
            'scheduled_time' => '08:00:00',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today(),
            'is_active' => true,
        ]);

        $payload = [
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Men vi sinh BioGaia Protectis',
            'dosage' => '6 giọt',
            'unit' => 'giọt',
            'time_of_day' => 'trua',
            'scheduled_time' => '12:00',
            'meal_timing' => 'trong_khi_an',
            'start_date' => Carbon::today()->toDateString(),
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->user)->put(route('schedules.update', $schedule), $payload);

        $response->assertRedirect(route('schedules.index'));
        $schedule->refresh();
        $this->assertEquals('Men vi sinh BioGaia Protectis', $schedule->medicine_name);
        $this->assertEquals('6 giọt', $schedule->dosage);
        $this->assertEquals('trua', $schedule->time_of_day);
    }

    public function test_user_can_toggle_schedule_active_status(): void
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Dầu cá Omega 3',
            'dosage' => '1 viên',
            'time_of_day' => 'sang',
            'scheduled_time' => '07:30:00',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today(),
            'is_active' => true,
        ]);

        // Toggle to false
        $response = $this->actingAs($this->user)->patch(route('schedules.toggle', $schedule));
        $response->assertRedirect();
        $schedule->refresh();
        $this->assertFalse($schedule->is_active);

        // Toggle back to true
        $this->actingAs($this->user)->patch(route('schedules.toggle', $schedule));
        $schedule->refresh();
        $this->assertTrue($schedule->is_active);
    }

    public function test_user_can_delete_medication_schedule(): void
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'medicine_name' => 'Lịch cần xóa',
            'dosage' => '1 viên',
            'time_of_day' => 'sang',
            'scheduled_time' => '07:30:00',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->delete(route('schedules.destroy', $schedule));

        $response->assertRedirect(route('schedules.index'));
        $this->assertDatabaseMissing('medication_schedules', ['id' => $schedule->id]);
    }

    public function test_user_cannot_modify_other_family_schedule(): void
    {
        $otherFamily = Family::create([
            'name' => 'Gia đình khác',
            'code' => 'FAM-SCH-' . uniqid(),
        ]);

        $otherMember = FamilyMember::create([
            'family_id' => $otherFamily->id,
            'name' => 'Người lạ',
            'relationship' => 'ban_than',
        ]);

        $otherSchedule = MedicationSchedule::create([
            'family_id' => $otherFamily->id,
            'family_member_id' => $otherMember->id,
            'medicine_name' => 'Thuốc nhà người khác',
            'dosage' => '1 viên',
            'time_of_day' => 'sang',
            'scheduled_time' => '07:30:00',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('schedules.edit', $otherSchedule));
        $response->assertStatus(403);
    }
}
