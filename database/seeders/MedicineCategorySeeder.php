<?php

namespace Database\Seeders;

use App\Models\MedicineCategory;
use Illuminate\Database\Seeder;

class MedicineCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Kháng sinh & Kháng khuẩn',
                'description' => 'Các loại thuốc kháng sinh điều trị nhiễm khuẩn theo đơn bác sĩ.',
                'is_prescription_required' => true,
            ],
            [
                'name' => 'Giảm đau, Hạ sốt & NSAID',
                'description' => 'Thuốc giảm đau hạ sốt thông thường (Paracetamol) và thuốc kháng viêm không steroid.',
                'is_prescription_required' => false,
            ],
            [
                'name' => 'Tim mạch, Huyết áp & Chuyển hóa',
                'description' => 'Thuốc điều trị tăng huyết áp, rối loạn mỡ máu, tiểu đường và tim mạch.',
                'is_prescription_required' => true,
            ],
            [
                'name' => 'Tiêu hóa, Dạ dày & Men vi sinh',
                'description' => 'Thuốc kháng acid, bảo vệ niêm mạc dạ dày, tiêu chảy, men vi sinh đường ruột.',
                'is_prescription_required' => false,
            ],
            [
                'name' => 'Hô hấp, Cảm cúm & Tan đờm',
                'description' => 'Thuốc trị ho, long đờm, viêm họng, xoang và xịt thông mũi.',
                'is_prescription_required' => false,
            ],
            [
                'name' => 'Chống dị ứng & Da liễu',
                'description' => 'Thuốc kháng histamin trị dị ứng, mề đay, ngứa và kem bôi ngoài da.',
                'is_prescription_required' => false,
            ],
            [
                'name' => 'Vitamin & Thực phẩm chức năng',
                'description' => 'Viên bổ sung vi chất dinh dưỡng, tăng đề kháng, bổ mắt, xương khớp.',
                'is_prescription_required' => false,
            ],
            [
                'name' => 'Sơ cứu & Dụng cụ y tế',
                'description' => 'Băng gạc cá nhân, cồn sát khuẩn, nước muối sinh lý, nhiệt kế, máy đo huyết áp.',
                'is_prescription_required' => false,
            ],
        ];

        foreach ($categories as $cat) {
            MedicineCategory::updateOrCreate(['name' => $cat['name']], $cat);
        }
    }
}
