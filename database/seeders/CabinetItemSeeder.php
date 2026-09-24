<?php

namespace Database\Seeders;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\Medicine;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CabinetItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $family = Family::where('code', 'FAM-VN2026')->first();
        if (!$family) {
            return;
        }

        $medMap = Medicine::pluck('id', 'name')->toArray();

        $items = [
            // 1. Thuốc còn hạn dài
            [
                'medicine_name' => 'Panadol Extra',
                'custom_name' => 'Panadol Extra Đỏ Giảm Đau',
                'batch_number' => 'VN-202509-01',
                'manufacturing_date' => Carbon::now()->subMonths(6)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(18)->toDateString(),
                'quantity' => 24,
                'unit' => 'viên',
                'storage_location' => 'Ngăn 1 - Tủ thuốc phòng khách',
                'price_per_unit' => 2500,
                'status' => 'con_han',
                'notes' => 'Mua sẵn dự phòng hạ sốt đau đầu cho người lớn.',
            ],
            [
                'medicine_name' => 'Berberin 100mg',
                'custom_name' => 'Berberin vàng trị tiêu chảy',
                'batch_number' => 'BB-2024-88',
                'manufacturing_date' => Carbon::now()->subMonths(8)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(12)->toDateString(),
                'quantity' => 50,
                'unit' => 'viên',
                'storage_location' => 'Ngăn 1 - Tủ thuốc phòng khách',
                'price_per_unit' => 500,
                'status' => 'con_han',
                'notes' => 'Thuốc thảo dược lành tính khi rối loạn tiêu hóa nhẹ.',
            ],
            [
                'medicine_name' => 'Amlodipine 5mg',
                'custom_name' => 'Thuốc huyết áp của Ông',
                'batch_number' => 'AML-5591',
                'manufacturing_date' => Carbon::now()->subMonths(2)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(20)->toDateString(),
                'quantity' => 60,
                'unit' => 'viên',
                'storage_location' => 'Tủ đầu giường phòng Ông',
                'price_per_unit' => 3500,
                'status' => 'con_han',
                'notes' => 'Đơn thuốc định kỳ của bệnh viện cấp.',
            ],
            [
                'medicine_name' => 'Nexium 40mg',
                'custom_name' => 'Nexium trị dạ dày của Mẹ',
                'batch_number' => 'NEX-8821',
                'manufacturing_date' => Carbon::now()->subMonths(3)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(15)->toDateString(),
                'quantity' => 14,
                'unit' => 'viên',
                'storage_location' => 'Ngăn 1 - Tủ thuốc phòng khách',
                'price_per_unit' => 24000,
                'status' => 'con_han',
                'notes' => 'Uống trước bữa ăn sáng khi đau rát thượng vị.',
            ],
            [
                'medicine_name' => 'Enterogermina 5ml',
                'custom_name' => 'Men vi sinh Enterogermina của Bé',
                'batch_number' => 'ENT-4029',
                'manufacturing_date' => Carbon::now()->subMonths(4)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(8)->toDateString(),
                'quantity' => 10,
                'unit' => 'ống',
                'storage_location' => 'Ngăn mát tủ lạnh',
                'price_per_unit' => 8500,
                'status' => 'con_han',
                'notes' => 'Bảo quản nhiệt độ mát theo khuyến nghị của dược sĩ.',
            ],
            [
                'medicine_name' => 'Salonpas Gel 50g',
                'custom_name' => 'Tuýp Salonpas bôi vai gáy Bố',
                'batch_number' => 'SAL-1299',
                'manufacturing_date' => Carbon::now()->subMonths(5)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(14)->toDateString(),
                'quantity' => 1,
                'unit' => 'tuýp',
                'storage_location' => 'Ngăn 2 - Tủ thuốc phòng khách',
                'price_per_unit' => 45000,
                'status' => 'con_han',
                'notes' => 'Xoa bóp khi mỏi cổ vai gáy.',
            ],
            [
                'medicine_name' => 'Nước muối sinh lý Natri Clorid 0.9%',
                'custom_name' => 'Nước muối súc họng 500ml',
                'batch_number' => 'NAC-9912',
                'manufacturing_date' => Carbon::now()->subMonths(2)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(10)->toDateString(),
                'quantity' => 3,
                'unit' => 'chai',
                'storage_location' => 'Kệ nhà tắm tầng 2',
                'price_per_unit' => 10000,
                'status' => 'con_han',
                'notes' => 'Súc họng sáng tối.',
            ],

            // 2. Thuốc cận date (sắp hết hạn trong vòng 15-25 ngày) -> TEST CẢNH BÁO VÀNG
            [
                'medicine_name' => 'Acemuc 200mg',
                'custom_name' => 'Thuốc tan đờm Acemuc (Cận Date)',
                'batch_number' => 'ACE-1102',
                'manufacturing_date' => Carbon::now()->subYears(2)->toDateString(),
                'expiry_date' => Carbon::now()->addDays(18)->toDateString(), // Còn 18 ngày
                'quantity' => 8,
                'unit' => 'gói',
                'storage_location' => 'Ngăn 2 - Tủ thuốc phòng khách',
                'price_per_unit' => 3000,
                'status' => 'sap_het_han',
                'notes' => 'Hạn sử dụng chỉ còn chưa đầy 3 tuần, cần lưu ý sử dụng sớm hoặc thay mới.',
            ],
            [
                'medicine_name' => 'Decolgen Forte',
                'custom_name' => 'Decolgen trị cảm cúm (Cận Date)',
                'batch_number' => 'DEC-0918',
                'manufacturing_date' => Carbon::now()->subYears(2)->toDateString(),
                'expiry_date' => Carbon::now()->addDays(25)->toDateString(), // Còn 25 ngày
                'quantity' => 6,
                'unit' => 'viên',
                'storage_location' => 'Ngăn 1 - Tủ thuốc phòng khách',
                'price_per_unit' => 2000,
                'status' => 'sap_het_han',
                'notes' => 'Vỉ thuốc mua từ đợt cảm năm ngoái, sắp hết hạn.',
            ],

            // 3. Thuốc đã quá hạn sử dụng -> TEST CẢNH BÁO ĐỎ NGUY HIỂM
            [
                'medicine_name' => 'Cephalexin 500mg',
                'custom_name' => 'Kháng sinh Cephalexin (Đã hết hạn)',
                'batch_number' => 'CEP-2023-01',
                'manufacturing_date' => Carbon::now()->subYears(3)->toDateString(),
                'expiry_date' => Carbon::now()->subDays(20)->toDateString(), // Hết hạn 20 ngày trước
                'quantity' => 10,
                'unit' => 'viên',
                'storage_location' => 'Góc tủ thuốc phòng khách',
                'price_per_unit' => 1500,
                'status' => 'qua_han',
                'notes' => 'CẢNH BÁO: Thuốc đã quá hạn, cần tiêu hủy ngay vào thùng rác y tế, không được uống!',
            ],

            // 4. Thuốc sắp hết số lượng (Low Stock)
            [
                'medicine_name' => 'Băng cá nhân Urgo Waterproof',
                'custom_name' => 'Băng dán Urgo chống nước',
                'batch_number' => 'URG-771',
                'manufacturing_date' => Carbon::now()->subMonths(6)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(24)->toDateString(),
                'quantity' => 2,
                'unit' => 'hộp',
                'storage_location' => 'Ngăn 1 - Tủ thuốc phòng khách',
                'price_per_unit' => 35000,
                'status' => 'con_han',
                'notes' => 'Chỉ còn 2 miếng gạc cuối cùng trong hộp, cần mua bổ sung.',
            ],
            [
                'medicine_name' => 'Efferalgan 500mg',
                'custom_name' => 'Efferalgan sủi hạ sốt 500mg',
                'batch_number' => 'EFF-881',
                'manufacturing_date' => Carbon::now()->subMonths(4)->toDateString(),
                'expiry_date' => Carbon::now()->addMonths(16)->toDateString(),
                'quantity' => 2,
                'unit' => 'viên',
                'storage_location' => 'Ngăn 1 - Tủ thuốc phòng khách',
                'price_per_unit' => 4000,
                'status' => 'con_han',
                'notes' => 'Chỉ còn 2 viên sủi.',
            ],
        ];

        foreach ($items as $item) {
            $medicineId = $medMap[$item['medicine_name']] ?? null;
            CabinetItem::updateOrCreate(
                [
                    'family_id' => $family->id,
                    'custom_name' => $item['custom_name'],
                ],
                [
                    'medicine_id' => $medicineId,
                    'batch_number' => $item['batch_number'],
                    'manufacturing_date' => $item['manufacturing_date'],
                    'expiry_date' => $item['expiry_date'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'storage_location' => $item['storage_location'],
                    'price_per_unit' => $item['price_per_unit'],
                    'status' => $item['status'],
                    'notes' => $item['notes'],
                ]
            );
        }
    }
}
