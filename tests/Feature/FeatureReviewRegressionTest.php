<?php

namespace Tests\Feature;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationLog;
use App\Models\MedicationSchedule;
use App\Models\MedicineExpense;
use App\Models\PrescriptionItem;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\MedicationReminderService;
use App\Services\MedicationSupplyService;
use App\Support\DateRangeFilter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Hồi quy các lỗi tìm được khi rà soát toàn bộ tính năng (11/2026)
 */
class FeatureReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected Family $family;
    protected User $familyAdmin;
    protected User $member;
    protected User $doctor;
    protected FamilyMember $grandpa;
    protected TreatmentCourse $course;
    protected PrescriptionItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->family = Family::where('code', 'FAM-VN2026')->firstOrFail();
        $this->familyAdmin = User::where('email', 'an.nguyen@gmail.com')->firstOrFail();
        $this->member = User::where('email', 'hoa.tran@gmail.com')->firstOrFail();
        $this->doctor = User::where('email', 'bs.minh@medcabinet.vn')->firstOrFail();
        $this->grandpa = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Văn Thịnh')->firstOrFail();

        $this->course = TreatmentCourse::create([
            'family_id' => $this->family->id, 'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn Bisoprolol', 'start_date' => Carbon::today()->subDays(5),
            'end_date' => Carbon::today()->addDays(20), 'status' => 'dang_dieu_tri',
        ]);
        $this->item = PrescriptionItem::create([
            'treatment_course_id' => $this->course->id, 'medicine_name' => 'Bisoprolol 2.5mg',
            'dosage' => '1 viên', 'frequency' => 'Sáng', 'total_prescribed_quantity' => 30, 'unit' => 'viên',
        ]);
    }

    private function purchase(array $overrides = [])
    {
        return $this->actingAs($this->familyAdmin)->post(route('cabinet.purchases.prescription.store', $this->item), array_merge([
            'quantity' => 30, 'unit' => 'viên', 'expiry_date' => now()->addYear()->toDateString(),
            'price_per_unit' => 1000, 'record_expense' => 1,
        ], $overrides));
    }

    public function test_prescription_purchase_cannot_be_recorded_twice(): void
    {
        $this->purchase()->assertSessionHas('success');
        $this->purchase()->assertSessionHas('warning');

        $this->assertSame(1, CabinetItem::withAnyApproval()->where('prescription_item_id', $this->item->id)->count());
        $this->assertSame(1, MedicineExpense::where('title', 'like', 'Mua thuốc:%Bisoprolol%')->count());
    }

    public function test_stopped_medicine_is_not_listed_or_purchasable(): void
    {
        $this->item->update(['stopped_at' => today()]);

        $toBuy = app(MedicationSupplyService::class)->purchaseList($this->family)->pluck('item.id');
        $this->assertNotContains($this->item->id, $toBuy);

        $this->actingAs($this->familyAdmin)->get(route('cabinet.purchases.prescription.create', $this->item))
            ->assertRedirect(route('cabinet.supply'))->assertSessionHas('warning');
        $this->purchase()->assertSessionHas('warning');
        $this->assertSame(0, CabinetItem::withAnyApproval()->where('prescription_item_id', $this->item->id)->count());
    }

    public function test_huge_purchase_total_is_rejected_instead_of_database_error(): void
    {
        $this->purchase(['quantity' => 100, 'price_per_unit' => 150000000])->assertSessionHasErrors('price_per_unit');
    }

    public function test_array_query_parameters_do_not_crash_pages(): void
    {
        $this->actingAs($this->familyAdmin);
        foreach (['/cabinet?q[]=x&location[]=y', '/cabinet/export-excel?location[]=x', '/members?q[]=x',
                  '/expenses?q[]=a&period[]=x', '/expenses/export-csv?q[]=a', '/medicines?q[]=a',
                  '/treatment-courses?q[]=a', '/alerts?q[]=a'] as $url) {
            $this->assertLessThan(500, $this->get($url)->getStatusCode(), $url);
        }
    }

    public function test_month_filter_does_not_roll_into_next_month_at_month_end(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 31, 9));
        $filter = DateRangeFilter::fromRequest(Request::create('/', 'GET', ['period' => 'month', 'month' => '2026-09']));

        $this->assertSame('2026-09-01 00:00:00', $filter->start->toDateTimeString());
        $this->assertSame('2026-09-30 23:59:59', $filter->end->toDateTimeString());
    }

    public function test_no_missed_dose_alert_for_slots_before_schedule_was_created(): void
    {
        MedicationSchedule::query()->update(['is_active' => false]);
        $this->travelTo(Carbon::today()->setTime(13, 30));
        foreach (['sang' => '07:30:00', 'trua' => '12:00:00'] as $slot => $time) {
            MedicationSchedule::create([
                'family_id' => $this->family->id, 'family_member_id' => $this->grandpa->id,
                'medicine_name' => 'Amlodipine 5mg', 'dosage' => '1 viên', 'unit' => 'viên',
                'time_of_day' => $slot, 'scheduled_time' => $time, 'meal_timing' => 'sau_an',
                'start_date' => Carbon::today(), 'is_active' => true,
            ]);
        }

        $stats = app(MedicationReminderService::class)->run();
        $this->assertSame(0, $stats['missed_sent']);
        $this->assertSame(0, $stats['due_sent']);
    }

    public function test_unticking_reminder_switch_disables_schedule(): void
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id, 'family_member_id' => $this->grandpa->id,
            'medicine_name' => 'Vitamin C', 'dosage' => '1 viên', 'unit' => 'viên', 'time_of_day' => 'sang',
            'scheduled_time' => '08:00:00', 'meal_timing' => 'sau_an', 'start_date' => Carbon::today(), 'is_active' => true,
        ]);

        $this->actingAs($this->doctor)->put(route('schedules.update', $schedule), [
            'family_member_id' => $this->grandpa->id, 'medicine_name' => 'Vitamin C', 'dosage' => '1 viên',
            'time_of_day' => 'sang', 'scheduled_time' => '08:30', 'meal_timing' => 'sau_an',
            'start_date' => Carbon::today()->toDateString(), 'is_active' => '0',
        ])->assertRedirect();

        $this->assertFalse((bool) $schedule->fresh()->is_active);
    }

    public function test_editing_course_dates_keeps_logged_history_visible(): void
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id, 'family_member_id' => $this->grandpa->id,
            'prescription_item_id' => $this->item->id, 'medicine_name' => 'Bisoprolol 2.5mg', 'dosage' => '1 viên',
            'unit' => 'viên', 'time_of_day' => 'sang', 'scheduled_time' => '07:30:00', 'meal_timing' => 'sau_an',
            'start_date' => $this->course->start_date, 'end_date' => $this->course->end_date, 'is_active' => true,
        ]);
        $loggedDay = Carbon::today()->subDays(3);
        MedicationLog::create([
            'schedule_id' => $schedule->id, 'family_member_id' => $this->grandpa->id,
            'scheduled_time' => $loggedDay->copy()->setTime(7, 30), 'status' => 'da_uong',
        ]);

        $this->actingAs($this->doctor)->put(route('treatment-courses.update', $this->course), [
            'family_member_id' => $this->grandpa->id, 'title' => 'Đơn Bisoprolol',
            'start_date' => Carbon::today()->subDay()->toDateString(),
            'end_date' => $this->course->end_date->toDateString(), 'status' => 'dang_dieu_tri',
            'items' => [[
                'id' => $this->item->id, 'medicine_name' => 'Bisoprolol 2.5mg', 'dosage' => '1 viên',
                'frequency' => 'Sáng', 'unit' => 'viên', 'total_prescribed_quantity' => 30, 'times' => ['sang'],
            ]],
        ])->assertRedirect();

        $this->assertTrue(MedicationSchedule::visibleOnDate($loggedDay)->whereKey($schedule->id)->exists());
    }

    public function test_resending_otp_does_not_reset_wrong_guess_limit(): void
    {
        $this->post('/forgot-password', ['email' => 'an.nguyen@gmail.com']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/verify-otp', ['otp' => '000000']);
        }

        // Xin mã mới: vẫn bị khóa, mã mới bị hủy ngay khi đoán tiếp
        $this->post('/forgot-password', ['email' => 'an.nguyen@gmail.com']);
        $this->post('/verify-otp', ['otp' => '000000'])->assertSessionHasErrors('otp');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'an.nguyen@gmail.com']);
    }

    public function test_founder_member_record_cannot_be_unlinked_then_deleted(): void
    {
        $founder = FamilyMember::where('family_id', $this->family->id)->where('user_id', $this->family->created_by)->firstOrFail();

        $this->actingAs($this->familyAdmin)->put(route('members.update', $founder), [
            'name' => $founder->name, 'relationship' => $founder->relationship, 'gender' => $founder->gender,
            'dob' => $founder->dob?->format('Y-m-d'), 'user_id' => '',
        ]);
        $this->assertSame($this->family->created_by, $founder->fresh()->user_id);

        $this->actingAs($this->familyAdmin)->delete(route('members.destroy', $founder));
        $this->assertNotNull($founder->fresh());
    }

    public function test_removed_account_can_rejoin_family_with_invite_code_and_keeps_old_profile(): void
    {
        $profile = $this->member->familyMember;
        $this->actingAs($this->familyAdmin)->delete(route('family.users.remove', $this->member))->assertRedirect();
        $this->member->refresh();
        $this->assertNull($this->member->family_id);

        // Mã sai → báo lỗi, vẫn chưa thuộc gia đình
        $this->actingAs($this->member)->post(route('family.join'), ['family_code' => 'FAM-KHONGCO'])
            ->assertSessionHasErrors('family_code');
        $this->assertNull($this->member->fresh()->family_id);

        // Mã đúng (không phân biệt hoa thường) → vào lại với vai trò Thành viên, nhận lại hồ sơ sức khỏe cũ
        $this->actingAs($this->member)->post(route('family.join'), ['family_code' => ' fam-vn2026 '])
            ->assertRedirect(route('dashboard'));

        $this->member->refresh();
        $this->assertSame($this->family->id, $this->member->family_id);
        $this->assertSame('member', $this->member->role);
        $this->assertSame($this->member->id, $profile->fresh()->user_id);
        $this->assertSame(1, FamilyMember::where('user_id', $this->member->id)->count());
        $this->assertDatabaseHas('smart_alerts', ['family_id' => $this->family->id, 'type' => 'family_join']);

        // Đã có gia đình thì không tham gia thêm
        $this->actingAs($this->member)->post(route('family.join'), ['family_code' => 'FAM-VN2026'])->assertSessionHas('warning');
    }

    public function test_family_less_profile_shows_join_form_and_new_family_creates_profile(): void
    {
        $loner = User::factory()->create(['family_id' => null, 'role' => 'member']);

        $this->actingAs($loner)->get(route('profile'))->assertOk()->assertSee(route('family.join'), false);

        $this->actingAs($loner)->post(route('family.join'), ['family_code' => 'FAM-VN2026'])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('family_members', ['family_id' => $this->family->id, 'user_id' => $loner->id]);
        $this->actingAs($loner->fresh())->get(route('dashboard'))->assertOk();
    }

    public function test_malformed_ai_prescription_scan_is_normalized(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        $payload = json_encode([
            'medicines' => [['name' => ['Panadol', 'Hapacol'], 'active_ingredient' => null], null, 'x'],
            'precautions' => 'Uống sau ăn',
        ]);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => $payload]]]]]])]);

        $result = (new GeminiService())->analyzePrescriptionImage(base64_encode('fake-image-bytes'), 'image/jpeg');

        $this->assertCount(1, $result['medicines']);
        $this->assertSame('Panadol, Hapacol', $result['medicines'][0]['name']);
        $this->assertSame(['Uống sau ăn'], $result['precautions']);
        // Bộ luật an toàn chạy được trên dữ liệu đã chuẩn hóa (trước đây gây TypeError)
        $this->assertIsArray((new GeminiService())->checkLocalRules($result['medicines']));
    }
}
