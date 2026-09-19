<?php

namespace Tests\Feature;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationSchedule;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineExpense;
use App\Models\SmartAlert;
use App\Models\TreatmentCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_family_and_users_relationship(): void
    {
        $family = Family::where('code', 'FAM-VN2026')->first();
        $this->assertNotNull($family);
        $this->assertGreaterThanOrEqual(2, $family->users()->count());

        $admin = $family->users()->where('role', 'family_admin')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isFamilyAdmin());
        $this->assertEquals($family->id, $admin->family->id);
    }

    public function test_family_members_profile_and_relations(): void
    {
        $family = Family::where('code', 'FAM-VN2026')->first();
        $this->assertNotNull($family);

        $members = $family->members;
        $this->assertGreaterThanOrEqual(4, $members->count());

        $grandpa = $members->firstWhere('name', 'Nguyễn Văn Thịnh');
        $this->assertNotNull($grandpa);
        $this->assertEquals('ong_ba', $grandpa->relationship);
        $this->assertStringContainsString('Tăng huyết áp', $grandpa->underlying_diseases);
        $this->assertGreaterThan(70, $grandpa->age);

        $mom = $members->firstWhere('name', 'Trần Thị Hoa');
        $this->assertNotNull($mom);
        $this->assertStringContainsString('NSAID', $mom->allergies);
    }

    public function test_medicines_and_categories(): void
    {
        $categoriesCount = MedicineCategory::count();
        $this->assertGreaterThanOrEqual(8, $categoriesCount);

        $medicinesCount = Medicine::count();
        $this->assertGreaterThanOrEqual(25, $medicinesCount);

        $panadol = Medicine::where('code', 'MED-PAN-01')->first();
        $this->assertNotNull($panadol);
        $this->assertEquals('Panadol Extra', $panadol->name);
        $this->assertEquals('Giảm đau, Hạ sốt & NSAID', $panadol->category->name);
    }

    public function test_cabinet_items_and_scopes(): void
    {
        $family = Family::where('code', 'FAM-VN2026')->first();
        $this->assertNotNull($family);

        // Kiểm tra scopeNearExpiry (thuốc cận date < 30 ngày)
        $nearExpiryItems = CabinetItem::where('family_id', $family->id)->nearExpiry(30)->get();
        $this->assertNotEmpty($nearExpiryItems);
        $this->assertTrue($nearExpiryItems->contains('custom_name', 'Thuốc tan đờm Acemuc (Cận Date)'));

        // Kiểm tra scopeExpired (thuốc đã hết hạn)
        $expiredItems = CabinetItem::where('family_id', $family->id)->expired()->get();
        $this->assertNotEmpty($expiredItems);
        $this->assertTrue($expiredItems->contains('custom_name', 'Kháng sinh Cephalexin (Đã hết hạn)'));

        // Kiểm tra scopeLowStock (số lượng tồn ít <= 5)
        $lowStockItems = CabinetItem::where('family_id', $family->id)->lowStock(5)->get();
        $this->assertNotEmpty($lowStockItems);
    }

    public function test_treatment_courses_and_prescription_items(): void
    {
        $family = Family::where('code', 'FAM-VN2026')->first();
        $course = TreatmentCourse::where('family_id', $family->id)->first();

        $this->assertNotNull($course);
        $this->assertNotEmpty($course->prescriptionItems);
        $this->assertIsArray($course->ai_analysis_data);
        $this->assertEquals('Gemini Vision OCR Engine', $course->ai_analysis_data['source']);
    }

    public function test_medication_schedules_and_logs(): void
    {
        $activeSchedules = MedicationSchedule::activeToday()->get();
        $this->assertNotEmpty($activeSchedules);

        $schedule = $activeSchedules->first();
        $this->assertNotNull($schedule->familyMember);

        $logs = $schedule->logs;
        $this->assertNotNull($logs);
    }

    public function test_medicine_expenses_and_smart_alerts(): void
    {
        $family = Family::where('code', 'FAM-VN2026')->first();

        $expenses = MedicineExpense::where('family_id', $family->id)->get();
        $this->assertNotEmpty($expenses);
        $this->assertStringContainsString('đ', $expenses->first()->formatted_amount);

        $unreadAlerts = SmartAlert::where('family_id', $family->id)->unread()->get();
        $this->assertNotEmpty($unreadAlerts);

        $dangerAlerts = SmartAlert::where('family_id', $family->id)->danger()->get();
        $this->assertNotEmpty($dangerAlerts);
    }
}
