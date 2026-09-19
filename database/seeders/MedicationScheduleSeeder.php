<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationLog;
use App\Models\MedicationSchedule;
use App\Models\PrescriptionItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MedicationScheduleSeeder extends Seeder
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
        $mom = FamilyMember::where('family_id', $family->id)->where('name', 'Trần Thị Hoa')->first();
        $daughter = FamilyMember::where('family_id', $family->id)->where('name', 'Nguyễn Mai Anh')->first();

        $today = Carbon::today();

        // 1. Lịch của Ông nội: Amlodipine 5mg sáng
        if ($grandpa) {
            $piAml = PrescriptionItem::where('medicine_name', 'Amlodipine 5mg')->first();
            $schAml = MedicationSchedule::updateOrCreate(
                [
                    'family_id' => $family->id,
                    'family_member_id' => $grandpa->id,
                    'medicine_name' => 'Amlodipine 5mg',
                    'time_of_day' => 'sang',
                ],
                [
                    'prescription_item_id' => $piAml?->id,
                    'dosage' => '1 viên',
                    'unit' => 'viên',
                    'scheduled_time' => '07:30:00',
                    'meal_timing' => 'sau_an',
                    'start_date' => $today->copy()->startOfMonth(),
                    'end_date' => $today->copy()->endOfMonth(),
                    'is_active' => true,
                ]
            );

            // Tạo log hôm qua và hôm nay cho Ông
            MedicationLog::updateOrCreate(
                [
                    'schedule_id' => $schAml->id,
                    'family_member_id' => $grandpa->id,
                    'scheduled_time' => $today->copy()->subDay()->setTime(7, 30, 0),
                ],
                [
                    'actual_taken_time' => $today->copy()->subDay()->setTime(7, 45, 0),
                    'status' => 'da_uong',
                    'note' => 'Đã uống sau khi ăn phở sáng.',
                ]
            );

            MedicationLog::updateOrCreate(
                [
                    'schedule_id' => $schAml->id,
                    'family_member_id' => $grandpa->id,
                    'scheduled_time' => $today->copy()->setTime(7, 30, 0),
                ],
                [
                    'actual_taken_time' => $today->copy()->setTime(7, 35, 0),
                    'status' => 'da_uong',
                    'note' => 'Huyết áp sáng nay 125/80 mmHg rất tốt.',
                ]
            );

            // Glucophage tối của Ông
            $piGlu = PrescriptionItem::where('medicine_name', 'Glucophage 850mg')->first();
            $schGlu = MedicationSchedule::updateOrCreate(
                [
                    'family_id' => $family->id,
                    'family_member_id' => $grandpa->id,
                    'medicine_name' => 'Glucophage 850mg',
                    'time_of_day' => 'toi',
                ],
                [
                    'prescription_item_id' => $piGlu?->id,
                    'dosage' => '1 viên',
                    'unit' => 'viên',
                    'scheduled_time' => '19:30:00',
                    'meal_timing' => 'trong_khi_an',
                    'start_date' => $today->copy()->startOfMonth(),
                    'end_date' => $today->copy()->endOfMonth(),
                    'is_active' => true,
                ]
            );

            MedicationLog::updateOrCreate(
                [
                    'schedule_id' => $schGlu->id,
                    'family_member_id' => $grandpa->id,
                    'scheduled_time' => $today->copy()->subDay()->setTime(19, 30, 0),
                ],
                [
                    'actual_taken_time' => $today->copy()->subDay()->setTime(19, 35, 0),
                    'status' => 'da_uong',
                    'note' => 'Uống trong bữa cơm tối.',
                ]
            );
        }

        // 2. Lịch của Con gái nhỏ: Augmentin sáng & tối
        if ($daughter) {
            $piAug = PrescriptionItem::where('medicine_name', 'Augmentin 1g')->first();
            $schAugMorning = MedicationSchedule::updateOrCreate(
                [
                    'family_id' => $family->id,
                    'family_member_id' => $daughter->id,
                    'medicine_name' => 'Augmentin 1g',
                    'time_of_day' => 'sang',
                ],
                [
                    'prescription_item_id' => $piAug?->id,
                    'dosage' => '1/2 viên',
                    'unit' => 'viên',
                    'scheduled_time' => '08:00:00',
                    'meal_timing' => 'trong_khi_an',
                    'start_date' => $today->copy()->subDays(2),
                    'end_date' => $today->copy()->addDays(5),
                    'is_active' => true,
                ]
            );

            MedicationSchedule::updateOrCreate(
                [
                    'family_id' => $family->id,
                    'family_member_id' => $daughter->id,
                    'medicine_name' => 'Augmentin 1g',
                    'time_of_day' => 'toi',
                ],
                [
                    'prescription_item_id' => $piAug?->id,
                    'dosage' => '1/2 viên',
                    'unit' => 'viên',
                    'scheduled_time' => '20:00:00',
                    'meal_timing' => 'trong_khi_an',
                    'start_date' => $today->copy()->subDays(2),
                    'end_date' => $today->copy()->addDays(5),
                    'is_active' => true,
                ]
            );

            // Log uống thuốc của Bé
            MedicationLog::updateOrCreate(
                [
                    'schedule_id' => $schAugMorning->id,
                    'family_member_id' => $daughter->id,
                    'scheduled_time' => $today->copy()->setTime(8, 0, 0),
                ],
                [
                    'actual_taken_time' => $today->copy()->setTime(8, 15, 0),
                    'status' => 'da_uong',
                    'note' => 'Bé ngoan, uống cùng nửa cốc sữa ấm.',
                ]
            );
        }

        // 3. Lịch uống thuốc dạ dày của Mẹ: Nexium sáng trước ăn
        if ($mom) {
            MedicationSchedule::updateOrCreate(
                [
                    'family_id' => $family->id,
                    'family_member_id' => $mom->id,
                    'medicine_name' => 'Nexium 40mg',
                    'time_of_day' => 'sang',
                ],
                [
                    'prescription_item_id' => null,
                    'dosage' => '1 viên',
                    'unit' => 'viên',
                    'scheduled_time' => '06:45:00',
                    'meal_timing' => 'truoc_an',
                    'start_date' => $today->copy()->subDays(10),
                    'end_date' => $today->copy()->addDays(20),
                    'is_active' => true,
                ]
            );
        }
    }
}
