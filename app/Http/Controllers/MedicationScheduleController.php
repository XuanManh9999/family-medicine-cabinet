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
use Illuminate\Validation\ValidationException;

class MedicationScheduleController extends Controller
{
    /**
     * Dòng thời gian uống thuốc hàng ngày, quản lý lịch và nhật ký tuân thủ
     */
    public function index(Request $request)
    {
        $family = $this->ensureFamily($request);

        // Ngày không hợp lệ (?date=abc) thì quay về hôm nay thay vì lỗi 500
        $selectedDate = $this->normalizeDateString($request->query('date')) ?? Carbon::today()->toDateString();
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
            // Gồm cả lịch đã ngừng nhưng có nhật ký trong ngày để lịch sử không biến mất
            ->visibleOnDate($carbonDate);

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
        $dueCount = 0; // số cữ đã đến giờ (hoặc đã được điểm danh) tính tới thời điểm hiện tại
        $now = Carbon::now();

        foreach ($allDaySchedules as $sch) {
            $log = $sch->getLogForDate($carbonDate);
            if ($log) {
                if ($log->status === 'da_uong') {
                    $takenCount++;
                } elseif (in_array($log->status, ['quen_uong', 'bo_qua'])) {
                    $missedCount++;
                }
            }

            $dueAt = Carbon::parse($carbonDate->toDateString() . ' ' . $this->scheduleTimePart($sch));
            if ($log || $dueAt->lte($now)) {
                $dueCount++;
            }
        }

        $remainingCount = max(0, $totalDoses - $takenCount - $missedCount);
        // Tỷ lệ tuân thủ = đã uống / số cữ đã đến hạn; ngày tương lai hoặc chưa có cữ nào đến giờ thì hiển thị "—"
        $adherenceRate = ($carbonDate->gt(Carbon::today()) || $dueCount === 0)
            ? null
            : (int) min(100, round(($takenCount / $dueCount) * 100));

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
            'schedule_id' => 'required|integer|exists:medication_schedules,id',
            'scheduled_time' => 'required_without:date|nullable|date',
            'date' => 'required_without:scheduled_time|nullable|date_format:Y-m-d',
            'status' => 'required|in:da_uong,quen_uong,bo_qua',
            'note' => 'nullable|string|max:500',
            'actual_taken_time' => 'nullable|date',
            'deduct_cabinet' => 'nullable|boolean',
        ], [
            'scheduled_time.required_without' => 'Thiếu ngày của cữ thuốc cần ghi nhận.',
            'date.required_without' => 'Thiếu ngày của cữ thuốc cần ghi nhận.',
            'status.in' => 'Trạng thái ghi nhận không hợp lệ.',
        ]);

        $schedule = MedicationSchedule::where('family_id', $family->id)
            ->with(['prescriptionItem'])
            ->findOrFail($validated['schedule_id']);

        // Chỉ lấy NGÀY từ client, giờ quy định luôn lấy theo lịch để mỗi ngày chỉ có 1 nhật ký / lịch
        $logDate = Carbon::parse(!empty($validated['date']) ? $validated['date'] : Carbon::parse($validated['scheduled_time'])->toDateString())->startOfDay();
        if ($dateError = $this->loggableDateError($schedule, $logDate)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $dateError,
                    'errors' => ['scheduled_time' => [$dateError]],
                ], 422);
            }
            return back()->with('warning', $dateError);
        }
        $scheduledDateTime = Carbon::parse($logDate->toDateString() . ' ' . $this->scheduleTimePart($schedule));

        $isTaken = $validated['status'] === 'da_uong';
        $actualTime = null;
        if ($isTaken) {
            $actualTime = !empty($validated['actual_taken_time']) ? Carbon::parse($validated['actual_taken_time']) : Carbon::now();
            if ($actualTime->gt(Carbon::now())) {
                $actualTime = Carbon::now();
            }
        }

        // Checkbox bỏ chọn: form gửi input ẩn = 0; không gửi gì cũng KHÔNG trừ kho
        $deductRequested = $request->boolean('deduct_cabinet');

        try {
            [$log, $stock] = DB::transaction(function () use ($schedule, $family, $logDate, $scheduledDateTime, $validated, $actualTime, $isTaken, $deductRequested) {
                // Khóa lịch uống để các lần điểm danh đồng thời (bấm đúp, 2 thiết bị) chạy tuần tự
                MedicationSchedule::whereKey($schedule->id)->lockForUpdate()->first();

                $log = MedicationLog::where('schedule_id', $schedule->id)
                    ->whereDate('scheduled_time', $logDate->toDateString())
                    ->lockForUpdate()
                    ->first();

                $previousStatus = $log?->status;

                if (!$log) {
                    $log = new MedicationLog([
                        'schedule_id' => $schedule->id,
                        'scheduled_time' => $scheduledDateTime,
                    ]);
                }

                $log->fill([
                    'family_member_id' => $schedule->family_member_id,
                    'actual_taken_time' => $actualTime,
                    'status' => $validated['status'],
                    'note' => $validated['note'] ?? null,
                ]);

                $stock = ['deducted' => null, 'restored' => null, 'warning' => null];

                if ($previousStatus === 'da_uong' && !$isTaken) {
                    // Đổi từ "Đã uống" sang Quên/Bỏ qua: hoàn trả đúng lô & số lượng đã trừ
                    $stock['restored'] = $this->restoreCabinetStock($log, $family->id);
                } elseif ($previousStatus !== 'da_uong' && $isTaken && $deductRequested) {
                    // Chỉ trừ kho khi chuyển sang "Đã uống" lần đầu (sửa ghi chú / bấm lại không trừ thêm)
                    $result = $this->deductCabinetStock($schedule, $family->id);
                    $stock['warning'] = $result['warning'];
                    if ($result['item']) {
                        $log->cabinet_item_id = $result['item']->id;
                        $log->deducted_quantity = $result['amount'];
                        $stock['deducted'] = [
                            'item_name' => $result['item']->display_name,
                            'deducted_quantity' => $result['amount'],
                            'remaining_quantity' => (float) $result['item']->quantity,
                            'unit' => $result['item']->unit,
                        ];
                    }
                }

                $log->save();

                return [$log, $stock];
            });
        } catch (\Throwable $e) {
            report($e);
            $errorMsg = 'Không thể ghi nhận cữ thuốc do lỗi hệ thống. Vui lòng thử lại sau.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $errorMsg], 500);
            }
            return back()->with('warning', $errorMsg);
        }

        // Tồn kho vừa thay đổi: quét lại cảnh báo sắp hết / hết hàng ngay (lỗi quét không được làm hỏng việc ghi nhận)
        if ($stock['deducted'] || $stock['restored']) {
            try {
                app(\App\Services\AlertScannerService::class)->scanFamily($family);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Cữ thuốc đã được điểm danh: gỡ thông báo "đến giờ uống" / "quá giờ chưa uống" của cữ đó
        try {
            app(\App\Services\MedicationReminderService::class)->clearForDose($schedule, $logDate);
        } catch (\Throwable $e) {
            report($e);
        }

        $statusText = match ($validated['status']) {
            'da_uong' => 'Đã uống',
            'quen_uong' => 'Quên uống',
            'bo_qua' => 'Bỏ qua',
        };

        $message = "Đã ghi nhận cữ thuốc: {$schedule->medicine_name} ({$statusText})!";
        if ($stock['deducted']) {
            $d = $stock['deducted'];
            $message .= ' Đã trừ ' . $this->formatQty($d['deducted_quantity']) . " {$d['unit']} từ tủ thuốc \"{$d['item_name']}\" (còn " . $this->formatQty($d['remaining_quantity']) . " {$d['unit']}).";
        } elseif ($stock['restored']) {
            $r = $stock['restored'];
            $message .= ' Đã hoàn trả ' . $this->formatQty($r['restored_quantity']) . " {$r['unit']} vào tủ thuốc \"{$r['item_name']}\".";
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'warning' => $stock['warning'],
                'log' => $log,
                'status' => $log->status,
                'status_label' => $log->status_label,
                'status_badge' => $log->status_badge_class,
                'deducted_cabinet' => $stock['deducted'],
                'restored_cabinet' => $stock['restored'],
            ]);
        }

        $redirect = back()->with('success', $message);
        if ($stock['warning']) {
            $redirect->with('warning', $stock['warning']);
        }

        return $redirect;
    }

    /**
     * Ngày ghi nhận phải nằm trong thời gian áp dụng của lịch và không ở tương lai (cho phép hôm nay); trả về thông báo lỗi nếu vi phạm
     */
    protected function loggableDateError(MedicationSchedule $schedule, Carbon $logDate): ?string
    {
        if ($logDate->gt(Carbon::today())) {
            return 'Không thể ghi nhận cữ thuốc cho ngày trong tương lai.';
        }

        if ($schedule->start_date && $logDate->lt($schedule->start_date->copy()->startOfDay())) {
            return 'Ngày ghi nhận nằm trước ngày bắt đầu của lịch uống (' . $schedule->start_date->format('d/m/Y') . ').';
        }

        if ($schedule->end_date && $logDate->gt($schedule->end_date->copy()->startOfDay())) {
            return 'Ngày ghi nhận nằm sau ngày kết thúc của lịch uống (' . $schedule->end_date->format('d/m/Y') . ').';
        }

        return null;
    }

    /**
     * Trừ 1 liều khỏi tủ thuốc: chỉ lô còn hạn & còn hàng, ưu tiên đúng mã thuốc rồi tới tên thuốc,
     * hết hạn trước dùng trước (FEFO). Không trừ một phần liều; trả về cảnh báo rõ ràng khi không trừ được.
     *
     * @return array{item: ?CabinetItem, amount: float, warning: ?string}
     */
    protected function deductCabinetStock(MedicationSchedule $schedule, int $familyId): array
    {
        $dose = MedicationSchedule::parseDose($schedule->dosage);
        if (!$dose) {
            return [
                'item' => null,
                'amount' => 0,
                'warning' => "Không xác định được số lượng từ liều \"{$schedule->dosage}\" nên chưa tự động trừ tủ thuốc. Vui lòng cập nhật tồn kho thủ công.",
            ];
        }

        $amount = $dose['amount'];
        $doseUnit = $schedule->unit ?: ($schedule->prescriptionItem?->unit ?? '');

        $candidates = $this->findCabinetCandidates($schedule, $familyId);
        if ($candidates->isEmpty()) {
            return [
                'item' => null,
                'amount' => 0,
                'warning' => "Không tìm thấy \"{$schedule->medicine_name}\" còn hạn sử dụng và còn hàng trong Tủ thuốc nên chưa trừ kho.",
            ];
        }

        $compatible = $candidates->filter(fn (CabinetItem $ci) => $this->unitsCompatible($ci->unit, $doseUnit, $dose['unit_text']));
        if ($compatible->isEmpty()) {
            $first = $candidates->first();
            return [
                'item' => null,
                'amount' => 0,
                'warning' => "Đơn vị trong tủ thuốc ({$first->unit}) khác đơn vị của liều uống (" . ($doseUnit ?: $dose['unit_text']) . ") nên chưa tự động trừ kho \"{$first->display_name}\". Vui lòng cập nhật tồn kho thủ công.",
            ];
        }

        foreach ($compatible as $item) {
            if ((float) $item->quantity < $amount) {
                continue;
            }

            // Trừ nguyên tử, chỉ thành công khi lô vẫn còn đủ thuốc tại thời điểm ghi
            $affected = CabinetItem::whereKey($item->id)
                ->where('quantity', '>=', $amount)
                ->decrement('quantity', $amount);

            if ($affected) {
                $item->refresh();
                $item->syncStatus();
                return ['item' => $item, 'amount' => $amount, 'warning' => null];
            }
        }

        $first = $compatible->first();
        $available = $this->formatQty($compatible->sum(fn ($ci) => (float) $ci->quantity));
        return [
            'item' => null,
            'amount' => 0,
            'warning' => "Tủ thuốc chỉ còn {$available} {$first->unit} \"{$first->display_name}\", không đủ 1 liều (" . $this->formatQty($amount) . " {$first->unit}) nên chưa trừ kho. Vui lòng bổ sung thuốc.",
        ];
    }

    /**
     * Hoàn trả số lượng đã trừ của 1 nhật ký "Đã uống" về đúng lô thuốc
     */
    protected function restoreCabinetStock(MedicationLog $log, int $familyId): ?array
    {
        $restored = null;
        $qty = (float) ($log->deducted_quantity ?? 0);

        if ($log->cabinet_item_id && $qty > 0) {
            $item = CabinetItem::where('family_id', $familyId)->find($log->cabinet_item_id);
            if ($item) {
                CabinetItem::whereKey($item->id)->increment('quantity', $qty);
                $item->refresh();
                $item->syncStatus();
                $restored = [
                    'item_name' => $item->display_name,
                    'restored_quantity' => $qty,
                    'remaining_quantity' => (float) $item->quantity,
                    'unit' => $item->unit,
                ];
            }
        }

        $log->cabinet_item_id = null;
        $log->deducted_quantity = null;

        return $restored;
    }

    /**
     * Các lô thuốc phù hợp để trừ, đã sắp theo độ ưu tiên: đúng mã thuốc > trùng tên > tên gần đúng, rồi hạn dùng gần nhất
     */
    protected function findCabinetCandidates(MedicationSchedule $schedule, int $familyId)
    {
        $medicineId = $schedule->prescriptionItem?->medicine_id;
        $target = $this->normalizeName($schedule->medicine_name);

        $items = CabinetItem::where('family_id', $familyId)
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '>=', Carbon::today()->toDateString())
            ->with('medicine')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get();

        $ranked = [];
        foreach ($items as $position => $item) {
            $rank = null;
            if ($medicineId && (int) $item->medicine_id === (int) $medicineId) {
                $rank = 0;
            } else {
                $names = array_values(array_filter([
                    $this->normalizeName($item->custom_name),
                    $this->normalizeName($item->medicine?->name),
                ]));

                foreach ($names as $name) {
                    if ($target !== '' && $name === $target) {
                        $rank = 1;
                        break;
                    }
                }

                if ($rank === null && mb_strlen($target) >= 3) {
                    foreach ($names as $name) {
                        if (mb_strlen($name) >= 3 && (str_contains($name, $target) || str_contains($target, $name))) {
                            $rank = 2;
                            break;
                        }
                    }
                }
            }

            if ($rank !== null) {
                $ranked[] = ['rank' => $rank, 'position' => $position, 'item' => $item];
            }
        }

        usort($ranked, fn ($a, $b) => [$a['rank'], $a['position']] <=> [$b['rank'], $b['position']]);

        return collect($ranked)->pluck('item')->values();
    }

    /**
     * So khớp đơn vị tủ thuốc với đơn vị liều uống (không phân biệt hoa thường; "viên" ~ "viên nén")
     */
    protected function unitsCompatible(?string $cabinetUnit, ?string $doseUnit, ?string $doseText): bool
    {
        $cab = $this->normalizeName($cabinetUnit);
        $unit = $this->normalizeName($doseUnit);
        $text = $this->normalizeName($doseText);

        if ($cab === '' || ($unit === '' && $text === '')) {
            return true;
        }
        if ($unit !== '' && ($cab === $unit || str_contains($cab, $unit) || str_contains($unit, $cab))) {
            return true;
        }

        return $text !== '' && str_contains($text, $cab);
    }

    protected function normalizeName(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower((string) $value)));
    }

    protected function formatQty(float|int|string|null $qty): string
    {
        $value = round((float) $qty, 2);
        return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    }

    /**
     * Giờ quy định của lịch dạng H:i:s
     */
    protected function scheduleTimePart(MedicationSchedule $schedule): string
    {
        return $schedule->scheduled_time ? Carbon::parse($schedule->scheduled_time)->format('H:i:s') : '08:00:00';
    }

    /**
     * Chuẩn hóa chuỗi ngày Y-m-d, trả về null nếu không hợp lệ
     */
    protected function normalizeDateString(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($value), $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? trim($value) : null;
    }

    /**
     * Form thêm lịch uống thuốc riêng / bổ sung vitamin
     */
    public function create(Request $request)
    {
        $family = $this->ensureFamily($request);
        abort_unless($request->user()->canManageSchedules(), 403, 'Chỉ Bác sĩ/Người chăm sóc hoặc Quản trị viên hệ thống mới có quyền thiết lập lịch uống thuốc.');

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
        abort_unless($request->user()->canManageSchedules(), 403, 'Chỉ Bác sĩ/Người chăm sóc hoặc Quản trị viên hệ thống mới có quyền thiết lập lịch uống thuốc.');

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
        abort_unless($request->user()->canManageSchedules(), 403, 'Chỉ Bác sĩ/Người chăm sóc hoặc Quản trị viên hệ thống mới có quyền chỉnh sửa lịch uống thuốc.');

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
        abort_unless($request->user()->canManageSchedules(), 403, 'Chỉ Bác sĩ/Người chăm sóc hoặc Quản trị viên hệ thống mới có quyền cập nhật lịch uống thuốc.');

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
        abort_unless($request->user()->canManageSchedules(), 403, 'Chỉ Bác sĩ/Người chăm sóc hoặc Quản trị viên hệ thống mới có quyền thay đổi trạng thái lịch uống.');

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
        abort_unless($request->user()->canManageSchedules(), 403, 'Chỉ Bác sĩ/Người chăm sóc hoặc Quản trị viên hệ thống mới có quyền xóa lịch uống thuốc.');

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

    /**
     * Trả về danh sách sự kiện uống thuốc định dạng JSON cho FullCalendar (Tháng/Tuần/Ngày)
     */
    public function calendarEvents(Request $request)
    {
        $family = $this->ensureFamily($request);

        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'member_id' => 'nullable|integer',
        ], [
            'start.date' => 'Ngày bắt đầu không hợp lệ.',
            'end.date' => 'Ngày kết thúc không hợp lệ.',
        ]);

        $memberId = $validated['member_id'] ?? null;

        // Lấy phần NGÀY theo giờ địa phương của client rồi neo lại theo múi giờ ứng dụng
        $start = !empty($validated['start'])
            ? Carbon::parse(Carbon::parse($validated['start'])->toDateString())->startOfDay()
            : Carbon::today()->startOfMonth();
        $end = !empty($validated['end'])
            ? Carbon::parse(Carbon::parse($validated['end'])->toDateString())->endOfDay()
            : Carbon::today()->endOfMonth();

        if ($end->lt($start)) {
            return response()->json([
                'message' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
                'errors' => ['end' => ['Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.']],
            ], 422);
        }

        // Giới hạn khoảng ngày tối đa 65 ngày để tối ưu hiệu năng (Carbon 3: diffInDays có dấu nên đo từ start → end)
        if ($start->diffInDays($end) > 65) {
            $end = $start->copy()->addDays(65)->endOfDay();
        }

        $schedulesQuery = $family->medicationSchedules()
            ->with(['familyMember', 'prescriptionItem.treatmentCourse'])
            ->where(function ($q) use ($start) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $start->copy()->startOfDay());
            })
            ->where('start_date', '<=', $end->copy()->startOfDay())
            // Lịch đã ngừng vẫn hiển thị ở những ngày đã có nhật ký (giữ lịch sử)
            ->where(function ($q) use ($start, $end) {
                $q->where('is_active', true)
                  ->orWhereHas('logs', function ($logQ) use ($start, $end) {
                      $logQ->whereBetween('scheduled_time', [$start, $end]);
                  });
            });

        if ($memberId) {
            $schedulesQuery->where('family_member_id', $memberId);
        }

        $schedules = $schedulesQuery->get();

        // Lấy tất cả nhật ký điểm danh trong khoảng ngày này
        $logs = MedicationLog::whereHas('schedule', fn ($q) => $q->where('family_id', $family->id))
            ->whereBetween('scheduled_time', [$start, $end])
            ->get()
            ->groupBy(function ($log) {
                $dt = Carbon::parse($log->scheduled_time)->toDateString();
                return $log->schedule_id . '_' . $dt;
            });

        $events = [];

        $colorMap = [
            'sang' => '#d97706',  // Amber
            'trua' => '#0284c7',  // Sky blue
            'chieu' => '#475569', // Slate
            'toi' => '#2563eb',   // Royal blue
        ];

        $curr = $start->copy();
        while ($curr->lte($end)) {
            $dateString = $curr->toDateString();

            foreach ($schedules as $sch) {
                $schStart = Carbon::parse($sch->start_date)->startOfDay();
                $schEnd = $sch->end_date ? Carbon::parse($sch->end_date)->endOfDay() : null;

                if ($curr->lt($schStart)) {
                    continue;
                }
                if ($schEnd && $curr->gt($schEnd)) {
                    continue;
                }

                $logKey = $sch->id . '_' . $dateString;
                $log = $logs->get($logKey)?->first();

                // Lịch đã ngừng: chỉ hiện những ngày có nhật ký
                if (!$sch->is_active && !$log) {
                    continue;
                }

                $timePart = $this->scheduleTimePart($sch);
                $eventStart = $dateString . 'T' . $timePart;
                $status = $log ? $log->status : 'chua_uong';

                $memberName = $sch->familyMember ? $sch->familyMember->name : 'Gia đình';
                $title = "{$memberName}: {$sch->medicine_name} ({$sch->dosage})";

                if ($status === 'da_uong') {
                    $title = '✓ ' . $title;
                    $color = '#16a34a'; // Emerald green
                } elseif ($status === 'quen_uong' || $status === 'bo_qua') {
                    $title = '✗ ' . $title;
                    $color = '#dc2626'; // Red
                } else {
                    $color = $colorMap[$sch->time_of_day] ?? '#007bb6';
                }

                $events[] = [
                    'id' => $sch->id . '_' . $dateString,
                    'title' => $title,
                    'start' => $eventStart,
                    'allDay' => false,
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'schedule_id' => $sch->id,
                        'medicine_name' => $sch->medicine_name,
                        'dosage' => $sch->dosage,
                        'unit' => $sch->unit,
                        'time_of_day' => $sch->time_of_day,
                        'time_of_day_label' => $sch->time_of_day_label,
                        'meal_timing' => $sch->meal_timing,
                        'meal_timing_label' => $sch->meal_timing_label,
                        'member_name' => $memberName,
                        'scheduled_date' => $dateString,
                        'scheduled_time_str' => Carbon::parse($sch->scheduled_time)->format('H:i'),
                        'scheduled_datetime' => $dateString . ' ' . $timePart,
                        'status' => $status,
                        'status_label' => match($status) {
                            'da_uong' => 'Đã uống',
                            'quen_uong' => 'Quên uống',
                            'bo_qua' => 'Bỏ qua',
                            default => 'Chưa uống',
                        },
                        'note' => $log?->note,
                        'is_future' => $curr->gt(Carbon::today()),
                    ],
                ];
            }

            $curr->addDay();
        }

        return response()->json($events);
    }
}

