<?php

namespace Tests\Feature;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationSchedule;
use App\Models\MedicineExpense;
use App\Models\PrescriptionItem;
use App\Models\RefillRequest;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Services\MedicationSupplyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hồi quy các lỗi tìm được khi rà soát luồng mua theo đơn / yêu cầu bổ sung / Bác sĩ xác nhận lô thuốc
 */
class SupplyRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected Family $family;
    protected User $familyAdmin;
    protected User $member;
    protected User $doctor;
    protected FamilyMember $grandpa;
    protected TreatmentCourse $course;
    protected PrescriptionItem $item;
    protected MedicationSchedule $morning;

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
            'title' => 'Đơn Bisoprolol', 'start_date' => Carbon::today()->subDay(),
            'end_date' => Carbon::today()->addDays(20), 'status' => 'dang_dieu_tri',
        ]);
        $this->item = PrescriptionItem::create([
            'treatment_course_id' => $this->course->id, 'medicine_name' => 'Bisoprolol 2.5mg',
            'dosage' => '1 viên', 'frequency' => 'Sáng', 'total_prescribed_quantity' => 10, 'unit' => 'viên',
        ]);
        $this->morning = MedicationSchedule::create([
            'family_id' => $this->family->id, 'family_member_id' => $this->grandpa->id,
            'prescription_item_id' => $this->item->id, 'medicine_name' => 'Bisoprolol 2.5mg',
            'dosage' => '1 viên', 'unit' => 'viên', 'time_of_day' => 'sang', 'scheduled_time' => '07:30:00',
            'meal_timing' => 'sau_an', 'start_date' => $this->course->start_date, 'end_date' => $this->course->end_date, 'is_active' => true,
        ]);
    }

    private function lot(array $attrs): CabinetItem
    {
        return CabinetItem::create(array_merge([
            'family_id' => $this->family->id, 'expiry_date' => now()->addYear()->toDateString(),
            'quantity' => 3, 'purchased_quantity' => 3, 'unit' => 'viên', 'status' => 'con_han',
        ], $attrs));
    }

    private function editCourseTitle(): void
    {
        $this->actingAs($this->doctor)->put(route('treatment-courses.update', $this->course), [
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn Bisoprolol (sửa tiêu đề)',
            'start_date' => $this->course->start_date->toDateString(),
            'end_date' => $this->course->end_date->toDateString(),
            'status' => 'dang_dieu_tri',
            'items' => [[
                'id' => $this->item->id, 'medicine_name' => 'Bisoprolol 2.5mg', 'dosage' => '1 viên',
                'frequency' => 'Sáng', 'unit' => 'viên', 'total_prescribed_quantity' => 10, 'times' => ['sang'],
            ]],
        ])->assertRedirect();
    }

    public function test_refill_for_finished_course_becomes_buyable_out_of_prescription_refill(): void
    {
        $lot = $this->lot(['custom_name' => 'Bisoprolol 2.5mg', 'prescription_item_id' => $this->item->id, 'quantity' => 2, 'purchased_quantity' => 10]);
        $this->actingAs($this->doctor)->patch(route('treatment-courses.toggle-status', $this->course))->assertRedirect();
        $this->assertSame('da_hoan_thanh', $this->course->fresh()->status);

        $this->actingAs($this->member)->post(route('cabinet.refills.store'), ['source' => 'cabinet', 'cabinet_item_id' => $lot->id])->assertRedirect();
        $refill = RefillRequest::latest('id')->firstOrFail();
        // Không gắn vào đơn đã kết thúc
        $this->assertNull($refill->prescription_item_id);

        $this->actingAs($this->doctor)->post(route('cabinet.refills.approve', $refill), ['approved_quantity' => 20])->assertRedirect();
        $this->assertEquals(10, (float) $this->item->fresh()->total_prescribed_quantity);

        // Gia đình mua được qua mục "được mua thêm", không bị kẹt
        $this->assertSame(1, RefillRequest::where('family_id', $this->family->id)->awaitingPurchase()->count());
        $this->actingAs($this->member)->get(route('cabinet.purchases.refill.create', $refill))->assertOk();
    }

    public function test_approved_out_of_prescription_refill_can_be_cancelled_by_family(): void
    {
        $lot = $this->lot(['custom_name' => 'Băng gạc', 'unit' => 'hộp', 'quantity' => 1]);
        $this->actingAs($this->member)->post(route('cabinet.refills.store'), ['source' => 'cabinet', 'cabinet_item_id' => $lot->id]);
        $refill = RefillRequest::firstOrFail();
        $this->actingAs($this->doctor)->post(route('cabinet.refills.approve', $refill), ['approved_quantity' => 2]);

        // Chủ hộ (không phải người gửi) hủy được vì gia đình không mua nữa
        $this->actingAs($this->familyAdmin)->post(route('cabinet.refills.cancel', $refill))->assertRedirect();
        $this->assertSame('cancelled', $refill->fresh()->status);

        // Gửi lại yêu cầu mới được
        $this->actingAs($this->member)->post(route('cabinet.refills.store'), ['source' => 'cabinet', 'cabinet_item_id' => $lot->id])
            ->assertSessionHas('success');
        $this->assertSame(2, RefillRequest::count());
    }

    public function test_link_existing_only_accepts_matching_usable_lot(): void
    {
        $vitc = $this->lot(['custom_name' => 'Vitamin C 500mg', 'quantity' => 50, 'purchased_quantity' => 50]);
        $this->actingAs($this->member)->post(route('cabinet.purchases.prescription.link', $this->item), ['cabinet_item_id' => $vitc->id])
            ->assertStatus(422);
        $this->assertNull($vitc->fresh()->prescription_item_id);

        $expired = $this->lot(['custom_name' => 'Bisoprolol 2.5mg', 'quantity' => 0, 'purchased_quantity' => 10, 'expiry_date' => now()->subMonth()->toDateString()]);
        $this->actingAs($this->member)->post(route('cabinet.purchases.prescription.link', $this->item), ['cabinet_item_id' => $expired->id])
            ->assertStatus(422);
        $this->assertTrue($this->item->fresh()->needs_purchase);

        // Lô đúng thuốc, còn hạn, còn hàng thì gắn được và hoàn tất yêu cầu kê tiếp đang mở
        $refill = RefillRequest::create([
            'family_id' => $this->family->id, 'requested_by' => $this->member->id, 'prescription_item_id' => $this->item->id,
            'medicine_name' => 'Bisoprolol 2.5mg', 'unit' => 'viên', 'source' => 'schedule', 'status' => 'approved', 'approved_quantity' => 10,
        ]);
        $good = $this->lot(['custom_name' => 'Bisoprolol 2.5mg', 'quantity' => 10, 'purchased_quantity' => null]);
        $this->actingAs($this->member)->post(route('cabinet.purchases.prescription.link', $this->item), ['cabinet_item_id' => $good->id])
            ->assertRedirect();
        $this->assertSame($this->item->id, $good->fresh()->prescription_item_id);
        $this->assertSame('fulfilled', $refill->fresh()->status);
    }

    public function test_doctor_stop_survives_later_course_edit(): void
    {
        $refill = RefillRequest::create([
            'family_id' => $this->family->id, 'requested_by' => $this->member->id,
            'prescription_item_id' => $this->item->id, 'medicine_name' => 'Bisoprolol 2.5mg', 'unit' => 'viên',
            'source' => 'schedule', 'status' => 'pending',
        ]);
        $this->actingAs($this->doctor)->post(route('cabinet.refills.stop', $refill), ['review_note' => 'Đã ổn'])->assertRedirect();
        $this->assertSame(today()->toDateString(), $this->item->fresh()->stopped_at->toDateString());

        $this->editCourseTitle();
        $this->assertSame(today()->toDateString(), $this->morning->fresh()->end_date->toDateString());

        // Kê tiếp lại sau này → thuốc được dùng lại
        $again = RefillRequest::create([
            'family_id' => $this->family->id, 'requested_by' => $this->member->id,
            'prescription_item_id' => $this->item->id, 'medicine_name' => 'Bisoprolol 2.5mg', 'unit' => 'viên',
            'source' => 'schedule', 'status' => 'pending',
        ]);
        $this->actingAs($this->doctor)->post(route('cabinet.refills.approve', $again), ['approved_quantity' => 10])->assertRedirect();
        $this->assertNull($this->item->fresh()->stopped_at);
        $this->editCourseTitle();
        $this->assertSame($this->course->end_date->toDateString(), $this->morning->fresh()->end_date->toDateString());
    }

    public function test_returning_a_lot_only_reopens_its_own_refill_and_removes_its_expense(): void
    {
        // Yêu cầu kê tiếp cũ đã hoàn tất từ trước
        $oldRefill = RefillRequest::create([
            'family_id' => $this->family->id, 'requested_by' => $this->member->id, 'prescription_item_id' => $this->item->id,
            'medicine_name' => 'Bisoprolol 2.5mg', 'unit' => 'viên', 'source' => 'schedule', 'status' => 'fulfilled',
        ]);

        $this->actingAs($this->familyAdmin)->post(route('cabinet.purchases.prescription.store', $this->item), [
            'quantity' => 10, 'unit' => 'viên', 'expiry_date' => now()->addYear()->toDateString(),
            'price_per_unit' => 3000, 'record_expense' => 1,
        ])->assertRedirect();
        $lot = CabinetItem::withAnyApproval()->where('prescription_item_id', $this->item->id)->latest('id')->firstOrFail();
        $this->assertNotNull($lot->medicine_expense_id);
        $this->assertDatabaseHas('medicine_expenses', ['id' => $lot->medicine_expense_id, 'amount' => 30000]);

        $this->actingAs($this->doctor)->post(route('cabinet.lots.return', $lot->id), ['review_note' => 'Sai hàm lượng'])->assertRedirect();

        $this->assertSame('fulfilled', $oldRefill->fresh()->status);
        $this->assertSame(0, MedicineExpense::where('title', 'Mua thuốc: Bisoprolol 2.5mg')->count());
        $this->assertTrue($this->item->fresh()->needs_purchase);
    }

    public function test_double_processing_is_ignored(): void
    {
        $refill = RefillRequest::create([
            'family_id' => $this->family->id, 'requested_by' => $this->member->id, 'prescription_item_id' => $this->item->id,
            'medicine_name' => 'Bisoprolol 2.5mg', 'unit' => 'viên', 'source' => 'schedule', 'status' => 'pending',
        ]);
        $this->actingAs($this->doctor)->post(route('cabinet.refills.approve', $refill), ['approved_quantity' => 10]);
        $this->actingAs($this->doctor)->post(route('cabinet.refills.approve', $refill), ['approved_quantity' => 10])
            ->assertSessionHas('warning');
        $this->assertEquals(20, (float) $this->item->fresh()->total_prescribed_quantity);

        // Mua bổ sung ngoài đơn bấm 2 lần chỉ tạo 1 lô
        $supply = $this->lot(['custom_name' => 'Gạc', 'unit' => 'hộp', 'quantity' => 1]);
        $otc = RefillRequest::create([
            'family_id' => $this->family->id, 'requested_by' => $this->member->id, 'cabinet_item_id' => $supply->id,
            'medicine_name' => 'Gạc', 'unit' => 'hộp', 'source' => 'cabinet', 'status' => 'approved', 'approved_quantity' => 1,
        ]);
        $payload = ['quantity' => 1, 'unit' => 'hộp', 'expiry_date' => now()->addYear()->toDateString()];
        $this->actingAs($this->member)->post(route('cabinet.purchases.refill.store', $otc), $payload)->assertRedirect();
        $this->actingAs($this->member)->post(route('cabinet.purchases.refill.store', $otc), $payload);
        $this->assertSame(1, CabinetItem::withAnyApproval()->where('refill_request_id', $otc->id)->count());
    }

    public function test_expired_pending_lot_cannot_be_confirmed(): void
    {
        $lot = $this->lot([
            'custom_name' => 'Bisoprolol 2.5mg', 'prescription_item_id' => $this->item->id, 'quantity' => 10,
            'approval_status' => CabinetItem::APPROVAL_PENDING, 'expiry_date' => now()->subDay()->toDateString(),
        ]);
        $this->actingAs($this->doctor)->post(route('cabinet.lots.confirm', $lot->id))->assertSessionHasErrors('review_note');
        $this->assertSame(CabinetItem::APPROVAL_PENDING, CabinetItem::withAnyApproval()->find($lot->id)->approval_status);
    }

    public function test_dashboard_does_not_report_running_low_for_medicine_waiting_confirmation(): void
    {
        $this->actingAs($this->member)->post(route('cabinet.purchases.prescription.store', $this->item), [
            'quantity' => 10, 'unit' => 'viên', 'expiry_date' => now()->addYear()->toDateString(),
        ]);
        $row = app(MedicationSupplyService::class)->forFamily($this->family)->first(fn ($r) => $r['medicine_name'] === 'Bisoprolol 2.5mg');
        $this->assertSame('out', $row['status']);
        $this->assertGreaterThan(0, $row['pending_review_quantity']);

        $this->actingAs($this->familyAdmin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lô thuốc mới mua chờ Bác sĩ xác nhận')
            ->assertDontSee('thuốc đang uống sắp hết');
    }
}
