<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicineExpense;
use App\Models\TreatmentCourse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MedicineExpenseSeeder extends Seeder
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

        $grandpa = FamilyMember::where('family_id', $family->id)->where('name', 'Nguyễn Văn Thịnh')->first();
        $daughter = FamilyMember::where('family_id', $family->id)->where('name', 'Nguyễn Mai Anh')->first();

        $courseGrandpa = TreatmentCourse::where('family_id', $family->id)->where('family_member_id', $grandpa?->id)->first();
        $courseDaughter = TreatmentCourse::where('family_id', $family->id)->where('family_member_id', $daughter?->id)->first();

        $expenses = [
            [
                'family_id' => $family->id,
                'treatment_course_id' => $courseGrandpa?->id,
                'family_member_id' => $grandpa?->id,
                'title' => 'Khám chuyên khoa Lão và lấy thuốc huyết áp tiểu đường tháng 9',
                'amount' => 450000,
                'purchase_date' => Carbon::now()->startOfMonth()->toDateString(),
                'receipt_image_path' => 'receipts/hoa_don_bv_lao_khoa.jpg',
                'notes' => 'Bao gồm tiền khám giáo sư và đồng chi trả BHYT thuốc Amlodipine + Glucophage.',
            ],
            [
                'family_id' => $family->id,
                'treatment_course_id' => $courseDaughter?->id,
                'family_member_id' => $daughter?->id,
                'title' => 'Mua thuốc điều trị Viêm Amidan cấp cho Bé tại Nhà thuốc Long Châu',
                'amount' => 285000,
                'purchase_date' => Carbon::now()->subDays(2)->toDateString(),
                'receipt_image_path' => 'receipts/hoa_don_long_chau_amidan.jpg',
                'notes' => 'Augmentin Pháp, gói cốm Acemuc và siro ho thảo dược.',
            ],
            [
                'family_id' => $family->id,
                'treatment_course_id' => null,
                'family_member_id' => null,
                'title' => 'Mua bổ sung hộp sơ cứu tủ thuốc: Cồn đỏ, nước muối, Salonpas',
                'amount' => 85000,
                'purchase_date' => Carbon::now()->subDays(12)->toDateString(),
                'receipt_image_path' => null,
                'notes' => 'Mua lẻ tại tiệm thuốc gần nhà.',
            ],
            [
                'family_id' => $family->id,
                'treatment_course_id' => null,
                'family_member_id' => null,
                'title' => 'Bổ sung Vitamin C Upsa và Berocca tăng đề kháng cả nhà',
                'amount' => 195000,
                'purchase_date' => Carbon::now()->subDays(18)->toDateString(),
                'receipt_image_path' => null,
                'notes' => 'Thời tiết chuyển mùa se lạnh nên mua cho cả nhà dùng.',
            ],
        ];

        foreach ($expenses as $exp) {
            MedicineExpense::updateOrCreate(
                [
                    'family_id' => $exp['family_id'],
                    'title' => $exp['title'],
                ],
                $exp
            );
        }
    }
}
