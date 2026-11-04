<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationLog;
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
        $this->user = User::where('email', 'bs.minh@medcabinet.vn')->first();
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

        $course = TreatmentCourse::where('family_id', $this->family->id)->latest('id')->first();
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

        $course = TreatmentCourse::where('family_id', $this->family->id)->latest('id')->first();
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

    /**
     * Tạo đợt điều trị qua form (store) với 1 thuốc và các cữ uống cho trước
     */
    protected function storeCourse(array $times, array $overrides = [], array $itemOverrides = []): TreatmentCourse
    {
        $payload = array_merge([
            'family_member_id' => $this->daughter->id,
            'title' => 'Đợt điều trị thử đồng bộ lịch',
            'start_date' => Carbon::today()->subDays(3)->toDateString(),
            'end_date' => Carbon::today()->addDays(7)->toDateString(),
            'status' => 'dang_dieu_tri',
            'auto_generate_schedules' => '1',
            'items' => [
                array_merge([
                    'medicine_name' => 'Thuốc thử đồng bộ',
                    'dosage' => '1 viên',
                    'frequency' => '2 lần/ngày',
                    'unit' => 'viên',
                    'meal_timing' => 'sau_an',
                    'times' => $times,
                ], $itemOverrides),
            ],
        ], $overrides);

        $this->actingAs($this->user)->post(route('treatment-courses.store'), $payload)->assertRedirect();

        return TreatmentCourse::where('family_id', $this->family->id)->latest('id')->firstOrFail();
    }

    public function test_editing_course_keeps_existing_logs_and_reconciles_time_slots(): void
    {
        $course = $this->storeCourse(['sang', 'toi', 'chieu']);
        $pItem = $course->prescriptionItems()->sole();
        $schedules = $pItem->schedules()->get()->keyBy('time_of_day');
        $this->assertCount(3, $schedules);

        // Cữ sáng & tối đã có nhật ký; cữ chiều chưa có
        foreach (['sang', 'toi'] as $slot) {
            MedicationLog::create([
                'schedule_id' => $schedules[$slot]->id,
                'family_member_id' => $this->daughter->id,
                'scheduled_time' => Carbon::yesterday()->setTimeFromTimeString($schedules[$slot]->scheduled_time),
                'status' => 'da_uong',
            ]);
        }

        $response = $this->actingAs($this->user)->put(route('treatment-courses.update', $course), [
            'family_member_id' => $this->daughter->id,
            'title' => 'Đợt điều trị thử đồng bộ lịch (sửa)',
            'start_date' => Carbon::today()->subDays(3)->toDateString(),
            'end_date' => Carbon::today()->addDays(10)->toDateString(),
            'status' => 'dang_dieu_tri',
            'auto_generate_schedules' => '1',
            'items' => [
                [
                    'id' => $pItem->id,
                    'medicine_name' => 'Thuốc thử đồng bộ',
                    'dosage' => '2 viên',
                    'unit' => 'viên',
                    'meal_timing' => 'truoc_an',
                    'times' => ['sang', 'trua'],
                ],
            ],
        ]);
        $response->assertRedirect(route('treatment-courses.show', $course));

        // Thuốc được cập nhật tại chỗ, không tạo lại
        $this->assertSame([$pItem->id], $course->prescriptionItems()->pluck('id')->all());
        $this->assertSame(2, MedicationLog::whereIn('schedule_id', $schedules->pluck('id'))->count());

        // Cữ sáng giữ nguyên lịch (cùng id) và cập nhật liều / bữa ăn / ngày kết thúc
        $morning = $schedules['sang']->fresh();
        $this->assertSame($pItem->id, $morning->prescription_item_id);
        $this->assertEquals('2 viên', $morning->dosage);
        $this->assertEquals('truoc_an', $morning->meal_timing);
        $this->assertTrue($morning->is_active);
        $this->assertEquals(Carbon::today()->addDays(10)->toDateString(), $morning->end_date->toDateString());

        // Cữ tối bị bỏ nhưng có nhật ký: ngừng lịch, giữ lịch sử
        $evening = $schedules['toi']->fresh();
        $this->assertNotNull($evening);
        $this->assertFalse($evening->is_active);
        $this->assertNull($evening->prescription_item_id);
        $this->assertEquals(Carbon::yesterday()->toDateString(), $evening->end_date->toDateString());
        $this->assertSame(1, $evening->logs()->count());

        // Cữ chiều bị bỏ và chưa có nhật ký: xóa hẳn; cữ trưa mới được tạo
        $this->assertNull($schedules['chieu']->fresh());
        $this->assertEqualsCanonicalizing(['sang', 'trua'], $pItem->schedules()->pluck('time_of_day')->all());
    }

    public function test_removing_item_with_logs_keeps_adherence_history(): void
    {
        $course = $this->storeCourse(['sang']);
        $oldItem = $course->prescriptionItems()->sole();
        $oldSchedule = $oldItem->schedules()->sole();

        MedicationLog::create([
            'schedule_id' => $oldSchedule->id,
            'family_member_id' => $this->daughter->id,
            'scheduled_time' => Carbon::yesterday()->setTime(7, 30),
            'status' => 'quen_uong',
        ]);

        $this->actingAs($this->user)->put(route('treatment-courses.update', $course), [
            'family_member_id' => $this->daughter->id,
            'title' => 'Đổi thuốc khác',
            'start_date' => Carbon::today()->subDays(3)->toDateString(),
            'status' => 'dang_dieu_tri',
            'auto_generate_schedules' => '1',
            'items' => [
                ['medicine_name' => 'Thuốc thay thế', 'dosage' => '1 viên', 'times' => ['toi']],
            ],
        ])->assertRedirect();

        $this->assertDatabaseMissing('prescription_items', ['id' => $oldItem->id]);
        $oldSchedule->refresh();
        $this->assertFalse($oldSchedule->is_active);
        $this->assertSame(1, $oldSchedule->logs()->count());
        $this->assertSame('Thuốc thay thế', $course->prescriptionItems()->sole()->medicine_name);
    }

    public function test_edit_form_preserves_item_id_times_and_meal_timing(): void
    {
        $course = $this->storeCourse(['trua', 'chieu'], [], ['meal_timing' => 'truoc_an']);
        $pItem = $course->prescriptionItems()->sole();
        $this->assertEquals('truoc_an', $pItem->meal_timing);

        $html = $this->actingAs($this->user)->get(route('treatment-courses.edit', $course))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="items[0][id]" value="' . $pItem->id . '"', $html);
        $this->assertMatchesRegularExpression('/id="time_trua_0"\s*checked/', $html);
        $this->assertMatchesRegularExpression('/id="time_chieu_0"\s*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="time_sang_0"\s*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="time_toi_0"\s*checked/', $html);
        $this->assertMatchesRegularExpression('/name="items\[0\]\[meal_timing\]".*?<option value="truoc_an" selected>/s', $html);
    }

    public function test_auto_generate_off_or_no_times_creates_no_schedules(): void
    {
        $course = $this->storeCourse(['sang', 'toi'], ['auto_generate_schedules' => '0']);
        $this->assertSame(0, $course->schedules()->count());

        // Thuốc dùng khi cần: không chọn cữ nào thì không lập lịch cố định
        $prnCourse = $this->storeCourse([]);
        $this->assertSame(1, $prnCourse->prescriptionItems()->count());
        $this->assertSame(0, $prnCourse->schedules()->count());
    }

    public function test_unevaluated_course_shows_neutral_safety_state(): void
    {
        $course = TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn thuốc chưa thẩm định',
            'start_date' => Carbon::today(),
            'status' => 'dang_dieu_tri',
        ]);

        $this->assertSame('unknown', $course->safety_risk_level);

        $this->actingAs($this->user)->get(route('treatment-courses.show', $course))
            ->assertOk()
            ->assertSee('Chưa thẩm định')
            ->assertDontSee('Đã kiểm tra an toàn');

        // Dữ liệu cũ lưu medicines_involved dạng chuỗi vẫn hiển thị được
        $course->update(['ai_analysis_data' => [
            'has_conflict' => true,
            'risk_level' => 'danger',
            'conflicts' => [['type' => 'allergy_conflict', 'severity' => 'danger', 'medicines_involved' => 'Aspirin 81mg']],
        ]]);

        $this->actingAs($this->user)->get(route('treatment-courses.show', $course))
            ->assertOk()
            ->assertSee('Chống chỉ định do dị ứng: Aspirin 81mg');
    }

    public function test_reactivating_course_only_reactivates_schedules_still_in_effect(): void
    {
        $course = $this->storeCourse(['sang', 'toi']);
        $schedules = $course->schedules()->get()->keyBy('time_of_day');
        $schedules['toi']->update(['end_date' => Carbon::yesterday()]);

        $this->actingAs($this->user)->patch(route('treatment-courses.toggle-status', $course));
        $this->assertSame(0, $course->schedules()->where('is_active', true)->count());

        $this->actingAs($this->user)->patch(route('treatment-courses.toggle-status', $course));
        $this->assertTrue($schedules['sang']->fresh()->is_active);
        $this->assertFalse($schedules['toi']->fresh()->is_active);
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

    public function test_normal_member_cannot_manage_or_delete_treatment_courses(): void
    {
        $member = User::where('email', 'hoa.tran@gmail.com')->first();

        // 1. Cannot access create form
        $this->actingAs($member)
            ->get(route('treatment-courses.create'))
            ->assertStatus(403);

        // 2. Cannot store new treatment course
        $this->actingAs($member)
            ->post(route('treatment-courses.store'), [
                'family_member_id' => $this->grandpa->id,
                'title' => 'Đơn thuốc thành viên tạo trái phép',
                'start_date' => Carbon::today()->toDateString(),
                'status' => 'dang_dieu_tri',
                'items' => [
                    ['medicine_name' => 'Thuốc A', 'dosage' => '1 viên'],
                ],
            ])
            ->assertStatus(403);

        // 3. Cannot delete treatment course
        $course = TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn thuốc thử quyền xóa',
            'start_date' => Carbon::today(),
            'status' => 'dang_dieu_tri',
        ]);

        $this->actingAs($member)
            ->delete(route('treatment-courses.destroy', $course))
            ->assertStatus(403);
    }

    public function test_family_admin_can_view_but_cannot_prescribe_or_delete_treatment_courses(): void
    {
        $familyAdmin = User::where('email', 'an.nguyen@gmail.com')->first();

        $course = TreatmentCourse::create([
            'family_id' => $this->family->id,
            'family_member_id' => $this->grandpa->id,
            'title' => 'Đơn thuốc do bác sĩ kê',
            'start_date' => Carbon::today(),
            'status' => 'dang_dieu_tri',
        ]);

        // 1. Chủ hộ vẫn xem được danh sách và chi tiết đơn thuốc
        $this->actingAs($familyAdmin)
            ->get(route('treatment-courses.index'))
            ->assertStatus(200)
            ->assertDontSee(route('treatment-courses.create'));
        $this->actingAs($familyAdmin)
            ->get(route('treatment-courses.show', $course))
            ->assertStatus(200);

        // 2. Không được kê đơn, sửa, đổi trạng thái hay xóa
        $this->actingAs($familyAdmin)
            ->get(route('treatment-courses.create'))
            ->assertStatus(403);
        $this->actingAs($familyAdmin)
            ->post(route('treatment-courses.store'), [
                'family_member_id' => $this->grandpa->id,
                'title' => 'Đơn thuốc chủ hộ tự kê',
                'start_date' => Carbon::today()->toDateString(),
                'status' => 'dang_dieu_tri',
                'items' => [
                    ['medicine_name' => 'Thuốc A', 'dosage' => '1 viên'],
                ],
            ])
            ->assertStatus(403);
        $this->actingAs($familyAdmin)
            ->patch(route('treatment-courses.toggle-status', $course))
            ->assertStatus(403);
        $this->actingAs($familyAdmin)
            ->delete(route('treatment-courses.destroy', $course))
            ->assertStatus(403);

        $this->assertDatabaseHas('treatment_courses', ['id' => $course->id]);
    }
}
