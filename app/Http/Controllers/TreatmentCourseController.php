<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationSchedule;
use App\Models\Medicine;
use App\Models\PrescriptionItem;
use App\Models\TreatmentCourse;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TreatmentCourseController extends Controller
{
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Danh sách các đợt điều trị ngoại trú & đơn thuốc của cả gia đình
     */
    public function index(Request $request)
    {
        $family = $this->ensureFamily($request);

        $query = $family->treatmentCourses()
            ->with([
                'familyMember',
                'prescriptionItems.medicine',
                'prescriptionItems.schedules',
            ]);

        // Lọc theo thành viên gia đình
        if ($memberId = $request->query('member_id')) {
            $query->where('family_member_id', $memberId);
        }

        // Lọc theo trạng thái
        $status = $request->query('status', 'all');
        if (in_array($status, ['dang_dieu_tri', 'da_hoan_thanh', 'tam_dung'])) {
            $query->where('status', $status);
        }

        // Tìm kiếm theo tên đợt / chẩn đoán / bệnh viện bác sĩ
        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('diagnosis', 'like', "%{$search}%")
                  ->orWhere('hospital_doctor', 'like', "%{$search}%")
                  ->orWhereHas('prescriptionItems', function ($piQuery) use ($search) {
                      $piQuery->where('medicine_name', 'like', "%{$search}%");
                  });
            });
        }

        $treatmentCourses = $query->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        // Thống kê nhanh toàn gia đình
        $allCourses = $family->treatmentCourses;
        $stats = [
            'total' => $allCourses->count(),
            'active' => $allCourses->where('status', 'dang_dieu_tri')->count(),
            'completed' => $allCourses->where('status', 'da_hoan_thanh')->count(),
            'active_members' => $allCourses->where('status', 'dang_dieu_tri')->pluck('family_member_id')->unique()->count(),
        ];

        $members = $family->members()->orderBy('name')->get();

        return view('treatment-courses.index', compact(
            'treatmentCourses',
            'stats',
            'members',
            'memberId',
            'status'
        ));
    }

    /**
     * Form kê đơn thuốc / lập đợt điều trị mới
     */
    public function create(Request $request)
    {
        $family = $this->ensureFamily($request);
        $members = $family->members()->orderBy('name')->get();

        if ($members->isEmpty()) {
            return redirect()->route('members.create')
                ->with('warning', 'Vui lòng tạo hồ sơ thành viên gia đình trước khi lập đợt điều trị!');
        }

        $preselectedMemberId = $request->query('member_id');
        $availableMedicines = Medicine::orderBy('name')->take(50)->get(['id', 'name', 'active_ingredient', 'concentration', 'default_unit']);
        $cabinetItems = $family->cabinetItems()->with('medicine')->where('quantity', '>', 0)->get();

        $treatmentCourse = new TreatmentCourse([
            'start_date' => Carbon::today(),
            'end_date' => Carbon::today()->addDays(7),
            'status' => 'dang_dieu_tri',
        ]);

        return view('treatment-courses.form', [
            'treatmentCourse' => $treatmentCourse,
            'editing' => false,
            'members' => $members,
            'preselectedMemberId' => $preselectedMemberId,
            'availableMedicines' => $availableMedicines,
            'cabinetItems' => $cabinetItems,
        ]);
    }

    /**
     * Lưu đợt điều trị & đơn thuốc, kiểm tra tương tác thuốc AI và sinh lịch uống
     */
    public function store(Request $request)
    {
        $family = $this->ensureFamily($request);

        $validated = $this->validateTreatmentCourse($request, $family->id);

        $member = FamilyMember::where('family_id', $family->id)
            ->findOrFail($validated['family_member_id']);

        // Upload ảnh đơn thuốc nếu có
        $imagePath = null;
        if ($request->hasFile('prescription_image')) {
            $imagePath = $request->file('prescription_image')->store('prescriptions', 'public');
        }

        // Kiểm tra tương tác thuốc & dị ứng y khoa qua AI Rule Engine / Gemini
        $medicinesForAi = [];
        $itemsData = $request->input('items', []);

        foreach ($itemsData as $item) {
            $medName = trim($item['medicine_name'] ?? '');
            if (empty($medName)) continue;

            $activeIng = '';
            if (!empty($item['medicine_id'])) {
                $med = Medicine::find($item['medicine_id']);
                $activeIng = $med?->active_ingredient ?? '';
            }

            $medicinesForAi[] = [
                'name' => $medName,
                'active_ingredient' => $activeIng,
                'dosage' => $item['dosage'] ?? '',
                'frequency' => $item['frequency'] ?? '',
            ];
        }

        $patientHealthProfile = [
            'name' => $member->name,
            'age' => $member->age,
            'allergies' => $member->allergies ?? '',
            'underlying_diseases' => $member->underlying_diseases ?? '',
        ];

        $aiAnalysis = $this->geminiService->checkDrugInteractions($medicinesForAi, $patientHealthProfile);
        $aiAnalysis['evaluated_at'] = Carbon::now()->toIso8601String();
        $aiAnalysis['checked_items_count'] = count($medicinesForAi);

        DB::beginTransaction();
        try {
            $treatmentCourse = TreatmentCourse::create([
                'family_id' => $family->id,
                'family_member_id' => $member->id,
                'title' => $validated['title'],
                'hospital_doctor' => $validated['hospital_doctor'] ?? null,
                'diagnosis' => $validated['diagnosis'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'prescription_image_path' => $imagePath,
                'ai_analysis_data' => $aiAnalysis,
                'status' => $validated['status'] ?? 'dang_dieu_tri',
            ]);

            $autoGenerate = $request->boolean('auto_generate_schedules', true);
            $timeSlotMap = [
                'sang' => '07:30:00',
                'trua' => '12:00:00',
                'chieu' => '17:30:00',
                'toi' => '20:00:00',
            ];

            foreach ($itemsData as $item) {
                $medName = trim($item['medicine_name'] ?? '');
                if (empty($medName)) continue;

                $pItem = PrescriptionItem::create([
                    'treatment_course_id' => $treatmentCourse->id,
                    'medicine_id' => !empty($item['medicine_id']) ? $item['medicine_id'] : null,
                    'medicine_name' => $medName,
                    'dosage' => $item['dosage'] ?? '1 viên',
                    'frequency' => $item['frequency'] ?? 'Ngày uống theo chỉ định',
                    'instructions' => $item['instructions'] ?? null,
                    'total_prescribed_quantity' => floatval($item['total_prescribed_quantity'] ?? 0),
                    'unit' => $item['unit'] ?? 'viên',
                ]);

                // Sinh lịch uống tự động nếu được chọn
                if ($autoGenerate) {
                    $selectedTimes = $item['times'] ?? [];
                    // Nếu người dùng không check giờ cụ thể nhưng có ghi tần suất, fallback sang sáng & tối
                    if (empty($selectedTimes)) {
                        $selectedTimes = ['sang', 'toi'];
                    }

                    foreach ($selectedTimes as $timeKey) {
                        if (isset($timeSlotMap[$timeKey])) {
                            MedicationSchedule::create([
                                'family_id' => $family->id,
                                'family_member_id' => $member->id,
                                'prescription_item_id' => $pItem->id,
                                'medicine_name' => $pItem->medicine_name,
                                'dosage' => $pItem->dosage,
                                'unit' => $pItem->unit,
                                'time_of_day' => $timeKey,
                                'scheduled_time' => $timeSlotMap[$timeKey],
                                'meal_timing' => $item['meal_timing'] ?? 'sau_an',
                                'start_date' => $treatmentCourse->start_date,
                                'end_date' => $treatmentCourse->end_date,
                                'is_active' => ($treatmentCourse->status === 'dang_dieu_tri'),
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            $msg = "Đã lưu đợt điều trị thành công cho bệnh nhân {$member->name}!";
            if (!empty($aiAnalysis['has_conflict'])) {
                return redirect()->route('treatment-courses.show', $treatmentCourse)
                    ->with('warning', $msg . ' LƯU Ý: AI đã phát hiện cảnh báo tương tác/dị ứng thuốc trong đơn này!');
            }

            return redirect()->route('treatment-courses.show', $treatmentCourse)
                ->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('warning', 'Lỗi lưu đợt điều trị: ' . $e->getMessage());
        }
    }

    /**
     * Chi tiết đơn thuốc, kết quả thẩm định tương tác AI, lịch uống và vỏ toa
     */
    public function show(Request $request, TreatmentCourse $treatmentCourse)
    {
        $this->authorizeCourse($request, $treatmentCourse);

        $treatmentCourse->load([
            'familyMember',
            'prescriptionItems.medicine',
            'prescriptionItems.schedules.logs',
            'expenses',
        ]);

        return view('treatment-courses.show', compact('treatmentCourse'));
    }

    /**
     * Form chỉnh sửa đợt điều trị
     */
    public function edit(Request $request, TreatmentCourse $treatmentCourse)
    {
        $this->authorizeCourse($request, $treatmentCourse);

        $family = $request->user()->family;
        $members = $family->members()->orderBy('name')->get();
        $availableMedicines = Medicine::orderBy('name')->take(50)->get(['id', 'name', 'active_ingredient', 'concentration', 'default_unit']);
        $cabinetItems = $family->cabinetItems()->with('medicine')->where('quantity', '>', 0)->get();

        $treatmentCourse->load('prescriptionItems.schedules');

        return view('treatment-courses.form', [
            'treatmentCourse' => $treatmentCourse,
            'editing' => true,
            'members' => $members,
            'preselectedMemberId' => $treatmentCourse->family_member_id,
            'availableMedicines' => $availableMedicines,
            'cabinetItems' => $cabinetItems,
        ]);
    }

    /**
     * Cập nhật đợt điều trị & đơn thuốc
     */
    public function update(Request $request, TreatmentCourse $treatmentCourse)
    {
        $this->authorizeCourse($request, $treatmentCourse);
        $family = $request->user()->family;

        $validated = $this->validateTreatmentCourse($request, $family->id);

        $member = FamilyMember::where('family_id', $family->id)
            ->findOrFail($validated['family_member_id']);

        if ($request->hasFile('prescription_image')) {
            if ($treatmentCourse->prescription_image_path) {
                Storage::disk('public')->delete($treatmentCourse->prescription_image_path);
            }
            $treatmentCourse->prescription_image_path = $request->file('prescription_image')->store('prescriptions', 'public');
        }

        // Tái thẩm định an toàn tương tác thuốc
        $medicinesForAi = [];
        $itemsData = $request->input('items', []);

        foreach ($itemsData as $item) {
            $medName = trim($item['medicine_name'] ?? '');
            if (empty($medName)) continue;

            $activeIng = '';
            if (!empty($item['medicine_id'])) {
                $med = Medicine::find($item['medicine_id']);
                $activeIng = $med?->active_ingredient ?? '';
            }

            $medicinesForAi[] = [
                'name' => $medName,
                'active_ingredient' => $activeIng,
                'dosage' => $item['dosage'] ?? '',
                'frequency' => $item['frequency'] ?? '',
            ];
        }

        $patientHealthProfile = [
            'name' => $member->name,
            'age' => $member->age,
            'allergies' => $member->allergies ?? '',
            'underlying_diseases' => $member->underlying_diseases ?? '',
        ];

        $aiAnalysis = $this->geminiService->checkDrugInteractions($medicinesForAi, $patientHealthProfile);
        $aiAnalysis['evaluated_at'] = Carbon::now()->toIso8601String();
        $aiAnalysis['checked_items_count'] = count($medicinesForAi);

        DB::beginTransaction();
        try {
            $treatmentCourse->update([
                'family_member_id' => $member->id,
                'title' => $validated['title'],
                'hospital_doctor' => $validated['hospital_doctor'] ?? null,
                'diagnosis' => $validated['diagnosis'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'ai_analysis_data' => $aiAnalysis,
                'status' => $validated['status'] ?? 'dang_dieu_tri',
            ]);

            // Cập nhật lại danh sách thuốc: xóa lịch cũ của đợt này và tạo lại
            foreach ($treatmentCourse->prescriptionItems as $oldItem) {
                $oldItem->schedules()->delete();
                $oldItem->delete();
            }

            $autoGenerate = $request->boolean('auto_generate_schedules', true);
            $timeSlotMap = [
                'sang' => '07:30:00',
                'trua' => '12:00:00',
                'chieu' => '17:30:00',
                'toi' => '20:00:00',
            ];

            foreach ($itemsData as $item) {
                $medName = trim($item['medicine_name'] ?? '');
                if (empty($medName)) continue;

                $pItem = PrescriptionItem::create([
                    'treatment_course_id' => $treatmentCourse->id,
                    'medicine_id' => !empty($item['medicine_id']) ? $item['medicine_id'] : null,
                    'medicine_name' => $medName,
                    'dosage' => $item['dosage'] ?? '1 viên',
                    'frequency' => $item['frequency'] ?? 'Ngày uống theo chỉ định',
                    'instructions' => $item['instructions'] ?? null,
                    'total_prescribed_quantity' => floatval($item['total_prescribed_quantity'] ?? 0),
                    'unit' => $item['unit'] ?? 'viên',
                ]);

                if ($autoGenerate) {
                    $selectedTimes = $item['times'] ?? [];
                    if (empty($selectedTimes)) {
                        $selectedTimes = ['sang', 'toi'];
                    }

                    foreach ($selectedTimes as $timeKey) {
                        if (isset($timeSlotMap[$timeKey])) {
                            MedicationSchedule::create([
                                'family_id' => $family->id,
                                'family_member_id' => $member->id,
                                'prescription_item_id' => $pItem->id,
                                'medicine_name' => $pItem->medicine_name,
                                'dosage' => $pItem->dosage,
                                'unit' => $pItem->unit,
                                'time_of_day' => $timeKey,
                                'scheduled_time' => $timeSlotMap[$timeKey],
                                'meal_timing' => $item['meal_timing'] ?? 'sau_an',
                                'start_date' => $treatmentCourse->start_date,
                                'end_date' => $treatmentCourse->end_date,
                                'is_active' => ($treatmentCourse->status === 'dang_dieu_tri'),
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return redirect()->route('treatment-courses.show', $treatmentCourse)
                ->with('success', 'Đã cập nhật đợt điều trị và đơn thuốc thành công!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('warning', 'Lỗi cập nhật: ' . $e->getMessage());
        }
    }

    /**
     * Bật/Tắt nhanh trạng thái Đang điều trị <-> Đã hoàn thành
     */
    public function toggleStatus(Request $request, TreatmentCourse $treatmentCourse)
    {
        $this->authorizeCourse($request, $treatmentCourse);

        $newStatus = ($treatmentCourse->status === 'dang_dieu_tri') ? 'da_hoan_thanh' : 'dang_dieu_tri';
        $isActive = ($newStatus === 'dang_dieu_tri');

        DB::transaction(function () use ($treatmentCourse, $newStatus, $isActive) {
            $treatmentCourse->update(['status' => $newStatus]);
            // Cập nhật trạng thái các lịch uống liên quan
            foreach ($treatmentCourse->prescriptionItems as $pItem) {
                $pItem->schedules()->update(['is_active' => $isActive]);
            }
        });

        $statusText = $newStatus === 'da_hoan_thanh' ? 'Đã hoàn thành' : 'Đang điều trị';
        return back()->with('success', "Đã chuyển trạng thái đợt điều trị sang: {$statusText}!");
    }

    /**
     * Xóa đợt điều trị và các đơn thuốc, lịch uống liên quan
     */
    public function destroy(Request $request, TreatmentCourse $treatmentCourse)
    {
        $this->authorizeCourse($request, $treatmentCourse);

        DB::transaction(function () use ($treatmentCourse) {
            if ($treatmentCourse->prescription_image_path) {
                Storage::disk('public')->delete($treatmentCourse->prescription_image_path);
            }

            foreach ($treatmentCourse->prescriptionItems as $pItem) {
                $pItem->schedules()->delete();
                $pItem->delete();
            }

            $treatmentCourse->delete();
        });

        return redirect()->route('treatment-courses.index')
            ->with('success', 'Đã xóa đợt điều trị thành công!');
    }

    /**
     * Trợ lý AI giải thích bình dân đơn thuốc cho gia đình
     */
    public function explainAi(Request $request, TreatmentCourse $treatmentCourse)
    {
        $this->authorizeCourse($request, $treatmentCourse);

        $treatmentCourse->load(['familyMember', 'prescriptionItems']);

        $prescriptionData = [
            'title' => $treatmentCourse->title,
            'diagnosis' => $treatmentCourse->diagnosis,
            'hospital_doctor' => $treatmentCourse->hospital_doctor,
            'medicines' => $treatmentCourse->prescriptionItems->map(fn ($item) => [
                'name' => $item->medicine_name,
                'dosage' => $item->dosage,
                'frequency' => $item->frequency,
                'instructions' => $item->instructions,
            ])->toArray(),
        ];

        $patientProfile = [
            'name' => $treatmentCourse->familyMember->name,
            'age' => $treatmentCourse->familyMember->age,
            'allergies' => $treatmentCourse->familyMember->allergies,
            'underlying_diseases' => $treatmentCourse->familyMember->underlying_diseases,
        ];

        $explanation = $this->geminiService->explainPrescription($prescriptionData, $patientProfile);

        return response()->json([
            'success' => true,
            'explanation' => $explanation,
        ]);
    }

    /**
     * Kiểm tra và lấy gia đình hiện tại của người dùng
     */
    protected function ensureFamily(Request $request): Family
    {
        $family = $request->user()->family;
        if (!$family) {
            abort(403, 'Bạn chưa thuộc về gia đình nào.');
        }
        return $family;
    }

    /**
     * Kiểm tra quyền sở hữu đợt điều trị
     */
    protected function authorizeCourse(Request $request, TreatmentCourse $course): void
    {
        $family = $this->ensureFamily($request);
        if ($course->family_id !== $family->id) {
            abort(403, 'Bạn không có quyền truy cập đợt điều trị này.');
        }
    }

    /**
     * Validate dữ liệu đầu vào của đợt điều trị
     */
    protected function validateTreatmentCourse(Request $request, int $familyId): array
    {
        return $request->validate([
            'family_member_id' => "required|exists:family_members,id,family_id,{$familyId}",
            'title' => 'required|string|max:255',
            'hospital_doctor' => 'nullable|string|max:255',
            'diagnosis' => 'nullable|string|max:1000',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:dang_dieu_tri,da_hoan_thanh,tam_dung',
            'prescription_image' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:10240',
            'items' => 'required|array|min:1',
            'items.*.medicine_name' => 'required|string|max:255',
            'items.*.medicine_id' => 'nullable|exists:medicines,id',
            'items.*.dosage' => 'required|string|max:100',
            'items.*.frequency' => 'nullable|string|max:255',
            'items.*.instructions' => 'nullable|string|max:500',
            'items.*.total_prescribed_quantity' => 'nullable|numeric|min:0',
            'items.*.unit' => 'nullable|string|max:30',
            'items.*.times' => 'nullable|array',
            'items.*.meal_timing' => 'nullable|in:truoc_an,sau_an,trong_khi_an,khong_phu_thuoc',
            'auto_generate_schedules' => 'nullable|boolean',
        ], [
            'family_member_id.required' => 'Vui lòng chọn thành viên gia đình được kê đơn.',
            'title.required' => 'Vui lòng nhập tên đợt điều trị hoặc chẩn đoán.',
            'start_date.required' => 'Vui lòng chọn ngày bắt đầu điều trị.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.',
            'items.required' => 'Vui lòng thêm ít nhất 1 loại thuốc trong đơn.',
            'items.min' => 'Vui lòng thêm ít nhất 1 loại thuốc trong đơn.',
            'items.*.medicine_name.required' => 'Vui lòng nhập tên thuốc.',
            'items.*.dosage.required' => 'Vui lòng nhập liều dùng cho thuốc.',
        ]);
    }
}
