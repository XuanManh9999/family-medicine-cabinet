<?php

namespace Tests\Feature;

use App\Models\CabinetItem;
use App\Models\CabinetRequest;
use App\Models\Family;
use App\Models\Medicine;
use App\Models\SmartAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CabinetRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $familyAdmin;
    protected User $member;
    protected User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->familyAdmin = User::where('email', 'an.nguyen@gmail.com')->first();
        $this->member = User::where('email', 'hoa.tran@gmail.com')->first();
        $this->doctor = User::where('email', 'bs.minh@medcabinet.vn')->first();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'custom_name' => 'Siro ho Prospan mua thêm',
            'batch_number' => 'LOT-REQ-01',
            'expiry_date' => now()->addYear()->toDateString(),
            'quantity' => 2,
            'unit' => 'chai',
            'storage_location' => 'Ngăn 1',
            'price_per_unit' => 95000,
            'record_expense' => 1,
        ], $overrides);
    }

    public function test_family_admin_request_is_approved_by_doctor_into_cabinet_with_expense(): void
    {
        // Chủ hộ gửi đề nghị: chưa có thuốc nào vào tủ
        $this->actingAs($this->familyAdmin)
            ->post(route('cabinet.requests.store'), $this->payload())
            ->assertRedirect(route('cabinet.requests.index'));

        $request = CabinetRequest::firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertTrue($request->record_expense);
        $this->assertDatabaseMissing('cabinet_items', ['custom_name' => 'Siro ho Prospan mua thêm']);
        $this->assertDatabaseHas('smart_alerts', ['type' => 'cabinet_request', 'family_id' => $request->family_id]);

        // Bác sĩ thấy đề nghị chờ duyệt, chỉnh số lượng rồi duyệt
        $this->actingAs($this->doctor)->get(route('cabinet.requests.index'))
            ->assertOk()->assertSee('Xem & duyệt', false);
        $this->actingAs($this->doctor)->get(route('cabinet.requests.review', $request))
            ->assertOk()->assertSee('Duyệt &amp; nhập vào tủ thuốc', false)->assertSee('LOT-REQ-01')
            ->assertSee('name="quantity" type="number" min="0" max="999999.99" step="0.01" required' . "\n" . '                                   value="2"', false);

        $this->actingAs($this->doctor)
            ->post(route('cabinet.requests.approve', $request), $this->payload(['quantity' => 3]))
            ->assertRedirect(route('cabinet.requests.index'));

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame($this->doctor->id, $request->reviewed_by);

        $item = CabinetItem::findOrFail($request->cabinet_item_id);
        $this->assertSame('Siro ho Prospan mua thêm', $item->custom_name);
        $this->assertEquals(3, (float) $item->quantity);

        // Chi phí = 3 × 95.000 được ghi tự động
        $this->assertDatabaseHas('medicine_expenses', [
            'id' => $request->medicine_expense_id,
            'family_id' => $request->family_id,
            'amount' => 285000,
        ]);

        // Duyệt lại lần nữa không nhập trùng
        $this->actingAs($this->doctor)
            ->post(route('cabinet.requests.approve', $request), $this->payload())
            ->assertRedirect();
        $this->assertSame(1, CabinetItem::where('custom_name', 'Siro ho Prospan mua thêm')->count());
    }

    public function test_doctor_can_reject_with_reason_and_member_can_cancel_own_request(): void
    {
        $this->actingAs($this->member)->post(route('cabinet.requests.store'), $this->payload(['record_expense' => 1]));
        $memberRequest = CabinetRequest::latest('id')->firstOrFail();
        // Thành viên thường không được bật ghi chi phí tự động
        $this->assertFalse($memberRequest->record_expense);

        $this->actingAs($this->doctor)
            ->post(route('cabinet.requests.reject', $memberRequest), [])
            ->assertSessionHasErrors('review_note');

        $this->actingAs($this->doctor)
            ->post(route('cabinet.requests.reject', $memberRequest), ['review_note' => 'Sai số lô trên vỏ hộp'])
            ->assertRedirect(route('cabinet.requests.index'));
        $this->assertSame('rejected', $memberRequest->fresh()->status);
        $this->assertDatabaseMissing('cabinet_items', ['custom_name' => 'Siro ho Prospan mua thêm']);

        $this->actingAs($this->member)->post(route('cabinet.requests.store'), $this->payload(['custom_name' => 'Vitamin C mua thêm']));
        $second = CabinetRequest::latest('id')->firstOrFail();
        $this->actingAs($this->member)->post(route('cabinet.requests.cancel', $second))->assertRedirect();
        $this->assertSame('cancelled', $second->fresh()->status);
    }

    public function test_permissions_on_cabinet_requests(): void
    {
        // Bác sĩ nhập thẳng vào tủ, không gửi đề nghị
        $this->actingAs($this->doctor)->get(route('cabinet.requests.create'))->assertForbidden();

        $this->actingAs($this->familyAdmin)->post(route('cabinet.requests.store'), $this->payload());
        $request = CabinetRequest::firstOrFail();

        // Chủ hộ / thành viên không được tự duyệt
        $this->actingAs($this->familyAdmin)->get(route('cabinet.requests.review', $request))->assertForbidden();
        $this->actingAs($this->member)->post(route('cabinet.requests.approve', $request), $this->payload())->assertForbidden();
        // Thành viên khác không hủy được đề nghị của chủ hộ
        $this->actingAs($this->member)->post(route('cabinet.requests.cancel', $request))->assertForbidden();

        // Bác sĩ của gia đình khác không xem được
        $otherFamily = Family::create(['name' => 'Gia đình khác', 'code' => 'FAM-OTHER2']);
        $otherDoctor = User::create([
            'family_id' => $otherFamily->id,
            'name' => 'BS. Nhà khác',
            'email' => 'other.doctor@example.com',
            'password' => bcrypt('password123'),
            'role' => 'doctor_caregiver',
        ]);
        $this->actingAs($otherDoctor)->get(route('cabinet.requests.review', $request))->assertNotFound();

        // Dữ liệu sai bị từ chối như form thêm thuốc
        $this->actingAs($this->familyAdmin)
            ->post(route('cabinet.requests.store'), $this->payload(['quantity' => -1, 'expiry_date' => '']))
            ->assertSessionHasErrors(['quantity', 'expiry_date']);
    }

    public function test_request_entry_points_follow_roles(): void
    {
        $medicine = Medicine::firstOrFail();

        $this->actingAs($this->familyAdmin)->get(route('cabinet.index'))
            ->assertOk()->assertSee(route('cabinet.supply'), false)->assertSee(route('cabinet.requests.index'), false);
        $this->actingAs($this->familyAdmin)->get(route('medicines.index'))
            ->assertOk()->assertSee(route('cabinet.requests.create', ['medicine_id' => $medicine->id]), false);

        // Form đề nghị điền sẵn thuốc chọn từ danh mục
        $this->actingAs($this->familyAdmin)->get(route('cabinet.requests.create', ['medicine_id' => $medicine->id]))
            ->assertOk()->assertSee('Chụp ảnh vỏ hộp thuốc')->assertSee('Gửi cho Bác sĩ kiểm tra')->assertSee($medicine->name);

        // Bác sĩ thấy số đề nghị chờ duyệt trên menu
        $this->actingAs($this->familyAdmin)->post(route('cabinet.requests.store'), $this->payload());
        $this->actingAs($this->doctor)->get(route('dashboard'))
            ->assertOk()->assertSee(route('cabinet.requests.index', ['status' => 'pending']), false);
        $this->assertTrue(SmartAlert::where('type', 'cabinet_request')->exists());
    }
}
