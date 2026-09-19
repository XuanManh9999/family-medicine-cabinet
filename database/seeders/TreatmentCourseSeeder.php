<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Medicine;
use App\Models\PrescriptionItem;
use App\Models\TreatmentCourse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TreatmentCourseSeeder extends Seeder
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

        $medMap = Medicine::pluck('id', 'name')->toArray();

        // 1. Đơn thuốc ngoại trú định kỳ cho Ông nội
        if ($grandpa) {
            $course1 = TreatmentCourse::updateOrCreate(
                [
                    'family_id' => $family->id,
                    'family_member_id' => $grandpa->id,
                    'title' => 'Đơn thuốc Huyết áp & Tiểu đường tháng 09/2026',
                ],
                [
                    'hospital_doctor' => 'Bệnh viện Lão khoa Trung ương - PGS.TS Trần Văn Bình',
                    'diagnosis' => 'Tăng huyết áp nguyên phát độ 2, Đái tháo đường type 2 có kiểm soát tốt',
                    'start_date' => Carbon::now()->startOfMonth()->toDateString(),
                    'end_date' => Carbon::now()->endOfMonth()->toDateString(),
                    'prescription_image_path' => 'prescriptions/sample_don_thuoc_ong.jpg',
                    'ai_analysis_data' => [
                        'source' => 'Gemini Vision OCR Engine',
                        'confidence' => 0.98,
                        'hospital' => 'Bệnh viện Lão khoa Trung ương',
                        'extracted_at' => Carbon::now()->startOfMonth()->toIso8601String(),
                        'plain_language_summary' => 'Đơn thuốc duy trì hàng tháng để ổn định huyết áp và lượng đường trong máu cho Ông.',
                        'safety_status' => 'safe',
                        'precautions' => [
                            'Uống Amlodipine đều đặn mỗi sáng, đo huyết áp trước khi uống.',
                            'Uống Glucophage ngay trong hoặc sau bữa ăn để giảm cồn cào ruột.',
                        ],
                    ],
                    'status' => 'dang_dieu_tri',
                ]
            );

            // Thuốc 1: Amlodipine 5mg
            PrescriptionItem::updateOrCreate(
                [
                    'treatment_course_id' => $course1->id,
                    'medicine_name' => 'Amlodipine 5mg',
                ],
                [
                    'medicine_id' => $medMap['Amlodipine 5mg'] ?? null,
                    'dosage' => '1 viên',
                    'frequency' => '1 lần/ngày (Sáng)',
                    'instructions' => 'Uống sau ăn sáng 30 phút cùng 1 cốc nước ấm đầy.',
                    'total_prescribed_quantity' => 30,
                    'unit' => 'viên',
                ]
            );

            // Thuốc 2: Glucophage 850mg
            PrescriptionItem::updateOrCreate(
                [
                    'treatment_course_id' => $course1->id,
                    'medicine_name' => 'Glucophage 850mg',
                ],
                [
                    'medicine_id' => $medMap['Glucophage 850mg'] ?? null,
                    'dosage' => '1 viên',
                    'frequency' => '2 lần/ngày (Sáng - Tối)',
                    'instructions' => 'Uống ngay trong bữa ăn sáng và tối.',
                    'total_prescribed_quantity' => 60,
                    'unit' => 'viên',
                ]
            );
        }

        // 2. Đợt điều trị Viêm Amidan cho Con gái nhỏ
        if ($daughter) {
            $course2 = TreatmentCourse::updateOrCreate(
                [
                    'family_id' => $family->id,
                    'family_member_id' => $daughter->id,
                    'title' => 'Đợt điều trị Viêm amidan cấp có sốt',
                ],
                [
                    'hospital_doctor' => 'Bệnh viện Nhi Trung ương - ThS.BS Lê Thu Trang',
                    'diagnosis' => 'Viêm Amidan mủ cấp tính, Sốt phản ứng 38.8°C',
                    'start_date' => Carbon::now()->subDays(2)->toDateString(),
                    'end_date' => Carbon::now()->addDays(5)->toDateString(),
                    'prescription_image_path' => 'prescriptions/sample_don_thuoc_be.jpg',
                    'ai_analysis_data' => [
                        'source' => 'Gemini Vision OCR Engine',
                        'confidence' => 0.96,
                        'extracted_at' => Carbon::now()->subDays(2)->toIso8601String(),
                        'plain_language_summary' => 'Đơn thuốc kháng sinh 7 ngày kết hợp thuốc tan đờm và hạ sốt khi thân nhiệt trên 38.5 độ.',
                        'safety_status' => 'safe',
                        'precautions' => [
                            'Tuyệt đối không bỏ dở kháng sinh Augmentin khi bé vừa dứt sốt.',
                            'Cho bé uống nhiều nước và vệ sinh mũi họng sạch sẽ.',
                        ],
                    ],
                    'status' => 'dang_dieu_tri',
                ]
            );

            // Thuốc 1: Augmentin 1g
            PrescriptionItem::updateOrCreate(
                [
                    'treatment_course_id' => $course2->id,
                    'medicine_name' => 'Augmentin 1g',
                ],
                [
                    'medicine_id' => $medMap['Augmentin 1g'] ?? null,
                    'dosage' => '1/2 viên',
                    'frequency' => '2 lần/ngày (cách nhau 12 tiếng)',
                    'instructions' => 'Bẻ đôi viên uống vào đầu bữa ăn sáng và tối.',
                    'total_prescribed_quantity' => 7,
                    'unit' => 'viên',
                ]
            );

            // Thuốc 2: Acemuc 200mg
            PrescriptionItem::updateOrCreate(
                [
                    'treatment_course_id' => $course2->id,
                    'medicine_name' => 'Acemuc 200mg',
                ],
                [
                    'medicine_id' => $medMap['Acemuc 200mg'] ?? null,
                    'dosage' => '1 gói',
                    'frequency' => '2 lần/ngày (Trưa - Tối)',
                    'instructions' => 'Hòa vào 30ml nước ấm uống sau bữa ăn.',
                    'total_prescribed_quantity' => 10,
                    'unit' => 'gói',
                ]
            );
        }
    }
}
