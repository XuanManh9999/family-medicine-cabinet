<?php

namespace Tests\Feature;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\SmartAlert;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartAlertManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Family $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->family = Family::where('code', 'FAM-VN2026')->first();
        $this->user = User::where('email', 'an.nguyen@gmail.com')->first();
    }

    public function test_user_can_view_alerts_index_with_kpis(): void
    {
        $response = $this->actingAs($this->user)->get(route('alerts.index'));

        $response->assertStatus(200);
        $response->assertSee('Trung tâm Cảnh báo Thông minh &amp; An toàn Tủ thuốc', false);
        $response->assertSee('Nguy hiểm khẩn cấp');
        $response->assertSee('Cảnh báo cần chú ý');
        $response->assertSee('Chưa xử lý');
        $response->assertSee('Tổng số cảnh báo');
        $response->assertSee('Quét an toàn tủ thuốc ngay');
    }

    public function test_user_can_filter_alerts_by_level_and_type(): void
    {
        // Tạo cảnh báo nguy hiểm (đã đọc để không lọt vào unread dropdown trên header)
        SmartAlert::create([
            'family_id' => $this->family->id,
            'type' => 'expired',
            'level' => 'danger',
            'title' => 'Thuốc Cephalexin đã quá đát',
            'content' => 'Thuốc đã hết hạn, không được uống.',
            'is_read' => true,
        ]);

        // Tạo cảnh báo cận date
        SmartAlert::create([
            'family_id' => $this->family->id,
            'type' => 'near_expiry',
            'level' => 'warning',
            'title' => 'Thuốc Acemuc cận date',
            'content' => 'Còn 15 ngày nữa là hết hạn.',
            'is_read' => true,
        ]);

        // Lọc theo level danger
        $dangerResponse = $this->actingAs($this->user)->get(route('alerts.index', ['level' => 'danger']));
        $dangerResponse->assertStatus(200);
        $dangerAlerts = $dangerResponse->viewData('alerts');
        $this->assertTrue($dangerAlerts->contains('title', 'Thuốc Cephalexin đã quá đát'));
        $this->assertFalse($dangerAlerts->contains('title', 'Thuốc Acemuc cận date'));
        $dangerResponse->assertSee('Thuốc Cephalexin đã quá đát');
        $dangerResponse->assertDontSee('Thuốc Acemuc cận date');

        // Lọc theo type near_expiry
        $typeResponse = $this->actingAs($this->user)->get(route('alerts.index', ['type' => 'near_expiry']));
        $typeResponse->assertStatus(200);
        $typeAlerts = $typeResponse->viewData('alerts');
        $this->assertTrue($typeAlerts->contains('title', 'Thuốc Acemuc cận date'));
        $this->assertFalse($typeAlerts->contains('title', 'Thuốc Cephalexin đã quá đát'));
        $typeResponse->assertSee('Thuốc Acemuc cận date');
        $typeResponse->assertDontSee('Thuốc Cephalexin đã quá đát');
    }

    public function test_user_can_search_alerts_by_keyword(): void
    {
        SmartAlert::create([
            'family_id' => $this->family->id,
            'type' => 'drug_interaction',
            'level' => 'danger',
            'title' => 'Nguy cơ quá liều Paracetamol nghiêm trọng',
            'content' => 'Phát hiện Panadol và Decolgen trong cùng tủ thuốc.',
            'is_read' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('alerts.index', ['q' => 'Decolgen']));
        $response->assertStatus(200);
        $this->assertTrue($response->viewData('alerts')->contains('title', 'Nguy cơ quá liều Paracetamol nghiêm trọng'));
        $response->assertSee('Nguy cơ quá liều Paracetamol nghiêm trọng');

        $emptyResponse = $this->actingAs($this->user)->get(route('alerts.index', ['q' => 'KhongTonTai123456']));
        $emptyResponse->assertStatus(200);
        $this->assertFalse($emptyResponse->viewData('alerts')->contains('title', 'Nguy cơ quá liều Paracetamol nghiêm trọng'));
        $emptyResponse->assertDontSee('Nguy cơ quá liều Paracetamol nghiêm trọng');
    }

    public function test_user_can_mark_single_alert_as_read(): void
    {
        $alert = SmartAlert::create([
            'family_id' => $this->family->id,
            'type' => 'near_expiry',
            'level' => 'warning',
            'title' => 'Cảnh báo cần đọc',
            'content' => 'Nội dung kiểm tra.',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->user)->patch(route('alerts.read', $alert));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $alert->refresh();
        $this->assertTrue($alert->is_read);
    }

    public function test_user_can_mark_all_alerts_as_read(): void
    {
        SmartAlert::create([
            'family_id' => $this->family->id,
            'type' => 'near_expiry',
            'level' => 'warning',
            'title' => 'Tin 1',
            'content' => 'Nội dung 1',
            'is_read' => false,
        ]);

        SmartAlert::create([
            'family_id' => $this->family->id,
            'type' => 'low_stock',
            'level' => 'warning',
            'title' => 'Tin 2',
            'content' => 'Nội dung 2',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->user)->post(route('alerts.mark-all-read'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $unreadCount = SmartAlert::where('family_id', $this->family->id)->unread()->count();
        $this->assertEquals(0, $unreadCount);
    }

    public function test_user_can_delete_alert(): void
    {
        $alert = SmartAlert::create([
            'family_id' => $this->family->id,
            'type' => 'low_stock',
            'level' => 'warning',
            'title' => 'Cảnh báo sẽ bị xóa',
            'content' => 'Nội dung xóa',
            'is_read' => true,
        ]);

        $response = $this->actingAs($this->user)->delete(route('alerts.destroy', $alert));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('smart_alerts', ['id' => $alert->id]);
    }

    public function test_user_can_trigger_scan_and_detect_expired_and_low_stock_medicines(): void
    {
        // Tạo thuốc hết hạn
        CabinetItem::create([
            'family_id' => $this->family->id,
            'custom_name' => 'Thuốc Kháng sinh Cũ Quá Hạn',
            'quantity' => 10,
            'unit' => 'viên',
            'expiry_date' => Carbon::now()->subDays(15),
            'storage_location' => 'Góc tủ thuốc tầng 1',
        ]);

        // Tạo thuốc sắp hết tồn kho
        CabinetItem::create([
            'family_id' => $this->family->id,
            'custom_name' => 'Băng dán cá nhân gạc y tế',
            'quantity' => 1,
            'unit' => 'miếng',
            'expiry_date' => Carbon::now()->addMonths(6),
            'storage_location' => 'Ngăn kéo sơ cứu',
        ]);

        $response = $this->actingAs($this->user)->post(route('alerts.scan'));

        $response->assertRedirect(route('alerts.index'));
        $response->assertSessionHas('success');

        // Kiểm tra database có cảnh báo mới
        $this->assertDatabaseHas('smart_alerts', [
            'family_id' => $this->family->id,
            'type' => 'expired',
            'level' => 'danger',
        ]);

        $this->assertDatabaseHas('smart_alerts', [
            'family_id' => $this->family->id,
            'type' => 'low_stock',
            'level' => 'warning',
        ]);
    }

    public function test_user_cannot_access_or_modify_other_familys_alert(): void
    {
        $otherFamily = Family::create([
            'name' => 'Gia đình khác',
            'code' => 'FAM-OTHER-ALERT-999',
            'address' => 'Đà Nẵng',
        ]);

        $otherAlert = SmartAlert::create([
            'family_id' => $otherFamily->id,
            'type' => 'expired',
            'level' => 'danger',
            'title' => 'Cảnh báo nhà người khác',
            'content' => 'Không được sửa',
            'is_read' => false,
        ]);

        $readResponse = $this->actingAs($this->user)->patch(route('alerts.read', $otherAlert));
        $readResponse->assertStatus(403);

        $deleteResponse = $this->actingAs($this->user)->delete(route('alerts.destroy', $otherAlert));
        $deleteResponse->assertStatus(403);
    }
}
