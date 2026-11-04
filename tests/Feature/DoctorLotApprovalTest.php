<?php

namespace Tests\Feature;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationSchedule;
use App\Models\PrescriptionItem;
use App\Models\RefillRequest;
use App\Models\SmartAlert;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Services\MedicationSupplyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mọi lô thuốc gia đình mua về phải được Bác sĩ xác nhận mới được dùng
 */
class DoctorLotApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected Family $family;
    protected User $familyAdmin;
    protected User $member;
    protected User $doctor;
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
        $grandpa = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Văn Thịnh')->firstOrFail();

        $course = TreatmentCourse::create([
            'family_id' => $this->family->id, 'family_member_id' => $grandpa->id,
            'title' => 'Đơn Bisoprolol', 'start_date' => Carbon::today()->subDay(),
            'end_date' => Carbon::today()->addDays(20), 'status' => 'dang_dieu_tri',
        ]);
        $this->item = PrescriptionItem::create([
            'treatment_course_id' => $course->id, 'medicine_name' => 'Bisoprolol 2.5mg',
            'dosage' => '1 viên', 'frequency' => 'Sáng', 'total_prescribed_quantity' => 10, 'unit' => 'viên',
        ]);
        $this->morning = MedicationSchedule::create([
            'family_id' => $this->family->id, 'family_member_id' => $grandpa->id,
            'prescription_item_id' => $this->item->id, 'medicine_name' => 'Bisoprolol 2.5mg',
            'dosage' => '1 viên', 'unit' => 'viên', 'time_of_day' => 'sang', 'scheduled_time' => '07:30:00',
            'meal_timing' => 'sau_an', 'start_date' => $course->start_date, 'end_date' => $course->end_date, 'is_active' => true,
        ]);
    }

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    private function buy(array $extra = []): CabinetItem
    {
        $this->actingAs($this->member)->post(route('cabinet.purchases.prescription.store', $this->item), array_merge([
            'quantity' => 10, 'unit' => 'viên', 'expiry_date' => now()->addYear()->toDateString(),
        ], $extra))->assertRedirect();

        return CabinetItem::withAnyApproval()->where('prescription_item_id', $this->item->id)->latest('id')->firstOrFail();
    }

    private function logTaken()
    {
        return $this->actingAs($this->member)->post(route('schedules.log'), [
            'schedule_id' => $this->morning->id,
            'scheduled_time' => Carbon::today()->setTime(7, 30)->toDateTimeString(),
            'status' => 'da_uong',
            'deduct_cabinet' => 1,
        ]);
    }

    public function test_unconfirmed_lot_cannot_be_used(): void
    {
        Storage::fake('local');
        $lot = $this->buy(['box_photos' => [$this->png('vo-hop.png')]]);

        $this->assertSame(CabinetItem::APPROVAL_PENDING, $lot->approval_status);
        Storage::disk('local')->assertExists($lot->box_image_paths[0]);

        // Không hiện trong tủ, có thông báo chờ xác nhận
        $this->actingAs($this->familyAdmin)->get(route('cabinet.index'))
            ->assertOk()->assertSee('chờ Bác sĩ xác nhận')->assertDontSee('LOT-');
        $this->assertSame(0, CabinetItem::where('family_id', $this->family->id)->where('custom_name', 'Bisoprolol 2.5mg')->count());

        // Điểm danh không trừ vào lô chưa duyệt
        $this->logTaken()->assertSessionHas('warning');
        $this->assertEquals(10, (float) $lot->fresh()->quantity);

        // Không bị báo nhầm "sắp hết": đã mua, đang chờ xác nhận
        $row = app(MedicationSupplyService::class)->forFamily($this->family)->first(fn ($r) => $r['medicine_name'] === 'Bisoprolol 2.5mg');
        $this->assertEquals(10, $row['pending_review_quantity']);
        $this->assertSame(0, SmartAlert::where('type', 'refill_needed')->count());

        // Chủ hộ / thành viên không tự xác nhận được
        $this->actingAs($this->familyAdmin)->post(route('cabinet.lots.confirm', $lot->id))->assertForbidden();
        $this->actingAs($this->member)->post(route('cabinet.lots.confirm', $lot->id))->assertForbidden();
    }

    public function test_doctor_confirms_lot_then_it_is_usable(): void
    {
        Storage::fake('local');
        $lot = $this->buy(['box_photos' => [$this->png('vo-hop.png')]]);

        $this->actingAs($this->doctor)->get(route('cabinet.supply'))
            ->assertOk()
            ->assertSee('Xác nhận đúng thuốc')
            ->assertSee(route('cabinet.lots.photo', [$lot->id, 0]), false);
        $this->actingAs($this->doctor)->get(route('cabinet.lots.photo', [$lot->id, 0]))->assertOk();

        $this->actingAs($this->doctor)->post(route('cabinet.lots.confirm', $lot->id), ['review_note' => 'Uống sau ăn sáng'])
            ->assertRedirect(route('cabinet.supply'));

        $lot->refresh();
        $this->assertSame(CabinetItem::APPROVAL_APPROVED, $lot->approval_status);
        $this->assertSame($this->doctor->id, $lot->approved_by);
        $this->assertStringContainsString('Uống sau ăn sáng', $lot->notes);
        $this->assertDatabaseHas('smart_alerts', ['type' => 'purchase_review', 'title' => 'Đã xác nhận, được dùng: Bisoprolol 2.5mg']);

        // Xác nhận rồi mới trừ kho khi điểm danh
        $this->logTaken()->assertRedirect();
        $this->assertEquals(9, (float) $lot->fresh()->quantity);

        // Đã xử lý thì không xác nhận / trả lại lần nữa
        $this->actingAs($this->doctor)->post(route('cabinet.lots.return', $lot->id), ['review_note' => 'x'])->assertStatus(409);
    }

    public function test_doctor_returns_wrong_lot_and_family_must_buy_again(): void
    {
        $lot = $this->buy();
        $this->assertFalse($this->item->fresh()->needs_purchase);

        $this->actingAs($this->doctor)->post(route('cabinet.lots.return', $lot->id), [])->assertSessionHasErrors('review_note');
        $this->actingAs($this->doctor)->post(route('cabinet.lots.return', $lot->id), ['review_note' => 'Sai hàm lượng, đơn kê 2.5mg'])
            ->assertRedirect();

        $this->assertSame(CabinetItem::APPROVAL_RETURNED, $lot->fresh()->approval_status);
        // Lô bị trả lại không tính là đã mua → thuốc xuất hiện lại trong danh sách cần mua
        $this->assertTrue($this->item->fresh()->needs_purchase);
        $this->assertDatabaseHas('smart_alerts', ['type' => 'purchase_review', 'title' => 'Bác sĩ trả lại thuốc: Bisoprolol 2.5mg']);
        $this->actingAs($this->familyAdmin)->get(route('cabinet.supply'))
            ->assertOk()->assertSee('Bị trả lại')->assertSee('Sai hàm lượng, đơn kê 2.5mg');
    }

    public function test_returned_refill_purchase_reopens_the_refill(): void
    {
        $supplyLot = CabinetItem::create([
            'family_id' => $this->family->id, 'custom_name' => 'Gạc vô trùng', 'quantity' => 1,
            'unit' => 'hộp', 'expiry_date' => now()->addYear()->toDateString(), 'status' => 'con_han',
        ]);
        $this->actingAs($this->familyAdmin)->post(route('cabinet.refills.store'), ['source' => 'cabinet', 'cabinet_item_id' => $supplyLot->id]);
        $refill = RefillRequest::firstOrFail();
        $this->actingAs($this->doctor)->post(route('cabinet.refills.approve', $refill), ['approved_quantity' => 2]);

        $this->actingAs($this->familyAdmin)->post(route('cabinet.purchases.refill.store', $refill), [
            'quantity' => 2, 'unit' => 'hộp', 'expiry_date' => now()->addYear()->toDateString(),
        ]);
        $bought = CabinetItem::withAnyApproval()->where('refill_request_id', $refill->id)->firstOrFail();
        $this->assertSame('fulfilled', $refill->fresh()->status);

        $this->actingAs($this->doctor)->post(route('cabinet.lots.return', $bought->id), ['review_note' => 'Gạc không vô trùng']);
        $this->assertSame('approved', $refill->fresh()->status);
    }

    public function test_system_admin_confirms_for_family_without_doctor(): void
    {
        $this->doctor->update(['family_id' => null]);
        $lot = $this->buy();

        $admin = User::where('email', 'admin@medcabinet.vn')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.cabinet-requests.index'))
            ->assertOk()->assertSee('Thuốc đã mua chờ xác nhận (hộ chưa có Bác sĩ / do Bác sĩ tự mua)')->assertSee('Bisoprolol 2.5mg');
        $this->actingAs($admin)->post(route('cabinet.lots.confirm', $lot->id))->assertRedirect(route('admin.cabinet-requests.index'));
        $this->assertSame(CabinetItem::APPROVAL_APPROVED, $lot->fresh()->approval_status);

        // Bác sĩ của gia đình khác không xác nhận được
        $otherDoctor = User::create([
            'family_id' => Family::create(['name' => 'Nhà khác', 'code' => 'FAM-LOT001'])->id,
            'name' => 'BS nhà khác', 'email' => 'other.lot@example.com',
            'password' => bcrypt('password123'), 'role' => 'doctor_caregiver',
        ]);
        $second = $this->buy();
        $this->actingAs($otherDoctor)->post(route('cabinet.lots.confirm', $second->id))->assertNotFound();
    }
}
