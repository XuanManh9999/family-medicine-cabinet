<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationLog;
use App\Models\MedicationSchedule;
use App\Models\Medicine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedicationScheduleController extends Controller
{
    /**
     * Dòng thời gian uống thuốc hàng ngày, quản lý lịch và nhật ký tuân thủ
     */
    public function index(Request $request)
    {
        $family = $this->ensureFamily($request);

        $selectedDate = $request->query('date', Carbon::today()->toDateString());
        $carbonDate = Carbon::parse($selectedDate)->startOfDay();

        $viewMode = $request->query('view', 'timeline'); // timeline, all, logs
        $memberId = $request->query('member_id');
        $timeOfDay = $request->query('time_of_day', 'all');

        $members = $family->members()->orderBy('name')->get();

        // 1. Dữ liệu cho dòng thời gian theo ngày (Timeline)
        $timelineQuery = $family->medicationSchedules()
            ->with([
                'familyMember',
                'prescriptionItem.treatmentCourse',
                'logs' => function ($q) use ($carbonDate) {
                    $q->whereDate('scheduled_time', $carbonDate->toDateString());
                },
            ])
            ->activeOnDate($carbonDate);

        if ($memberId) {
            $timelineQuery->where('family_member_id', $memberId);
        }

        if ($timeOfDay !== 'all') {
            $timelineQuery->where('time_of_day', $timeOfDay);
        }

        $allDaySchedules = $timelineQuery->orderBy('scheduled_time')->get();

        // Phân loại các cữ trong ngày
        $timelineSlots = [
            'sang' => [
                'title' => 'Cữ Sáng',
                'icon' => 'bi bi-brightness-high-fill text-warning',
                'badge' => 'bg-warning text-dark',
                'time_range' => '06:00 - 11:59',
                'items' => $allDaySchedules->where('time_of_day', 'sang'),
            ],
            'trua' => [
                'title' => 'Cữ Trưa',
                'icon' => 'bi bi-sun-fill text-info',
                'badge' => 'bg-info text-dark',
                'time_range' => '12:00 - 16:59',
                'items' => $allDaySchedules->where('time_of_day', 'trua'),
            ],
            'chieu' => [
                'title' => 'Cữ Chiều',
                'icon' => 'bi bi-sunset-fill text-warning-emphasis',
                'badge' => 'bg-secondary text-white',
                'time_range' => '17:00 - 18:59',
                'items' => $allDaySchedules->where('time_of_day', 'chieu'),
            ],
            'toi' => [
                'title' => 'Cữ Tối',
                'icon' => 'bi bi-moon-stars-fill text-primary',
                'badge' => 'bg-primary text-white',
                'time_range' => '19:00 - 23:59',
                'items' => $allDaySchedules->where('time_of_day', 'toi'),
            ],
        ];

        // 2. Thống kê KPI tuân thủ trong ngày đã chọn
        $totalDoses = $allDaySchedules->count();
        $takenCount = 0;
        $missedCount = 0;

        foreach ($allDaySchedules as $sch) {
            $log = $sch->getLogForDate($carbonDate);
            if ($log) {
                if ($log->status === 'da_uong') {
                    $takenCount++;
                } elseif (in_array($log->status, ['quen_uong', 'bo_qua'])) {
                    $missedCount++;
                }
            }
        }

        $remainingCount = max(0, $totalDoses - $takenCount - $missedCount);
        $adherenceRate = $totalDoses > 0 ? round(($takenCount / $totalDoses) * 100) : 100;

        // 3. Dữ liệu Danh sách toàn bộ lịch lặp (Tab Quản lý lịch)
        $allSchedules = null;
        if ($viewMode === 'all') {
            $allQuery = $family->medicationSchedules()
                ->with(['familyMember', 'prescriptionItem.treatmentCourse']);

            if ($memberId) {
                $allQuery->where('family_member_id', $memberId);
            }

            $allSchedules = $allQuery->orderByDesc('is_active')
                ->orderBy('scheduled_time')
                ->paginate(15)
                ->withQueryString();
        }

        // 4. Dữ liệu Nhật ký tuân thủ (Tab Nhật ký)
        $logs = null;
        if ($viewMode === 'logs') {
            $logQuery = MedicationLog::whereHas('schedule', function ($q) use ($family) {
                $q->where('family_id', $family->id);
            })->with(['schedule', 'familyMember']);

            if ($memberId) {
                $logQuery->where('family_member_id', $memberId);
            }

            $logs = $logQuery->orderByDesc('scheduled_time')
                ->paginate(20)
                ->withQueryString();
        }

        // Danh sách thuốc trong tủ để hỗ trợ modal uống nhanh
        $cabinetItems = $family->cabinetItems()->with('medicine')->where('quantity', '>', 0)->get();

        return view('schedules.index', compact(
            'selectedDate',
            'carbonDate',
            'viewMode',
            'memberId',
            'timeOfDay',
            'members',
            'timelineSlots',
            'totalDoses',
            'takenCount',
            'missedCount',
            'remainingCount',
            'adherenceRate',
            'allSchedules',
            'logs',
            'cabinetItems'
        ));
    }

    /**
     * Ghi nhận 1 chạm: Đã uống / Quên uống / Bỏ qua cữ thuốc
     */
    public function recordLog(Request $request)
    {
        $family = $this->ensureFamily($request);

        $validated = $request->validate([
            'schedule_id' => 'required|exists:medication_schedules,id',
            'scheduled_time' => 'required|date',
            'status' => 'required|in:da_uong,quen_uong,bo_qua',
            'note' => 'nullable|string|max:500',
            'actual_taken_time' => 'nullable|date',
            'deduct_cabinet' => 'nullable|boolean',
        ]);

        $schedule = MedicationSchedule::where('family_id', $family->id)
            ->with(['prescriptionItem.medicine'])
            ->findOrFail($validated['schedule_id']);

        $scheduledDateTime = Carbon::parse($validated['scheduled_time']);
        $actualTime = ($validated['status'] === 'da_uong')
            ? (!empty($validated['actual_taken_time']) ? Carbon::parse($validated['actual_taken_time']) : Carbon::now())
            : null;

        DB::beginTransaction();
        try {
            $log = MedicationLog::updateOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'family_member_id' => $schedule->family_member_id,
                    'scheduled_time' => $scheduledDateTime,
                ],
                [
                    'actual_taken_time' => $actualTime,
                    'status' => $validated['status'],
                    'note' => $validated['note'] ?? null,
                ]
            );

            // Tự động trừ tồn kho tủ thuốc nếu chọn "Đã uống"
            $deductedInfo = null;
            if ($validated['status'] === 'da_uong' && $request->boolean('deduct_cabinet', true)) {
                $cabinetItem = CabinetItem::where('family_id', $family->id)
                    ->where(function ($q) use ($schedule) {
                        if ($schedule->prescriptionItem && $schedule->prescriptionItem->medicine_id) {
                            $q->where('medicine_id', $schedule->prescriptionItem->medicine_id);
                        } else {
                            $q->where('custom_name', 'like', "%{$schedule->medicine_name}%")
                              ->orWhereHas('medicine', function ($mQ) use ($schedule) {
                                  $mQ->where('name', 'like', "%{$schedule->medicine_name}%");
                              });
                        }
                    })
                    ->where('quantity', '>', 0)
                    ->first();

                if ($cabinetItem) {
                    $doseAmount = 1;
                    if (preg_match('/^([0-9]+(?:\.[0-9]+)?)/', $schedule->dosage, $matches)) {
                        $doseAmount = floatval($matches[1]);
                    }
                    $cabinetItem->decrement('quantity', min($doseAmount, $cabinetItem->quantity));
                    $deductedInfo = [
                        'item_name' => $cabinetItem->display_name,
                        'remaining_quantity' => $cabinetItem->fresh()->quantity,
                        'unit' => $cabinetItem->unit,
                    ];
                }
            }

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Đã ghi nhận nhật ký uống thuốc thành công!',
                    'log' => $log,
                    'status' => $log->status,
                    'status_label' => $log->status_label,
                    'status_badge' => $log->status_badge_class,
                    'deducted_cabinet' => $deductedInfo,
                ]);
            }

            $statusText = match ($validated['status']) {
                'da_uong' => 'Đã uống',
                'quen_uong' => 'Quên uống',
                'bo_qua' => 'Bỏ qua',
            };

            return back()->with('success', "Đã ghi nhận cữ thuốc: {$schedule->medicine_name} ({$statusText})!");
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->with('warning', 'Lỗi ghi nhận: ' . $e->getMessage());
        }
    }

    /**
     * Form thêm lịch uống thuốc riêng / bổ sung vitamin
     */
    public function create(Request $request)
    {
        $family = $this->ensureFamily($request);
        $members = $family->members()->orderBy('name')->get();

        if ($members->isEmpty()) {
            return redirect()->route('members.create')
                ->with('warning', 'Vui lòng thêm thành viên gia đình trước khi tạo lịch uống thuốc!');
        }

        $preselectedMemberId = $request->query('member_id');
        $availableMedicines = Medicine::orderBy('name')->take(50)->get(['id', 'name', 'active_ingredient', 'concentration', 'default_unit']);
        $cabinetItems = $family->cabinetItems()->with('medicine')->where('quantity', '>', 0)->get();

        $schedule = new MedicationSchedule([
            'scheduled_time' => '07:30',
            'time_of_day' => 'sang',
            'dosage' => '1 viên',
            'unit' => 'viên',
            'meal_timing' => 'sau_an',
            'start_date' => Carbon::today(),
            'is_active' => true,
        ]);

        return view('schedules.form', [
            'schedule' => $schedule,
            'editing' => false,
            'members' => $members,
            'preselectedMemberId' => $preselectedMemberId,
            'availableMedicines' => $availableMedicines,
            'cabinetItems' => $cabinetItems,
        ]);
    }

    /**
     * Lưu lịch uống thuốc mới
     */
    public function store(Request $request)
    {
        $family = $this->ensureFamily($request);

        $validated = $this->validateSchedule($request, $family->id);
        $timeStr = strlen($validated['scheduled_time']) === 5 ? $validated['scheduled_time'] . ':00' : $validated['scheduled_time'];

        $schedule = MedicationSchedule::create([
            'family_id' => $family->id,
            'family_member_id' => $validated['family_member_id'],
            'medicine_name' => $validated['medicine_name'],
            'dosage' => $validated['dosage'],
            'unit' => $validated['unit'] ?? 'viên',
            'time_of_day' => $validated['time_of_day'],
            'scheduled_time' => $timeStr,
            'meal_timing' => $validated['meal_timing'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('schedules.index')
            ->with('success', "Đã tạo lịch uống thuốc mới cho {$schedule->familyMember->name}: {$schedule->medicine_name}!");
    }

    /**
     * Form chỉnh sửa lịch uống thuốc
     */
    public function edit(Request $request, MedicationSchedule $schedule)
    {
        $this->authorizeSchedule($request, $schedule);

        $family = $request->user()->family;
        $members = $family->members()->orderBy('name')->get();
        $availableMedicines = Medicine::orderBy('name')->take(50)->get(['id', 'name', 'active_ingredient', 'concentration', 'default_unit']);
        $cabinetItems = $family->cabinetItems()->with('medicine')->where('quantity', '>', 0)->get();

        return view('schedules.form', [
            'schedule' => $schedule,
            'editing' => true,
            'members' => $members,
            'preselectedMemberId' => $schedule->family_member_id,
            'availableMedicines' => $availableMedicines,
            'cabinetItems' => $cabinetItems,
        ]);
    }

    /**
     * Cập nhật lịch uống thuốc
     */
    public function update(Request $request, MedicationSchedule $schedule)
    {
        $this->authorizeSchedule($request, $schedule);
        $family = $request->user()->family;

        $validated = $this->validateSchedule($request, $family->id);
        $timeStr = strlen($validated['scheduled_time']) === 5 ? $validated['scheduled_time'] . ':00' : $validated['scheduled_time'];

        $schedule->update([
            'family_member_id' => $validated['family_member_id'],
            'medicine_name' => $validated['medicine_name'],
            'dosage' => $validated['dosage'],
            'unit' => $validated['unit'] ?? 'viên',
            'time_of_day' => $validated['time_of_day'],
            'scheduled_time' => $timeStr,
            'meal_timing' => $validated['meal_timing'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('schedules.index')
            ->with('success', "Đã cập nhật lịch uống thuốc: {$schedule->medicine_name}!");
    }

    /**
     * Bật / Tắt nhanh lịch uống thuốc
     */
    public function toggle(Request $request, MedicationSchedule $schedule)
    {
        $this->authorizeSchedule($request, $schedule);

        $schedule->update([
            'is_active' => !$schedule->is_active,
        ]);

        $statusText = $schedule->is_active ? 'Đã kích hoạt' : 'Đã tạm dừng';
        return back()->with('success', "{$statusText} lịch nhắc: {$schedule->medicine_name}!");
    }

    /**
     * Xóa lịch uống thuốc
     */
    public function destroy(Request $request, MedicationSchedule $schedule)
    {
        $this->authorizeSchedule($request, $schedule);

        $schedule->logs()->delete();
        $schedule->delete();

        return redirect()->route('schedules.index')
            ->with('success', 'Đã xóa lịch uống thuốc thành công!');
    }

    /**
     * Đảm bảo người dùng thuộc gia đình
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
     * Kiểm tra quyền sở hữu lịch uống thuốc
     */
    protected function authorizeSchedule(Request $request, MedicationSchedule $schedule): void
    {
        $family = $this->ensureFamily($request);
        if ($schedule->family_id !== $family->id) {
            abort(403, 'Bạn không có quyền truy cập lịch uống thuốc này.');
        }
    }

    /**
     * Validate dữ liệu đầu vào của lịch uống thuốc
     */
    protected function validateSchedule(Request $request, int $familyId): array
    {
        return $request->validate([
            'family_member_id' => "required|exists:family_members,id,family_id,{$familyId}",
            'medicine_name' => 'required|string|max:255',
            'dosage' => 'required|string|max:100',
            'unit' => 'nullable|string|max:30',
            'time_of_day' => 'required|in:sang,trua,chieu,toi',
            'scheduled_time' => 'required|date_format:H:i',
            'meal_timing' => 'required|in:sau_an,truoc_an,trong_khi_an,khong_phu_thuoc',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'nullable|boolean',
        ], [
            'family_member_id.required' => 'Vui lòng chọn thành viên cần uống thuốc.',
            'medicine_name.required' => 'Vui lòng nhập tên thuốc hoặc vitamin.',
            'dosage.required' => 'Vui lòng nhập liều lượng uống.',
            'scheduled_time.required' => 'Vui lòng nhập giờ uống thuốc định kỳ.',
            'start_date.required' => 'Vui lòng chọn ngày bắt đầu uống thuốc.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.',
        ]);
    }
}
