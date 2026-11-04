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
use App\Services\AlertScannerService;
use App\Services\MedicationSupplyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionPurchaseAndRefillTest extends TestCase
{
    use RefreshDatabase;

    protected Family $family;
    protected User $familyAdmin;
    protected User $member;
    protected User $doctor;
    protected FamilyMember $grandpa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->family = Family::where('code', 'FAM-VN2026')->firstOrFail();
        $this->familyAdmin = User::where('email', 'an.nguyen@gmail.com')->firstOrFail();
        $this->member = User::where('email', 'hoa.tran@gmail.com')->firstOrFail();
        $this->doctor = User::where('email', 'bs.minh@medcabinet.vn')->firstOrFail();
        $this->grandpa = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Văn Thịnh')->firstOrFail();
    }

    /**
     * Đơn thuốc 1 viên sáng + 1 viên tối (2 viên/ngày) cho ông, đợt điều trị 20 ngày nữa mới kết thúc
     */
    private function prescribe(float $total = 30): PrescriptionItem
    {
        $course = TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn Losartan kiểm soát huyết áp',
            'hospital_doctor' => 'BS. Lê Văn Minh',
            'start_date' => Carbon::today()->subDay(),
            'end_date' => Carbon::today()->addDays(20),
            'status' => 'dang_dieu_tri',
        ]);
        $item = PrescriptionItem::create([
            'treatment_course_id' => $course->id,
            'medicine_name' => 'Losartan 50mg',
            'dosage' => '1 viên',
            'frequency' => 'Sáng - Tối',
            'total_prescribed_quantity' => $total,
            'unit' => 'viên',
        ]);
        foreach (['sang' => '07:30:00', 'toi' => '20:00:00'] as $slot => $time) {
            MedicationSchedule::create([
                'family_id' => $this->family->id,
                'family_member_id' => $this->grandpa->id,
                'prescription_item_id' => $item->id,
                'medicine_name' => 'Losartan 50mg',
                'dosage' => '1 viên',
                'unit' => 'viên',
                'time_of_day' => $slot,
                'scheduled_time' => $time,
                'meal_timing' => 'sau_an',
                'start_date' => $course->start_date,
                'end_date' => $course->end_date,
                'is_active' => true,
            ]);
        }

        return $item;
    }

    private function buy(User $user, PrescriptionItem $item, float $qty, array $extra = [])
    {
        return $this->actingAs($user)->post(route('cabinet.purchases.prescription.store', $item), array_merge([
            'quantity' => $qty,
            'unit' => 'viên',
            'expiry_date' => now()->addYear()->toDateString(),
            'storage_location' => 'Ngăn 1',
        ], $extra));
    }

    /** Bác sĩ xác nhận mọi lô thuốc đang chờ của gia đình */
    private function doctorConfirmsAll(): void
    {
        foreach (CabinetItem::withAnyApproval()->where('approval_status', CabinetItem::APPROVAL_PENDING)->get() as $lot) {
            $this->actingAs($this->doctor)->post(route('cabinet.lots.confirm', $lot->id))->assertRedirect();
        }
    }

    private function buyAndConfirm(User $user, PrescriptionItem $item, float $qty, array $extra = [])
    {
        $response = $this->buy($user, $item, $qty, $extra);
        $this->doctorConfirmsAll();

        return $response;
    }

    public function test_family_buys_prescribed_medicine_and_it_waits_for_doctor(): void
    {
        $item = $this->prescribe(30);

        // Đơn vừa kê → xuất hiện trong "Cần mua theo đơn"
        $this->actingAs($this->familyAdmin)->get(route('cabinet.supply'))
            ->assertOk()
            ->assertSee('Losartan 50mg')
            ->assertSee(route('cabinet.purchases.prescription.create', $item), false);
        $this->actingAs($this->member)->get(route('cabinet.purchases.prescription.create', $item))
            ->assertOk()->assertSee('Đơn Losartan kiểm soát huyết áp');

        // Thành viên mua 20 viên (có giá nhưng không được ghi chi phí), Chủ hộ mua nốt 10 viên và ghi chi phí
        $this->buy($this->member, $item, 20, ['price_per_unit' => 2000, 'record_expense' => 1])->assertRedirect(route('cabinet.supply'));
        // Lô vừa mua chưa nằm trong tủ: chờ Bác sĩ xác nhận, nhưng vẫn tính là đã mua (không mua trùng)
        $this->assertSame(0, CabinetItem::where('prescription_item_id', $item->id)->count());
        $lot = CabinetItem::withAnyApproval()->where('prescription_item_id', $item->id)->firstOrFail();
        $this->assertSame(CabinetItem::APPROVAL_PENDING, $lot->approval_status);
        $this->assertSame($this->member->id, $lot->purchased_by);
        $this->assertDatabaseHas('smart_alerts', ['type' => 'purchase_review', 'title' => 'Thuốc mới mua chờ xác nhận: Losartan 50mg']);
        $this->assertEquals(20, (float) $lot->purchased_quantity);
        $this->assertSame('Losartan 50mg', $lot->custom_name);
        $this->assertEquals(10, $item->fresh()->remaining_to_buy);
        $this->assertDatabaseMissing('medicine_expenses', ['title' => 'Mua thuốc: Losartan 50mg']);

        $this->buy($this->familyAdmin, $item, 10, ['price_per_unit' => 2000, 'record_expense' => 1])->assertRedirect();
        $this->assertDatabaseHas('medicine_expenses', [
            'title' => 'Mua thuốc: Losartan 50mg',
            'amount' => 20000,
            'treatment_course_id' => $item->treatment_course_id,
            'family_member_id' => $this->grandpa->id,
        ]);
        $this->assertFalse($item->fresh()->needs_purchase);
        $this->assertFalse(app(MedicationSupplyService::class)->purchaseList($this->family)->pluck('item.id')->contains($item->id));

        // Thuốc mua về đã quá hạn bị từ chối; người ngoài gia đình không mua hộ được
        $this->buy($this->familyAdmin, $item, 5, ['expiry_date' => now()->subDay()->toDateString()])->assertSessionHasErrors('expiry_date');
        $outsider = User::create([
            'family_id' => Family::create(['name' => 'Nhà khác', 'code' => 'FAM-BUY001'])->id,
            'name' => 'Người ngoài', 'email' => 'outsider.buy@example.com',
            'password' => bcrypt('password123'), 'role' => 'family_admin',
        ]);
        $this->buy($outsider, $item, 5)->assertNotFound();
    }

    public function test_existing_stock_can_be_linked_and_dose_logging_uses_prescribed_lot_first(): void
    {
        $item = $this->prescribe(30);

        // Lô mua từ trước (chưa gắn đơn) hết hạn sớm hơn
        $oldLot = CabinetItem::create([
            'family_id' => $this->family->id, 'custom_name' => 'Losartan 50mg', 'quantity' => 30,
            'unit' => 'viên', 'expiry_date' => now()->addMonth()->toDateString(), 'status' => 'con_han',
        ]);
        $this->actingAs($this->familyAdmin)->get(route('cabinet.purchases.prescription.create', $item))
            ->assertOk()->assertSee('Dùng lô này');

        // Mua lô mới theo đơn, Bác sĩ xác nhận rồi điểm danh: trừ vào lô mua theo đơn
        $this->buyAndConfirm($this->familyAdmin, $item, 10);
        $prescribedLot = CabinetItem::where('prescription_item_id', $item->id)->firstOrFail();
        $schedule = MedicationSchedule::where('prescription_item_id', $item->id)->where('time_of_day', 'sang')->firstOrFail();
        $this->actingAs($this->member)->post(route('schedules.log'), [
            'schedule_id' => $schedule->id,
            'scheduled_time' => Carbon::today()->setTime(7, 30)->toDateTimeString(),
            'status' => 'da_uong',
            'deduct_cabinet' => 1,
        ])->assertRedirect();
        $this->assertEquals(9, (float) $prescribedLot->fresh()->quantity);
        $this->assertEquals(30, (float) $oldLot->fresh()->quantity);

        // Gắn lô có sẵn vào đơn: tính là đã mua
        $this->actingAs($this->familyAdmin)->post(route('cabinet.purchases.prescription.link', $item), ['cabinet_item_id' => $oldLot->id])
            ->assertRedirect();
        $oldLot->refresh();
        $this->assertSame($item->id, $oldLot->prescription_item_id);
        $this->assertEquals(30, (float) $oldLot->purchased_quantity);
        $this->assertFalse($item->fresh()->needs_purchase);
    }

    public function test_running_low_medicine_triggers_alert_and_refill_request_doctor_prescribes_more(): void
    {
        // Đã mua đủ 4 viên theo đơn, mỗi ngày uống 2 viên → chỉ đủ 2 ngày trong khi đợt còn 21 ngày
        $item = $this->prescribe(4);
        $this->buyAndConfirm($this->familyAdmin, $item, 4);

        $row = app(MedicationSupplyService::class)->forFamily($this->family)
            ->first(fn ($r) => $r['medicine_name'] === 'Losartan 50mg');
        $this->assertSame('low', $row['status']);
        $this->assertEquals(2.0, $row['days_left']);
        $this->assertEquals(2.0, $row['daily_usage']);
        $this->assertFalse($row['awaiting_purchase']);
        $this->assertDatabaseHas('smart_alerts', ['type' => 'refill_needed', 'family_id' => $this->family->id, 'level' => 'warning']);

        $this->actingAs($this->familyAdmin)->get(route('dashboard'))->assertOk()->assertSee('thuốc đang uống sắp hết');

        // Chủ hộ gửi yêu cầu bổ sung (số liệu lấy từ hệ thống), gửi trùng không tạo thêm
        $this->actingAs($this->familyAdmin)->post(route('cabinet.refills.store'), [
            'source' => 'schedule', 'schedule_id' => $row['schedule_id'], 'requester_note' => 'Ông uống đều, sắp hết',
        ])->assertRedirect(route('cabinet.supply'));
        $refill = RefillRequest::firstOrFail();
        $this->assertSame($item->id, $refill->prescription_item_id);
        $this->assertEquals(4, (float) $refill->current_stock);
        $this->assertDatabaseHas('smart_alerts', ['type' => 'refill_request', 'family_id' => $this->family->id]);
        $this->actingAs($this->member)->post(route('cabinet.refills.store'), ['source' => 'schedule', 'schedule_id' => $row['schedule_id']]);
        $this->assertSame(1, RefillRequest::count());

        // Thành viên không được tự kê; Bác sĩ thấy yêu cầu và kê tiếp 30 viên, kéo dài đợt điều trị
        $this->actingAs($this->member)->post(route('cabinet.refills.approve', $refill), ['approved_quantity' => 30])->assertForbidden();
        $this->actingAs($this->doctor)->get(route('cabinet.supply'))->assertOk()->assertSee('Ông uống đều, sắp hết')->assertSee('Kê tiếp');

        $until = Carbon::today()->addDays(40);
        $this->actingAs($this->doctor)->post(route('cabinet.refills.approve', $refill), [
            'approved_quantity' => 30,
            'extended_until' => $until->toDateString(),
            'review_note' => 'Tiếp tục 1 viên sáng - tối',
        ])->assertRedirect(route('cabinet.supply'));

        $this->assertSame('approved', $refill->fresh()->status);
        $item->refresh();
        $this->assertEquals(34, (float) $item->total_prescribed_quantity);
        $this->assertEquals(30, $item->remaining_to_buy);
        $this->assertSame($until->toDateString(), $item->treatmentCourse->end_date->toDateString());
        $this->assertSame(2, MedicationSchedule::where('prescription_item_id', $item->id)->whereDate('end_date', $until)->count());

        // Gia đình mua đủ phần kê thêm → yêu cầu hoàn tất
        $this->buy($this->familyAdmin, $item, 30)->assertRedirect();
        $this->assertSame('fulfilled', $refill->fresh()->status);
    }

    public function test_doctor_can_stop_medicine_instead_of_refilling(): void
    {
        $item = $this->prescribe(2);
        $this->buyAndConfirm($this->familyAdmin, $item, 2);
        $row = app(MedicationSupplyService::class)->forFamily($this->family)->first(fn ($r) => $r['medicine_name'] === 'Losartan 50mg');

        $this->actingAs($this->member)->post(route('cabinet.refills.store'), ['source' => 'schedule', 'schedule_id' => $row['schedule_id']]);
        $refill = RefillRequest::firstOrFail();

        $this->actingAs($this->doctor)->post(route('cabinet.refills.stop', $refill), [])->assertSessionHasErrors('review_note');
        $this->actingAs($this->doctor)->post(route('cabinet.refills.stop', $refill), ['review_note' => 'Huyết áp đã ổn định, ngưng thuốc'])
            ->assertRedirect();

        $this->assertSame('stopped', $refill->fresh()->status);
        $this->assertSame(2, MedicationSchedule::where('prescription_item_id', $item->id)->whereDate('end_date', today())->count());
        $this->assertSame(0, MedicationSchedule::where('prescription_item_id', $item->id)->activeOnDate(today()->addDay())->count());
        $this->assertDatabaseHas('smart_alerts', ['type' => 'refill_request', 'title' => 'Bác sĩ cho ngưng thuốc: Losartan 50mg']);
    }

    public function test_as_needed_supplies_can_be_requested_approved_and_bought(): void
    {
        $lot = CabinetItem::create([
            'family_id' => $this->family->id, 'custom_name' => 'Băng gạc vô trùng', 'quantity' => 1,
            'unit' => 'hộp', 'expiry_date' => now()->addYear()->toDateString(), 'status' => 'con_han',
        ]);

        $this->actingAs($this->familyAdmin)->get(route('cabinet.supply'))->assertOk()->assertSee('Băng gạc vô trùng');
        $this->actingAs($this->familyAdmin)->post(route('cabinet.refills.store'), ['source' => 'cabinet', 'cabinet_item_id' => $lot->id])
            ->assertRedirect();
        $refill = RefillRequest::where('cabinet_item_id', $lot->id)->firstOrFail();

        // Bác sĩ không tự gửi yêu cầu; đồng ý cho mua thêm 2 hộp
        $this->actingAs($this->doctor)->post(route('cabinet.refills.store'), ['source' => 'cabinet', 'cabinet_item_id' => $lot->id])->assertForbidden();
        $this->actingAs($this->doctor)->post(route('cabinet.refills.approve', $refill), ['approved_quantity' => 2])->assertRedirect();
        $this->assertSame('approved', $refill->fresh()->status);

        // Chưa mua thì chưa được nhập theo yêu cầu khác; mua về → hoàn tất, có lô mới trong tủ
        $this->actingAs($this->familyAdmin)->get(route('cabinet.purchases.refill.create', $refill))->assertOk()->assertSee('Được mua thêm');
        $this->actingAs($this->familyAdmin)->post(route('cabinet.purchases.refill.store', $refill), [
            'quantity' => 2, 'unit' => 'hộp', 'expiry_date' => now()->addYears(2)->toDateString(),
        ])->assertRedirect(route('cabinet.supply'));

        $this->assertSame('fulfilled', $refill->fresh()->status);
        // Lô mua bổ sung cũng phải chờ Bác sĩ xác nhận mới vào tủ
        $this->assertSame(1, CabinetItem::where('family_id', $this->family->id)->where('custom_name', 'Băng gạc vô trùng')->count());
        $this->doctorConfirmsAll();
        $this->assertSame(2, CabinetItem::where('family_id', $this->family->id)->where('custom_name', 'Băng gạc vô trùng')->count());
        $this->actingAs($this->familyAdmin)->post(route('cabinet.purchases.refill.store', $refill), [
            'quantity' => 2, 'unit' => 'hộp', 'expiry_date' => now()->addYears(2)->toDateString(),
        ])->assertForbidden();
    }

    public function test_supply_alert_is_removed_once_stock_is_enough(): void
    {
        $item = $this->prescribe(4);
        $this->buyAndConfirm($this->familyAdmin, $item, 4);
        $this->assertDatabaseHas('smart_alerts', ['type' => 'refill_needed']);

        // Có thêm đủ thuốc cho cả đợt → cảnh báo sắp hết tự gỡ
        CabinetItem::where('prescription_item_id', $item->id)->update(['quantity' => 60]);
        app(AlertScannerService::class)->scanFamily($this->family);
        $this->assertSame(0, SmartAlert::where('type', 'refill_needed')->count());
    }

    public function test_expired_medicine_is_flagged_for_disposal_not_rebuy(): void
    {
        $expired = CabinetItem::create([
            'family_id' => $this->family->id, 'custom_name' => 'Kháng sinh hết hạn thử', 'quantity' => 4,
            'unit' => 'viên', 'expiry_date' => now()->subWeek()->toDateString(), 'status' => 'qua_han',
        ]);

        $this->actingAs($this->familyAdmin)->get(route('cabinet.supply'))
            ->assertOk()->assertSee('Kháng sinh hết hạn thử')->assertSee('Cần loại bỏ khỏi tủ');

        $this->actingAs($this->familyAdmin)->post(route('cabinet.refills.store'), ['source' => 'cabinet', 'cabinet_item_id' => $expired->id])
            ->assertSessionHas('warning');
        $this->assertSame(0, RefillRequest::count());
    }

    public function test_system_admin_handles_refill_for_family_without_doctor(): void
    {
        // Gỡ Bác sĩ khỏi gia đình mẫu
        $this->doctor->update(['family_id' => null]);
        $lot = CabinetItem::create([
            'family_id' => $this->family->id, 'custom_name' => 'Nước muối sinh lý', 'quantity' => 1,
            'unit' => 'chai', 'expiry_date' => now()->addYear()->toDateString(), 'status' => 'con_han',
        ]);

        $this->actingAs($this->familyAdmin)->get(route('cabinet.supply'))->assertOk()->assertSee('Gia đình chưa có Bác sĩ');
        $this->actingAs($this->familyAdmin)->post(route('cabinet.refills.store'), ['source' => 'cabinet', 'cabinet_item_id' => $lot->id])
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Quản trị viên hệ thống sẽ xử lý'));
        $refill = RefillRequest::firstOrFail();

        $admin = User::where('email', 'admin@medcabinet.vn')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.cabinet-requests.index'))
            ->assertOk()
            ->assertSee('Yêu cầu bổ sung thuốc của hộ chưa có Bác sĩ')
            ->assertSee('Nước muối sinh lý');
        $this->actingAs($admin)->post(route('cabinet.refills.approve', $refill), ['approved_quantity' => 3])
            ->assertRedirect(route('admin.cabinet-requests.index'));
        $this->assertSame('approved', $refill->fresh()->status);
    }
}
