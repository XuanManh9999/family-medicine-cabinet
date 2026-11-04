<?php

namespace Tests\Feature;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationLog;
use App\Models\MedicationSchedule;
use App\Models\PrescriptionItem;
use App\Models\TreatmentCourse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Siết phân quyền: kiểm tra chéo khi xác nhận thuốc, Bác sĩ sửa thông tin y tế, không xóa đơn đã có lịch sử
 */
class PermissionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Family $family;
    protected User $familyAdmin;
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
        $this->doctor = User::where('email', 'bs.minh@medcabinet.vn')->firstOrFail();
        $this->grandpa = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Văn Thịnh')->firstOrFail();

        $this->course = TreatmentCourse::create([
            'family_id' => $this->family->id, 'family_member_id' => $this->grandpa->id, 'title' => 'Đơn thử quyền',
            'start_date' => Carbon::today()->subDay(), 'end_date' => Carbon::today()->addDays(10), 'status' => 'dang_dieu_tri',
        ]);
        $this->item = PrescriptionItem::create([
            'treatment_course_id' => $this->course->id, 'medicine_name' => 'Losartan 50mg', 'dosage' => '1 viên',
            'frequency' => 'Sáng', 'total_prescribed_quantity' => 10, 'unit' => 'viên',
        ]);
    }

    public function test_doctor_cannot_confirm_a_lot_they_bought_themselves(): void
    {
        $this->actingAs($this->doctor)->post(route('cabinet.purchases.prescription.store', $this->item), [
            'quantity' => 10, 'unit' => 'viên', 'expiry_date' => now()->addYear()->toDateString(),
        ])->assertRedirect();
        $lot = CabinetItem::withAnyApproval()->where('prescription_item_id', $this->item->id)->firstOrFail();

        $this->actingAs($this->doctor)->get(route('cabinet.supply'))
            ->assertOk()->assertSee('Bạn là người mua lô này')->assertDontSee('Xác nhận đúng thuốc</button>', false);
        $this->actingAs($this->doctor)->post(route('cabinet.lots.confirm', $lot->id))->assertForbidden();
        $this->assertSame(CabinetItem::APPROVAL_PENDING, CabinetItem::withAnyApproval()->find($lot->id)->approval_status);

        // Quản trị viên thấy lô do Bác sĩ tự mua và xác nhận thay (kiểm tra chéo)
        $admin = User::where('email', 'admin@medcabinet.vn')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.cabinet-requests.index'))->assertOk()->assertSee('Losartan 50mg');
        $this->actingAs($admin)->post(route('cabinet.lots.confirm', $lot->id))->assertRedirect();
        $this->assertSame(CabinetItem::APPROVAL_APPROVED, CabinetItem::withAnyApproval()->find($lot->id)->approval_status);
    }

    public function test_doctor_updates_only_medical_info_of_member(): void
    {
        $this->actingAs($this->doctor)->get(route('members.edit', $this->grandpa))
            ->assertOk()->assertSee('chỉ sửa được nhóm máu, tiền sử dị ứng và bệnh nền');

        $this->actingAs($this->doctor)->put(route('members.update', $this->grandpa), [
            'name' => 'Tên bị đổi trái phép',
            'relationship' => 'khac',
            'gender' => 'nu',
            'blood_type' => 'A+',
            'allergies' => 'Dị ứng Penicillin (phát ban)',
            'underlying_diseases' => 'Tăng huyết áp, Đái tháo đường type 2',
        ])->assertRedirect(route('members.show', $this->grandpa));

        $this->grandpa->refresh();
        $this->assertSame('Nguyễn Văn Thịnh', $this->grandpa->name);
        $this->assertNotSame('khac', $this->grandpa->relationship);
        $this->assertSame('nam', $this->grandpa->gender);
        $this->assertSame('A+', $this->grandpa->blood_type);
        $this->assertSame('Dị ứng Penicillin (phát ban)', $this->grandpa->allergies);

        // Thành viên thường vẫn không sửa được hồ sơ người khác
        $member = User::where('email', 'hoa.tran@gmail.com')->firstOrFail();
        $this->actingAs($member)->get(route('members.edit', $this->grandpa))->assertForbidden();
    }

    public function test_course_with_history_cannot_be_deleted_but_empty_one_can(): void
    {
        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id, 'family_member_id' => $this->grandpa->id, 'prescription_item_id' => $this->item->id,
            'medicine_name' => 'Losartan 50mg', 'dosage' => '1 viên', 'unit' => 'viên', 'time_of_day' => 'sang',
            'scheduled_time' => '07:30:00', 'meal_timing' => 'sau_an', 'start_date' => $this->course->start_date, 'is_active' => true,
        ]);
        MedicationLog::create([
            'schedule_id' => $schedule->id, 'family_member_id' => $this->grandpa->id,
            'scheduled_time' => Carbon::today()->setTime(7, 30), 'status' => 'da_uong',
        ]);

        $this->actingAs($this->doctor)->delete(route('treatment-courses.destroy', $this->course))->assertSessionHas('warning');
        $this->assertDatabaseHas('treatment_courses', ['id' => $this->course->id]);
        $this->assertSame(1, MedicationLog::where('schedule_id', $schedule->id)->count());

        // Đơn tạo nhầm, chưa có dữ liệu gì thì vẫn xóa được
        $empty = TreatmentCourse::create([
            'family_id' => $this->family->id, 'family_member_id' => $this->grandpa->id, 'title' => 'Đơn tạo nhầm',
            'start_date' => Carbon::today(), 'status' => 'dang_dieu_tri',
        ]);
        $this->actingAs($this->doctor)->delete(route('treatment-courses.destroy', $empty))->assertRedirect(route('treatment-courses.index'));
        $this->assertDatabaseMissing('treatment_courses', ['id' => $empty->id]);
    }

    public function test_doctor_purchase_does_not_record_family_expense(): void
    {
        $this->actingAs($this->doctor)->post(route('cabinet.purchases.prescription.store', $this->item), [
            'quantity' => 10, 'unit' => 'viên', 'expiry_date' => now()->addYear()->toDateString(),
            'price_per_unit' => 5000, 'record_expense' => 1,
        ])->assertRedirect();

        $this->assertDatabaseMissing('medicine_expenses', ['title' => 'Mua thuốc: Losartan 50mg']);
    }
}
