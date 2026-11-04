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

    public function test_doctor_can_view_cabinet_index_with_management_actions(): void
    {
        $doctor = User::where('email', 'bs.minh@medcabinet.vn')->first();
        $response = $this->actingAs($doctor)->get(route('cabinet.index'));

        $response->assertStatus(200);
        $response->assertSee('Tủ thuốc gia đình');
        $response->assertSee('Xuất Excel');
        $response->assertSee('Nhập từ Excel');
        $response->assertSee('Thêm thuốc vào tủ');
    }

    public function test_family_admin_sees_cabinet_in_read_only_mode(): void
    {
        $familyAdmin = User::where('email', 'an.nguyen@gmail.com')->first();
        $response = $this->actingAs($familyAdmin)->get(route('cabinet.index'));

        $response->assertStatus(200);
        $response->assertSee('Xuất Excel');
        $response->assertSee('Chế độ xem & tra cứu', false);
        $response->assertDontSee('Nhập từ Excel');
        $response->assertDontSee('Thêm thuốc vào tủ');
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

    public function test_doctor_can_import_medicines_from_csv(): void
    {
        $admin = User::where('email', 'bs.minh@medcabinet.vn')->first();
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

    private function doctor(): User
    {
        return User::where('email', 'bs.minh@medcabinet.vn')->first();
    }

    private function importCsv(User $user, string $content, string $filename = 'nhap.csv')
    {
        return $this->actingAs($user)->post(route('cabinet.import-excel'), [
            'excel_file' => UploadedFile::fake()->createWithContent($filename, $content),
        ]);
    }

    public function test_exported_csv_can_be_reimported_round_trip(): void
    {
        $doctor = $this->doctor();
        $family = $doctor->family;
        $family->cabinetItems()->delete();

        CabinetItem::create([
            'family_id' => $family->id,
            'custom_name' => '=Thuốc thử vòng lặp',
            'batch_number' => 'RT-01',
            'manufacturing_date' => '2026-01-15',
            'expiry_date' => '2028-06-30',
            'quantity' => 1.5,
            'unit' => 'chai',
            'storage_location' => 'Ngăn 3; tủ bếp',
            'price_per_unit' => 2500.5,
            'notes' => "Dòng 1\nDòng 2, có dấu phẩy \"ngoặc kép\"",
            'status' => 'con_han',
        ]);
        CabinetItem::create([
            'family_id' => $family->id,
            'custom_name' => 'Gạc vô trùng',
            'expiry_date' => '2029-01-31',
            'quantity' => 0,
            'unit' => 'gói',
            'status' => 'het_hang',
        ]);

        $export = $this->actingAs($doctor)->get(route('cabinet.export-excel'));
        $export->assertStatus(200);
        $csv = $export->streamedContent();

        // Chống chèn công thức Excel
        $this->assertStringContainsString("'=Thuốc thử vòng lặp", $csv);

        $family->cabinetItems()->delete();

        $response = $this->importCsv($doctor, $csv, 'tu_thuoc_xuat.csv');
        $response->assertRedirect(route('cabinet.index'));
        $response->assertSessionHas('success');

        $items = $family->cabinetItems()->orderBy('custom_name')->get();
        $this->assertCount(2, $items);

        $first = $items->firstWhere('custom_name', '=Thuốc thử vòng lặp');
        $this->assertNotNull($first);
        $this->assertSame('RT-01', $first->batch_number);
        $this->assertSame('2026-01-15', $first->manufacturing_date->toDateString());
        $this->assertSame('2028-06-30', $first->expiry_date->toDateString());
        $this->assertEquals(1.5, (float) $first->quantity);
        $this->assertSame('chai', $first->unit);
        $this->assertEquals(2500.5, (float) $first->price_per_unit);
        $this->assertSame('Ngăn 3; tủ bếp', $first->storage_location);
        $this->assertSame("Dòng 1\nDòng 2, có dấu phẩy \"ngoặc kép\"", $first->notes);

        $second = $items->firstWhere('custom_name', 'Gạc vô trùng');
        $this->assertSame('2029-01-31', $second->expiry_date->toDateString());
        $this->assertEquals(0, (float) $second->quantity);
        $this->assertNull($second->batch_number);
        $this->assertNull($second->manufacturing_date);
        $this->assertNull($second->price_per_unit);
        $this->assertSame('het_hang', $second->status);
    }

    public function test_import_rejects_overflowing_dates_strictly(): void
    {
        $doctor = $this->doctor();

        $csv = "Tên thuốc,Hạn sử dụng,Ngày sản xuất,Số lượng\n"
            . "Thuốc ngày Mỹ,6/30/2028,,5\n"
            . "Thuốc ngày tràn,31/02/2027,,5\n"
            . "Thuốc NSX sai,2028-01-01,2025-13-01,5\n"
            . "Thuốc hợp lệ,30/06/2028,15/01/2026,5\n";

        $response = $this->importCsv($doctor, $csv);
        $response->assertRedirect(route('cabinet.index'));
        $response->assertSessionHas('warning');
        $this->assertCount(3, session('import_errors'));

        $this->assertDatabaseMissing('cabinet_items', ['custom_name' => 'Thuốc ngày Mỹ']);
        $this->assertDatabaseMissing('cabinet_items', ['custom_name' => 'Thuốc ngày tràn']);
        $this->assertDatabaseMissing('cabinet_items', ['custom_name' => 'Thuốc NSX sai']);

        $valid = CabinetItem::where('custom_name', 'Thuốc hợp lệ')->firstOrFail();
        $this->assertSame('2028-06-30', $valid->expiry_date->toDateString());
        $this->assertSame('2026-01-15', $valid->manufacturing_date->toDateString());
    }

    public function test_import_validates_rows_and_normalises_vietnamese_numbers(): void
    {
        $doctor = $this->doctor();
        $tooLongUnit = str_repeat('x', 31);
        $future = now()->addMonth()->format('d/m/Y');

        $csv = "Tên thuốc;Hạn sử dụng;Ngày sản xuất;Số lượng;Đơn vị tính;Đơn giá\n"
            . "Giá kiểu Việt;31/12/2028;;10;viên;2.500\n"
            . "Giá dấu phẩy;31/12/2028;;1,5;viên;2,500\n"
            . "Số lượng âm;31/12/2028;;-3;viên;1000\n"
            . "Số lượng chữ;31/12/2028;;abc;viên;1000\n"
            . "Số lượng tràn;31/12/2028;;10000000;viên;1000\n"
            . "Giá âm;31/12/2028;;3;viên;-5\n"
            . "HSD trước NSX;01/01/2026;01/06/2026;3;viên;1000\n"
            . "NSX tương lai;31/12/2028;{$future};3;viên;1000\n"
            . "Đơn vị quá dài;31/12/2028;;3;{$tooLongUnit};1000\n"
            . ";31/12/2028;;3;viên;1000\n";

        $response = $this->importCsv($doctor, $csv);
        $response->assertRedirect(route('cabinet.index'));
        $response->assertSessionHas('warning');
        $this->assertCount(8, session('import_errors'));

        $this->assertEquals(2500, (float) CabinetItem::where('custom_name', 'Giá kiểu Việt')->value('price_per_unit'));
        $comma = CabinetItem::where('custom_name', 'Giá dấu phẩy')->firstOrFail();
        $this->assertEquals(1.5, (float) $comma->quantity);
        $this->assertEquals(2500, (float) $comma->price_per_unit);

        foreach (['Số lượng âm', 'Số lượng chữ', 'Số lượng tràn', 'Giá âm', 'HSD trước NSX', 'NSX tương lai', 'Đơn vị quá dài'] as $name) {
            $this->assertDatabaseMissing('cabinet_items', ['custom_name' => $name]);
        }
    }

    public function test_import_supports_utf16_tab_file_and_rejects_binary_or_wrong_extension(): void
    {
        $doctor = $this->doctor();

        $tsv = "Tên thuốc\tHạn sử dụng\tSố lượng\tĐơn vị\r\n\tGhi chú lệch\t\t\r\nSiro ho trẻ em\t2028-05-20\t2\tchai\r\n";
        $utf16 = "\xFF\xFE" . mb_convert_encoding($tsv, 'UTF-16LE', 'UTF-8');

        $this->importCsv($doctor, $utf16, 'unicode.txt')->assertRedirect(route('cabinet.index'));
        $this->assertDatabaseHas('cabinet_items', ['custom_name' => 'Siro ho trẻ em', 'unit' => 'chai', 'quantity' => 2]);
        // Dòng có ô đầu trống không bị "dịch cột": thiếu tên thuốc => bị bỏ qua
        $this->assertDatabaseMissing('cabinet_items', ['custom_name' => '2028-05-20']);
        $this->assertDatabaseMissing('cabinet_items', ['custom_name' => 'Ghi chú lệch']);

        // File CSV lưu bằng bảng mã Windows-1258 (Excel tiếng Việt cũ)
        $win1258 = iconv('UTF-8', 'CP1258', "Tên thuốc,Hạn sử dụng,Số lượng\nThuốc bổ phổi Hải Thượng,2028-05-20,4\n");
        $this->assertNotFalse($win1258);
        $this->importCsv($doctor, $win1258)->assertRedirect(route('cabinet.index'));
        $this->assertDatabaseHas('cabinet_items', ['custom_name' => 'Thuốc bổ phổi Hải Thượng', 'quantity' => 4]);

        $this->importCsv($doctor, "PK\x03\x04\x00\x00binary", 'file.csv')->assertSessionHas('danger');

        $this->actingAs($doctor)
            ->post(route('cabinet.import-excel'), [
                'excel_file' => UploadedFile::fake()->createWithContent('bang.xlsx', "Tên thuốc,Hạn sử dụng,Số lượng\nX,2028-01-01,1\n"),
            ])
            ->assertSessionHasErrors('excel_file');
        $this->assertDatabaseMissing('cabinet_items', ['custom_name' => 'X']);
    }

    public function test_import_links_master_medicine_only_on_exact_name_or_code(): void
    {
        $doctor = $this->doctor();
        $medicine = \App\Models\Medicine::whereNotNull('code')->firstOrFail();

        $csv = "Tên thuốc,Hạn sử dụng,Số lượng\n"
            . "C,2028-01-01,1\n"
            . '"  ' . mb_strtoupper($medicine->name, 'UTF-8') . '  ",2028-01-01,1' . "\n"
            . "{$medicine->code},2028-01-01,1\n";

        $this->importCsv($doctor, $csv)->assertSessionHas('success');

        $this->assertNull(CabinetItem::where('custom_name', 'C')->value('medicine_id'));
        $this->assertEquals($medicine->id, CabinetItem::where('custom_name', mb_strtoupper($medicine->name, 'UTF-8'))->value('medicine_id'));
        $this->assertEquals($medicine->id, CabinetItem::where('custom_name', $medicine->code)->value('medicine_id'));
    }

    public function test_cabinet_changes_refresh_and_resolve_item_alerts(): void
    {
        $doctor = $this->doctor();
        $family = $doctor->family;

        $this->actingAs($doctor)->post(route('cabinet.items.store'), [
            'custom_name' => 'Thuốc nhỏ mắt V.Rohto',
            'quantity' => 2,
            'unit' => 'lọ',
            'expiry_date' => now()->addYear()->toDateString(),
        ])->assertRedirect(route('cabinet.index'));

        $item = CabinetItem::where('custom_name', 'Thuốc nhỏ mắt V.Rohto')->firstOrFail();
        $this->assertDatabaseHas('smart_alerts', ['family_id' => $family->id, 'type' => 'low_stock', 'cabinet_item_id' => $item->id]);

        // Bổ sung hàng => cảnh báo tồn kho thấp được gỡ
        $this->actingAs($doctor)->patch(route('cabinet.items.quantity', $item), ['quantity' => 20]);
        $this->assertDatabaseMissing('smart_alerts', ['type' => 'low_stock', 'cabinet_item_id' => $item->id]);

        // Hết hàng => cảnh báo "đã hết" riêng
        $this->actingAs($doctor)->patch(route('cabinet.items.quantity', $item), ['quantity' => 0]);
        $this->assertDatabaseHas('smart_alerts', ['type' => 'out_of_stock', 'cabinet_item_id' => $item->id]);
        $this->assertSame('het_hang', $item->fresh()->status);

        // Xóa lô => mọi cảnh báo gắn với lô bị gỡ
        $this->actingAs($doctor)->delete(route('cabinet.items.destroy', $item));
        $this->assertDatabaseMissing('smart_alerts', ['cabinet_item_id' => $item->id]);
        $this->assertDatabaseMissing('smart_alerts', ['family_id' => $family->id, 'title' => 'Tủ thuốc đã hết: Thuốc nhỏ mắt V.Rohto']);
    }

    public function test_member_cannot_modify_cabinet_items(): void
    {
        $this->assertCannotModifyCabinet(User::where('email', 'hoa.tran@gmail.com')->first());
    }

    public function test_family_admin_cannot_modify_cabinet_items(): void
    {
        $this->assertCannotModifyCabinet(User::where('email', 'an.nguyen@gmail.com')->first());
    }

    private function assertCannotModifyCabinet(User $member): void
    {
        $item = CabinetItem::where('family_id', $member->family_id)->first();

        $this->actingAs($member)
            ->patch(route('cabinet.items.quantity', $item), ['quantity' => 1])
            ->assertStatus(403);

        // 1. Cannot open create form
        $this->actingAs($member)
            ->get(route('cabinet.items.create'))
            ->assertStatus(403);

        // 2. Cannot store cabinet item
        $this->actingAs($member)
            ->post(route('cabinet.items.store'), [
                'custom_name' => 'Thuốc tạo trái phép',
                'quantity' => 10,
                'unit' => 'viên',
                'expiry_date' => now()->addYear()->toDateString(),
            ])
            ->assertStatus(403);

        if ($item) {
            // 3. Cannot edit cabinet item
            $this->actingAs($member)
                ->get(route('cabinet.items.edit', $item))
                ->assertStatus(403);

            // 4. Cannot update cabinet item
            $this->actingAs($member)
                ->put(route('cabinet.items.update', $item), [
                    'custom_name' => 'Thuốc sửa trái phép',
                    'quantity' => 5,
                    'unit' => 'viên',
                    'expiry_date' => now()->addYear()->toDateString(),
                ])
                ->assertStatus(403);

            // 5. Cannot delete cabinet item
            $this->actingAs($member)
                ->delete(route('cabinet.items.destroy', $item))
                ->assertStatus(403);
        }

        // 6. Cannot import excel
        $file = UploadedFile::fake()->createWithContent('test.csv', 'dummy');
        $this->actingAs($member)
            ->post(route('cabinet.import-excel'), ['excel_file' => $file])
            ->assertStatus(403);
    }
}
