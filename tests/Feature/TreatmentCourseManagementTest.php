<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationSchedule;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\PrescriptionItem;
use App\Models\TreatmentCourse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TreatmentCourseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Family $family;
    protected FamilyMember $grandpa;
    protected FamilyMember $daughter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->family = Family::where('code', 'FAM-VN2026')->first();
        $this->user = User::where('email', 'an.nguyen@gmail.com')->first();
        $this->grandpa = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Văn Thịnh')->first();
        if ($this->grandpa) {
            $this->grandpa->update(['allergies' => 'Aspirin, NSAID']);
        }
        $this->daughter = FamilyMember::where('family_id', $this->family->id)->where('name', 'Nguyễn Mai Anh')->first();
    }

    public function test_authenticated_user_can_view_treatment_courses_list(): void
    {
        $course = TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn thuốc Huyết áp tháng 09/2026',
            'hospital_doctor' => 'Bệnh viện Bạch Mai - BS. Tuấn',
            'diagnosis' => 'Tăng huyết áp độ 2',
            'start_date' => Carbon::today(),
            'status' => 'dang_dieu_tri',
        ]);

        $response = $this->actingAs($this->user)->get(route('treatment-courses.index'));

        $response->assertStatus(200);
        $response->assertSee('Đơn thuốc &amp; Đợt điều trị ngoại trú', false);
        $response->assertSee('Đơn thuốc Huyết áp tháng 09/2026');
        $response->assertSee('Nguyễn Văn Thịnh');
        $response->assertSee('Bệnh viện Bạch Mai');
    }

    public function test_treatment_courses_can_be_filtered_by_member_and_status(): void
    {
        TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn thuốc của Ông',
            'start_date' => Carbon::today(),
            'status' => 'dang_dieu_tri',
        ]);

        TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->daughter->id,
            'title' => 'Đơn thuốc của Con gái',
            'start_date' => Carbon::today(),
            'status' => 'da_hoan_thanh',
        ]);

        // Filter by grandpa
        $resGrandpa = $this->actingAs($this->user)->get(route('treatment-courses.index', ['member_id' => $this->grandpa->id]));
        $resGrandpa->assertStatus(200);
        $resGrandpa->assertSee('Đơn thuốc của Ông');
        $resGrandpa->assertDontSee('Đơn thuốc của Con gái');

        // Filter by status completed
        $resCompleted = $this->actingAs($this->user)->get(route('treatment-courses.index', ['status' => 'da_hoan_thanh']));
        $resCompleted->assertStatus(200);
        $resCompleted->assertSee('Đơn thuốc của Con gái');
        $resCompleted->assertDontSee('Đơn thuốc của Ông');
    }

    public function test_user_can_view_create_form_with_preselected_member(): void
    {
        $response = $this->actingAs($this->user)->get(route('treatment-courses.create', ['member_id' => $this->daughter->id]));

        $response->assertStatus(200);
        $response->assertSee('Kê đơn thuốc &amp; Lập đợt điều trị mới', false);
        $response->assertSee('Nguyễn Mai Anh');
    }

    public function test_user_can_create_treatment_course_with_items_and_auto_schedules(): void
    {
        Storage::fake('public');

        $category = MedicineCategory::firstOrCreate(
            ['name' => 'Thuốc kháng sinh'],
            ['is_prescription_required' => true]
        );

        $medicine = Medicine::firstOrCreate(
            ['name' => 'Augmentin 1g'],
            [
                'category_id' => $category->id,
                'code' => 'AUG1G',
                'active_ingredient' => 'Amoxicillin + Clavulanic Acid',
                'concentration' => '1000mg',
                'dosage_form' => 'Viên nén',
                'default_unit' => 'viên',
            ]
        );

        $payload = [
            'family_member_id' => $this->daughter->id,
            'title' => 'Đợt điều trị Viêm amidan cấp',
            'hospital_doctor' => 'Bệnh viện Nhi Trung ương - BS. Trang',
            'diagnosis' => 'Viêm amidan hốc mủ, sốt cao',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(7)->toDateString(),
            'status' => 'dang_dieu_tri',
            'auto_generate_schedules' => '1',
            'items' => [
                [
                    'medicine_name' => 'Augmentin 1g',
                    'medicine_id' => $medicine->id,
                    'dosage' => '1 viên',
                    'frequency' => '2 lần/ngày sau khi ăn no',
                    'instructions' => 'Uống nhiều nước ấm',
                    'total_prescribed_quantity' => 14,
                    'unit' => 'viên',
                    'meal_timing' => 'sau_an',
                    'times' => ['sang', 'toi'],
                ],
            ],
            'prescription_image' => UploadedFile::fake()->create('toa_thuoc.jpg', 100, 'image/jpeg'),
        ];

        $response = $this->actingAs($this->user)->post(route('treatment-courses.store'), $payload);

        $course = TreatmentCourse::where('family_id', $this->family->id)->latest()->first();
        $this->assertNotNull($course);
        $response->assertRedirect(route('treatment-courses.show', $course));

        $this->assertEquals('Đợt điều trị Viêm amidan cấp', $course->title);
        $this->assertNotNull($course->prescription_image_path);
        Storage::disk('public')->assertExists($course->prescription_image_path);

        // Kiểm tra PrescriptionItem
        $this->assertCount(1, $course->prescriptionItems);
        $pItem = $course->prescriptionItems->first();
        $this->assertEquals('Augmentin 1g', $pItem->medicine_name);
        $this->assertEquals(14, $pItem->total_prescribed_quantity);

        // Kiểm tra Lịch uống thuốc tự động đồng bộ (sang + toi = 2 schedules)
        $schedules = MedicationSchedule::where('prescription_item_id', $pItem->id)->get();
        $this->assertCount(2, $schedules);
        $this->assertTrue($schedules->contains('time_of_day', 'sang'));
        $this->assertTrue($schedules->contains('time_of_day', 'toi'));
    }

    public function test_treatment_course_detects_drug_allergy_conflicts(): void
    {
        // Ông Thịnh bị dị ứng Aspirin/NSAID
        $payload = [
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn giảm đau hạ sốt',
            'hospital_doctor' => 'Phòng khám đa khoa',
            'diagnosis' => 'Đau nhức xương khớp',
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'dang_dieu_tri',
            'items' => [
                [
                    'medicine_name' => 'Aspirin 500mg',
                    'dosage' => '1 viên',
                    'frequency' => 'Khi đau nhiều',
                    'unit' => 'viên',
                    'times' => ['sang'],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('treatment-courses.store'), $payload);

        $course = TreatmentCourse::where('family_id', $this->family->id)->latest()->first();
        $this->assertNotNull($course);

        // AI Rule Engine phát hiện dị ứng NSAID/Aspirin
        $this->assertNotNull($course->ai_analysis_data);
        $this->assertTrue($course->ai_analysis_data['has_conflict']);
        $this->assertEquals('danger', $course->ai_analysis_data['risk_level']);
        $this->assertEquals('allergy_conflict', $course->ai_analysis_data['conflicts'][0]['type']);

        $response->assertSessionHas('warning');
    }

    public function test_user_can_view_treatment_course_detail(): void
    {
        $course = TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn thuốc Tiểu đường',
            'hospital_doctor' => 'Bệnh viện Lão khoa Trung ương',
            'diagnosis' => 'Đái tháo đường type 2',
            'start_date' => Carbon::today(),
            'status' => 'dang_dieu_tri',
            'ai_analysis_data' => [
                'has_conflict' => false,
                'risk_level' => 'safe',
                'summary' => 'Đơn thuốc an toàn.',
            ],
        ]);

        PrescriptionItem::create([
            'treatment_course_id' => $course->id,
            'medicine_name' => 'Glucophage 850mg',
            'dosage' => '1 viên',
            'frequency' => '2 lần/ngày',
            'unit' => 'viên',
            'total_prescribed_quantity' => 60,
        ]);

        $response = $this->actingAs($this->user)->get(route('treatment-courses.show', $course));

        $response->assertStatus(200);
        $response->assertSee('Đơn thuốc Tiểu đường');
        $response->assertSee('Glucophage 850mg');
        $response->assertSee('Bệnh viện Lão khoa Trung ương');
        $response->assertSee('Đã kiểm tra an toàn');
    }

    public function test_user_can_update_treatment_course(): void
    {
        $course = TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn thuốc cũ',
            'start_date' => Carbon::today(),
            'status' => 'dang_dieu_tri',
        ]);

        $payload = [
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn thuốc đã chỉnh sửa',
            'hospital_doctor' => 'BS. Tuấn Anh',
            'diagnosis' => 'Chẩn đoán mới',
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'da_hoan_thanh',
            'items' => [
                [
                    'medicine_name' => 'Panadol 500mg',
                    'dosage' => '1 viên',
                    'frequency' => 'Khi sốt',
                    'unit' => 'viên',
                    'times' => ['sang'],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->put(route('treatment-courses.update', $course), $payload);

        $response->assertRedirect(route('treatment-courses.show', $course));
        $course->refresh();
        $this->assertEquals('Đơn thuốc đã chỉnh sửa', $course->title);
        $this->assertEquals('da_hoan_thanh', $course->status);
    }

    public function test_user_can_toggle_treatment_course_status(): void
    {
        $course = TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn thuốc điều trị',
            'start_date' => Carbon::today(),
            'status' => 'dang_dieu_tri',
        ]);

        $pItem = PrescriptionItem::create([
            'treatment_course_id' => $course->id,
            'medicine_name' => 'Amlodipine 5mg',
            'dosage' => '1 viên',
            'frequency' => 'Sáng',
        ]);

        $schedule = MedicationSchedule::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'prescription_item_id' => $pItem->id,
            'medicine_name' => 'Amlodipine 5mg',
            'dosage' => '1 viên',
            'time_of_day' => 'sang',
            'scheduled_time' => '07:30:00',
            'start_date' => Carbon::today(),
            'is_active' => true,
        ]);

        // Toggle to completed
        $response = $this->actingAs($this->user)->patch(route('treatment-courses.toggle-status', $course));
        $response->assertRedirect();

        $course->refresh();
        $schedule->refresh();
        $this->assertEquals('da_hoan_thanh', $course->status);
        $this->assertFalse($schedule->is_active);

        // Toggle back to active
        $this->actingAs($this->user)->patch(route('treatment-courses.toggle-status', $course));
        $course->refresh();
        $schedule->refresh();
        $this->assertEquals('dang_dieu_tri', $course->status);
        $this->assertTrue($schedule->is_active);
    }

    public function test_user_can_delete_treatment_course(): void
    {
        $course = TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn thuốc cần xóa',
            'start_date' => Carbon::today(),
            'status' => 'dang_dieu_tri',
        ]);

        $pItem = PrescriptionItem::create([
            'treatment_course_id' => $course->id,
            'medicine_name' => 'Thuốc xóa',
            'dosage' => '1 viên',
            'frequency' => 'Sáng',
        ]);

        $response = $this->actingAs($this->user)->delete(route('treatment-courses.destroy', $course));

        $response->assertRedirect(route('treatment-courses.index'));
        $this->assertDatabaseMissing('treatment_courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('prescription_items', ['id' => $pItem->id]);
    }

    public function test_user_cannot_access_other_family_treatment_courses(): void
    {
        $otherFamily = Family::create([
            'name' => 'Gia đình khác',
            'code' => 'FAM-OTHER-' . uniqid(),
        ]);

        $otherMember = FamilyMember::create([
            'family_id' => $otherFamily->id,
            'name' => 'Người lạ',
            'relationship' => 'ban_than',
        ]);

        $otherCourse = TreatmentCourse::create([
            'family_id' => $otherFamily->id,
            'family_member_id' => $otherMember->id,
            'title' => 'Đơn thuốc gia đình khác',
            'start_date' => Carbon::today(),
            'status' => 'dang_dieu_tri',
        ]);

        $response = $this->actingAs($this->user)->get(route('treatment-courses.show', $otherCourse));
        $response->assertStatus(403);
    }

    public function test_ai_explainer_endpoint_returns_json(): void
    {
        $course = TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn thuốc Viêm xoang',
            'start_date' => Carbon::today(),
            'status' => 'dang_dieu_tri',
        ]);

        $response = $this->actingAs($this->user)->post(route('treatment-courses.explain-ai', $course));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'explanation',
        ]);
    }
}
