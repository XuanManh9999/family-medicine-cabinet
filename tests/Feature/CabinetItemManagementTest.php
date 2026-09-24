<?php

namespace Tests\Feature;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CabinetItemManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_view_cabinet_index_with_stats(): void
    {
        $admin = User::where('email', 'an.nguyen@gmail.com')->first();
        $response = $this->actingAs($admin)->get(route('cabinet.index'));

        $response->assertStatus(200);
        $response->assertSee('Tủ thuốc gia đình');
        $response->assertSee('Xuất Excel');
        $response->assertSee('Nhập từ Excel');
    }

    public function test_admin_can_export_cabinet_to_excel_csv(): void
    {
        $admin = User::where('email', 'an.nguyen@gmail.com')->first();
        $response = $this->actingAs($admin)->get(route('cabinet.export-excel'));

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), 'attachment; filename='));

        $content = $response->streamedContent();
        $this->assertNotEmpty($content);
        $this->assertTrue(str_contains($content, 'Tên thuốc / Biệt dược'));
    }

    public function test_admin_can_download_sample_excel_template(): void
    {
        $admin = User::where('email', 'an.nguyen@gmail.com')->first();
        $response = $this->actingAs($admin)->get(route('cabinet.sample-excel'));

        $response->assertStatus(200);
        $content = $response->streamedContent();
        $this->assertTrue(str_contains($content, 'Tên thuốc'));
        $this->assertTrue(str_contains($content, 'Hạn sử dụng'));
    }

    public function test_admin_can_import_medicines_from_csv(): void
    {
        $admin = User::where('email', 'an.nguyen@gmail.com')->first();
        $family = $admin->family;

        $csvContent = "\xEF\xBB\xBF" . "Tên thuốc,Hoạt chất,Số lô,Ngày sản xuất,Hạn sử dụng,Số lượng,Đơn vị,Đơn giá,Vị trí cất,Ghi chú\n" .
            "Thuốc ho Prospan,Cao lá thường xuân,LOT-PRO-01,2026-01-10,2028-10-15,3,chai,120000,Ngăn 2 tủ phòng khách,Uống khi ho có đờm\n" .
            "Vitamin C 500mg,Acid ascorbic,LOT-VITC-02,2026-02-15,2027-12-31,10,vỉ,15000,Tủ đầu giường,Uống sau ăn sáng\n";

        $file = UploadedFile::fake()->createWithContent('danh_sach_thuoc_moi.csv', $csvContent);

        $response = $this->actingAs($admin)->post(route('cabinet.import-excel'), [
            'excel_file' => $file,
        ]);

        $response->assertRedirect(route('cabinet.index'));

        $this->assertDatabaseHas('cabinet_items', [
            'family_id' => $family->id,
            'custom_name' => 'Thuốc ho Prospan',
            'quantity' => 3,
            'unit' => 'chai',
        ]);

        $this->assertDatabaseHas('cabinet_items', [
            'family_id' => $family->id,
            'custom_name' => 'Vitamin C 500mg',
            'quantity' => 10,
            'unit' => 'vỉ',
        ]);
    }
}
