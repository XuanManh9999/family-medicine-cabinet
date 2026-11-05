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
    /**
     * Giờ nhắc mặc định của từng cữ uống khi sinh lịch tự động
     */
    protected const TIME_SLOT_MAP = [
        'sang' => '07:30:00',
        'trua' => '12:00:00',
        'chieu' => '17:30:00',
        'toi' => '20:00:00',
    ];

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
        if ($search = $request->textQuery('q')) {
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
        abort_unless($request->user()->canManageTreatments(), 403, 'Chỉ Bác sĩ/Người chăm sóc hoặc Quản trị viên hệ thống mới có quyền lập đợt điều trị & kê đơn thuốc.');

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
        abort_unless($request->user()->canManageTreatments(), 403, 'Chỉ Bác sĩ/Người chăm sóc hoặc Quản trị viên hệ thống mới có quyền lập đợt điều trị & kê đơn thuốc.');

        $validated = $this->validateTreatmentCourse($request, $family->id);

        $member = FamilyMember::where('family_id', $family->id)
            ->findOrFail($validated['family_member_id']);

        $itemsData = $this->filledItems($request);

        // Kiểm tra tương tác thuốc & dị ứng y khoa qua AI Rule Engine / Gemini
        $aiAnalysis = $this->evaluateSafety($itemsData, $member);

        // Upload ảnh đơn thuốc nếu có (sẽ xóa lại nếu lưu dữ liệu thất bại để không để lại file mồ côi)
        $imagePath = null;
        if ($request->hasFile('prescription_image')) {
            $imagePath = $request->file('prescription_image')->store('prescriptions', 'public');
        }

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

            // Form luôn gửi kèm input ẩn = 0 nên có thể tắt đồng bộ lịch uống
            $autoGenerate = $request->boolean('auto_generate_schedules', true);

            foreach ($itemsData as $item) {
                $pItem = PrescriptionItem::create(
                    ['treatment_course_id' => $treatmentCourse->id] + $this->prescriptionItemAttributes($item)
                );

                // Sinh lịch uống tự động theo đúng các cữ được chọn (không chọn cữ nào = thuốc dùng khi cần, không lập lịch)
                if ($autoGenerate) {
                    $this->syncItemSchedules($pItem, $treatmentCourse, $family->id, $member->id, $this->selectedTimes($item));
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            report($e);
            return back()->withInput()->with('warning', 'Không thể lưu đợt điều trị do lỗi hệ thống. Vui lòng thử lại sau.');
        }

        $msg = "Đã lưu đợt điều trị thành công cho bệnh nhân {$member->name}!";
        if (!empty($aiAnalysis['has_conflict'])) {
            return redirect()->route('treatment-courses.show', $treatmentCourse)
                ->with('warning', $msg . ' LƯU Ý: AI đã phát hiện cảnh báo tương tác/dị ứng thuốc trong đơn này!');
        }

        return redirect()->route('treatment-courses.show', $treatmentCourse)
            ->with('success', $msg);
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
            'prescriptionItems.cabinetItems',
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
        abort_unless($request->user()->canManageTreatments(), 403, 'Chỉ Bác sĩ/Người chăm sóc hoặc Quản trị viên hệ thống mới có quyền chỉnh sửa đợt điều trị.');

        $family = $request->user()->family;
        $members = $family->members()->orderBy('name')->get();
        $availableMedicines = Medicine::orderBy('name')->take(50)->get(['id', 'name', 'active_ingredient', 'concentration', 'default_unit']);
        $cabinetItems = $family->cabinetItems()->with('medicine')->where('quantity', '>', 0)->get();

        $treatmentCourse->load('prescriptionItems.schedules');

        // Dựng lại dữ liệu từng thuốc kèm id, các cữ uống & thời điểm ăn thực tế từ lịch đã sinh
        $timeKeys = array_keys(self::TIME_SLOT_MAP);
        $formItems = $treatmentCourse->prescriptionItems->map(function (PrescriptionItem $pItem) use ($timeKeys) {
            $times = $pItem->schedules->pluck('time_of_day')->unique()->all();

            return [
                'id' => $pItem->id,
                'medicine_id' => $pItem->medicine_id,
                'medicine_name' => $pItem->medicine_name,
                'dosage' => $pItem->dosage,
                'frequency' => $pItem->frequency,
                'instructions' => $pItem->instructions,
                'total_prescribed_quantity' => (float) $pItem->total_prescribed_quantity,
                'unit' => $pItem->unit,
                'meal_timing' => $pItem->effective_meal_timing,
                'times' => array_values(array_intersect($timeKeys, $times)),
            ];
        })->values()->all();

        $hasSchedules = $treatmentCourse->prescriptionItems->contains(fn ($pItem) => $pItem->schedules->isNotEmpty());

        return view('treatment-courses.form', [
            'treatmentCourse' => $treatmentCourse,
            'editing' => true,
            'members' => $members,
            'preselectedMemberId' => $treatmentCourse->family_member_id,
            'availableMedicines' => $availableMedicines,
            'cabinetItems' => $cabinetItems,
            'formItems' => $formItems ?: [[]],
            'autoGenerateDefault' => $hasSchedules,
        ]);
    }

    /**
     * Cập nhật đợt điều trị & đơn thuốc
     */
    public function update(Request $request, TreatmentCourse $treatmentCourse)
    {
        $this->authorizeCourse($request, $treatmentCourse);
        abort_unless($request->user()->canManageTreatments(), 403, 'Chỉ Bác sĩ/Người chăm sóc hoặc Quản trị viên hệ thống mới có quyền cập nhật đợt điều trị.');

        $family = $request->user()->family;

        $validated = $this->validateTreatmentCourse($request, $family->id);

        $member = FamilyMember::where('family_id', $family->id)
            ->findOrFail($validated['family_member_id']);

        $itemsData = $this->filledItems($request);

        // Tái thẩm định an toàn tương tác thuốc
        $aiAnalysis = $this->evaluateSafety($itemsData, $member);

        // Ảnh mới chỉ thay ảnh cũ sau khi lưu DB thành công
        $oldImagePath = $treatmentCourse->prescription_image_path;
        $newImagePath = null;
        if ($request->hasFile('prescription_image')) {
            $newImagePath = $request->file('prescription_image')->store('prescriptions', 'public');
        }

        DB::beginTransaction();
        try {
            $courseData = [
                'family_member_id' => $member->id,
                'title' => $validated['title'],
                'hospital_doctor' => $validated['hospital_doctor'] ?? null,
                'diagnosis' => $validated['diagnosis'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'ai_analysis_data' => $aiAnalysis,
                'status' => $validated['status'] ?? 'dang_dieu_tri',
            ];
            if ($newImagePath) {
                $courseData['prescription_image_path'] = $newImagePath;
            }
            $treatmentCourse->update($courseData);

            $autoGenerate = $request->boolean('auto_generate_schedules', true);

            // Đối chiếu từng thuốc theo id thay vì xóa & tạo lại để giữ nguyên lịch sử nhật ký uống thuốc
            $existingItems = $treatmentCourse->prescriptionItems()->get()->keyBy('id');
            $keptIds = [];

            foreach ($itemsData as $item) {
                $attributes = $this->prescriptionItemAttributes($item);
                $itemId = (int) ($item['id'] ?? 0);

                if ($itemId && $existingItems->has($itemId) && !in_array($itemId, $keptIds, true)) {
                    $pItem = $existingItems->get($itemId);
                    $pItem->update($attributes);
                } else {
                    $pItem = PrescriptionItem::create(['treatment_course_id' => $treatmentCourse->id] + $attributes);
                }
                $keptIds[] = $pItem->id;

                // Tắt đồng bộ: giữ nguyên các cữ hiện có (chỉ cập nhật thông tin thuốc / trạng thái), không thêm bớt cữ
                $this->syncItemSchedules(
                    $pItem,
                    $treatmentCourse,
                    $family->id,
                    $member->id,
                    $autoGenerate ? $this->selectedTimes($item) : null
                );
            }

            // Thuốc bị gỡ khỏi đơn: lịch đã có nhật ký thì ngừng (giữ lịch sử), chưa có thì xóa
            foreach ($existingItems as $oldId => $oldItem) {
                if (in_array($oldId, $keptIds, true)) {
                    continue;
                }
                foreach ($oldItem->schedules()->get() as $oldSchedule) {
                    $this->retireSchedule($oldSchedule);
                }
                $oldItem->delete();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }
            report($e);
            return back()->withInput()->with('warning', 'Không thể cập nhật đợt điều trị do lỗi hệ thống. Vui lòng thử lại sau.');
        }

        if ($newImagePath && $oldImagePath && $oldImagePath !== $newImagePath && !preg_match('#^https?://#i', $oldImagePath)) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return redirect()->route('treatment-courses.show', $treatmentCourse)
            ->with('success', 'Đã cập nhật đợt điều trị và đơn thuốc thành công!');
    }

    /**
     * Bật/Tắt nhanh trạng thái Đang điều trị <-> Đã hoàn thành
     */
    public function toggleStatus(Request $request, TreatmentCourse $treatmentCourse)
    {
        $this->authorizeCourse($request, $treatmentCourse);
        abort_unless($request->user()->canManageTreatments(), 403, 'Chỉ Bác sĩ/Người chăm sóc hoặc Quản trị viên hệ thống mới có quyền thay đổi trạng thái đợt điều trị.');

        $newStatus = ($treatmentCourse->status === 'dang_dieu_tri') ? 'da_hoan_thanh' : 'dang_dieu_tri';
        $isActive = ($newStatus === 'dang_dieu_tri');

        DB::transaction(function () use ($treatmentCourse, $newStatus, $isActive) {
            $treatmentCourse->update(['status' => $newStatus]);
            // Cập nhật trạng thái các lịch uống liên quan (kích hoạt lại chỉ các lịch còn hiệu lực từ hôm nay)
            foreach ($treatmentCourse->prescriptionItems as $pItem) {
                if ($isActive) {
                    $pItem->schedules()
                        ->where(function ($q) {
                            $q->whereNull('end_date')
                              ->orWhere('end_date', '>=', Carbon::today());
                        })
                        ->update(['is_active' => true]);
                } else {
                    $pItem->schedules()->update(['is_active' => false]);
                }
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
        abort_unless($request->user()->canDeleteTreatments(), 403, 'Chỉ Bác sĩ/Người chăm sóc hoặc Quản trị viên hệ thống mới có quyền xóa đợt điều trị.');

        // Hồ sơ điều trị đã phát sinh lịch sử (điểm danh, mua thuốc, chi phí) là dữ liệu y tế: không xóa, chỉ kết thúc đợt
        if ($this->hasHistory($treatmentCourse)) {
            return back()->with('warning', 'Đợt điều trị đã có lịch sử điểm danh / mua thuốc / chi phí nên không thể xóa (để giữ hồ sơ y tế). Hãy dùng "Kết thúc đợt" thay vì xóa.');
        }

        $imagePath = $treatmentCourse->prescription_image_path;

        DB::transaction(function () use ($treatmentCourse) {
            foreach ($treatmentCourse->prescriptionItems as $pItem) {
                $pItem->schedules()->delete();
                $pItem->delete();
            }

            $treatmentCourse->delete();
        });

        // Chỉ xóa file ảnh sau khi dữ liệu đã xóa thành công
        if ($imagePath && !preg_match('#^https?://#i', $imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

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

        // Không gửi họ tên người bệnh sang dịch vụ AI bên ngoài (chỉ cần tuổi, dị ứng, bệnh nền)
        $patientProfile = [
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
     * Đợt điều trị đã có điểm danh uống thuốc, lô thuốc mua theo đơn hoặc chi phí gắn với đơn
     */
    protected function hasHistory(TreatmentCourse $course): bool
    {
        $itemIds = $course->prescriptionItems()->pluck('id');

        return \App\Models\MedicationLog::whereHas('schedule', fn ($q) => $q->whereIn('prescription_item_id', $itemIds))->exists()
            || CabinetItem::withAnyApproval()->whereIn('prescription_item_id', $itemIds)->exists()
            || \App\Models\MedicineExpense::where('treatment_course_id', $course->id)->exists();
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
     * Danh sách thuốc hợp lệ (có tên) gửi lên từ form
     */
    protected function filledItems(Request $request): array
    {
        return array_values(array_filter(
            (array) $request->input('items', []),
            fn ($item) => is_array($item) && trim((string) ($item['medicine_name'] ?? '')) !== ''
        ));
    }

    /**
     * Thẩm định tương tác thuốc / dị ứng (không gửi họ tên người bệnh sang AI)
     */
    protected function evaluateSafety(array $itemsData, FamilyMember $member): array
    {
        $medicinesForAi = [];
        foreach ($itemsData as $item) {
            $activeIng = '';
            if (!empty($item['medicine_id'])) {
                $activeIng = Medicine::find($item['medicine_id'])?->active_ingredient ?? '';
            }

            $medicinesForAi[] = [
                'name' => trim($item['medicine_name']),
                'active_ingredient' => $activeIng,
                'dosage' => $item['dosage'] ?? '',
                'frequency' => $item['frequency'] ?? '',
            ];
        }

        $patientHealthProfile = [
            'age' => $member->age,
            'allergies' => $member->allergies ?? '',
            'underlying_diseases' => $member->underlying_diseases ?? '',
        ];

        $aiAnalysis = $this->geminiService->checkDrugInteractions($medicinesForAi, $patientHealthProfile);
        $aiAnalysis['evaluated_at'] = Carbon::now()->toIso8601String();
        $aiAnalysis['checked_items_count'] = count($medicinesForAi);

        return $aiAnalysis;
    }

    /**
     * Thuộc tính lưu cho 1 thuốc trong đơn
     */
    protected function prescriptionItemAttributes(array $item): array
    {
        return [
            'medicine_id' => !empty($item['medicine_id']) ? $item['medicine_id'] : null,
            'medicine_name' => trim($item['medicine_name']),
            'dosage' => $item['dosage'] ?? '1 viên',
            'frequency' => $item['frequency'] ?? 'Ngày uống theo chỉ định',
            'instructions' => $item['instructions'] ?? null,
            'total_prescribed_quantity' => floatval($item['total_prescribed_quantity'] ?? 0),
            'unit' => $item['unit'] ?? 'viên',
            'meal_timing' => $item['meal_timing'] ?? 'sau_an',
        ];
    }

    /**
     * Các cữ uống hợp lệ được chọn cho 1 thuốc (giữ thứ tự Sáng → Tối, bỏ trùng)
     */
    protected function selectedTimes(array $item): array
    {
        return array_values(array_intersect(array_keys(self::TIME_SLOT_MAP), (array) ($item['times'] ?? [])));
    }

    /**
     * Đồng bộ lịch uống của 1 thuốc theo các cữ được chọn:
     * - cữ vẫn được chọn: giữ lịch cũ (cùng id, giữ nhật ký) và cập nhật liều / bữa ăn / ngày áp dụng
     * - cữ mới: tạo lịch mới
     * - cữ bị bỏ: lịch có nhật ký thì ngừng, chưa có thì xóa
     * $times = null: không thêm/bớt cữ, chỉ cập nhật thông tin các lịch hiện có
     */
    protected function syncItemSchedules(PrescriptionItem $pItem, TreatmentCourse $course, int $familyId, int $memberId, ?array $times): void
    {
        $isActive = $course->status === 'dang_dieu_tri';
        $remaining = $times === null ? null : array_flip($times);
        // Bác sĩ đã cho ngưng thuốc này → lịch uống kết thúc ở ngày ngưng dù đợt điều trị còn dài
        $endDate = $course->end_date;
        if ($pItem->stopped_at && (!$endDate || $pItem->stopped_at->lt($endDate))) {
            $endDate = $pItem->stopped_at;
        }

        // Cữ cần tạo lịch mới: time_of_day => [giờ uống, ngày bắt đầu]
        $toCreate = [];
        foreach (array_keys($remaining ?? []) as $timeKey) {
            $toCreate[$timeKey] = [self::TIME_SLOT_MAP[$timeKey], $course->start_date];
        }

        foreach ($pItem->schedules()->orderBy('id')->get() as $schedule) {
            $keep = $remaining === null || isset($remaining[$schedule->time_of_day]);

            if (!$keep) {
                $this->retireSchedule($schedule);
                continue;
            }

            unset($toCreate[$schedule->time_of_day]);

            $logRange = $schedule->logs()
                ->selectRaw('MIN(scheduled_time) as first_log, MAX(scheduled_time) as last_log')
                ->first();

            // Đổi người bệnh của đơn: lịch đã có nhật ký thuộc về người cũ → ngừng & giữ lịch sử,
            // tạo lịch mới cho người bệnh mới từ hôm nay (không gán nhật ký cũ sang người khác)
            if ((int) $schedule->family_member_id !== $memberId && $logRange?->last_log) {
                $this->retireSchedule($schedule);
                $start = Carbon::today()->max($course->start_date ?? Carbon::today());
                $toCreate[$schedule->time_of_day] = [$schedule->scheduled_time, $start];
                continue;
            }

            // Sửa ngày của đơn không được làm "mất" các ngày đã điểm danh khỏi dòng thời gian / lịch biểu
            $startDate = $course->start_date;
            if ($logRange?->first_log && $startDate && Carbon::parse($logRange->first_log)->startOfDay()->lt($startDate)) {
                $startDate = Carbon::parse($logRange->first_log)->startOfDay();
            }
            $scheduleEnd = $endDate;
            if ($logRange?->last_log && $scheduleEnd && Carbon::parse($logRange->last_log)->startOfDay()->gt($scheduleEnd)) {
                $scheduleEnd = Carbon::parse($logRange->last_log)->startOfDay();
            }

            $schedule->update([
                'family_id' => $familyId,
                'family_member_id' => $memberId,
                'medicine_name' => $pItem->medicine_name,
                'dosage' => $pItem->dosage,
                'unit' => $pItem->unit,
                'meal_timing' => $pItem->meal_timing ?? 'sau_an',
                'start_date' => $startDate,
                'end_date' => $scheduleEnd,
                'is_active' => $isActive,
            ]);
        }

        foreach ($toCreate as $timeKey => [$scheduledTime, $startDate]) {
            if ($endDate && $startDate && Carbon::parse($startDate)->gt($endDate)) {
                continue;
            }
            MedicationSchedule::create([
                'family_id' => $familyId,
                'family_member_id' => $memberId,
                'prescription_item_id' => $pItem->id,
                'medicine_name' => $pItem->medicine_name,
                'dosage' => $pItem->dosage,
                'unit' => $pItem->unit,
                'time_of_day' => $timeKey,
                'scheduled_time' => $scheduledTime,
                'meal_timing' => $pItem->meal_timing ?? 'sau_an',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => $isActive,
            ]);
        }
    }

    /**
     * Gỡ 1 cữ uống khỏi đơn: đã có nhật ký thì ngừng lịch (kết thúc từ hôm qua / ngày điểm danh cuối)
     * và tách khỏi đơn để giữ lịch sử tuân thủ; chưa có nhật ký thì xóa hẳn
     */
    protected function retireSchedule(MedicationSchedule $schedule): void
    {
        $lastLogTime = $schedule->logs()->max('scheduled_time');

        if (!$lastLogTime) {
            $schedule->delete();
            return;
        }

        $endDate = Carbon::yesterday();
        $lastLogDate = Carbon::parse($lastLogTime)->startOfDay();
        if ($lastLogDate->gt($endDate)) {
            $endDate = $lastLogDate;
        }
        if ($schedule->end_date && $schedule->end_date->lt($endDate)) {
            $endDate = $schedule->end_date->copy();
        }
        if ($schedule->start_date && $endDate->lt($schedule->start_date)) {
            $endDate = $schedule->start_date->copy();
        }

        $schedule->update([
            'is_active' => false,
            'end_date' => $endDate,
            'prescription_item_id' => null,
        ]);
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
            'items.*.id' => 'nullable|integer',
            'items.*.medicine_name' => 'required|string|max:255',
            'items.*.medicine_id' => 'nullable|exists:medicines,id',
            'items.*.dosage' => 'required|string|max:100',
            'items.*.frequency' => 'nullable|string|max:255',
            'items.*.instructions' => 'nullable|string|max:500',
            'items.*.total_prescribed_quantity' => 'nullable|numeric|min:0',
            'items.*.unit' => 'nullable|string|max:30',
            'items.*.times' => 'nullable|array',
            'items.*.times.*' => 'in:sang,trua,chieu,toi',
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
