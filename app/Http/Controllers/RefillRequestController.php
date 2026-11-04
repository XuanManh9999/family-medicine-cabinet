<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\MedicationSchedule;
use App\Models\RefillRequest;
use App\Models\SmartAlert;
use App\Models\User;
use App\Services\AlertScannerService;
use App\Services\CabinetStockMatcher;
use App\Services\MedicationSupplyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Yêu cầu bổ sung thuốc:
 * - Chủ hộ / Thành viên: thuốc đang uống sắp hết, hoặc thuốc trong tủ sắp hết / hết hạn → bấm "Yêu cầu bổ sung"
 * - Bác sĩ (hoặc Quản trị viên khi gia đình chưa có Bác sĩ): Kê tiếp / Đồng ý mua thêm, Ngưng thuốc, hoặc Không mua thêm
 */
class RefillRequestController extends Controller
{
    public function store(Request $request, MedicationSupplyService $supply, CabinetStockMatcher $matcher)
    {
        $family = $this->ensureFamily($request);
        abort_unless($request->user()->canRequestCabinetItems(), 403, 'Bác sĩ kê tiếp hoặc nhập thuốc trực tiếp, không cần gửi yêu cầu.');

        $validated = $request->validate([
            'source' => ['required', 'in:schedule,cabinet'],
            'schedule_id' => ['required_if:source,schedule', 'nullable', 'integer'],
            'cabinet_item_id' => ['required_if:source,cabinet', 'nullable', 'integer'],
            'requester_note' => ['nullable', 'string', 'max:1000'],
        ]);

        // Thông tin thuốc lấy từ dữ liệu hệ thống (không tin số liệu do trình duyệt gửi lên)
        if ($validated['source'] === RefillRequest::SOURCE_SCHEDULE) {
            $row = $supply->forFamily($family)->first(fn (array $r) => in_array((int) $validated['schedule_id'], $r['schedule_ids'], true));
            abort_unless($row, 404, 'Không tìm thấy thuốc đang uống này trong lịch của gia đình.');

            $attributes = [
                'source' => RefillRequest::SOURCE_SCHEDULE,
                'prescription_item_id' => $row['prescription_item']?->id,
                'medicine_id' => $row['medicine_id'],
                'family_member_id' => $row['family_member_id'],
                'medicine_name' => $row['medicine_name'],
                'unit' => $row['unit'],
                'current_stock' => $row['stock'],
                'days_left' => $row['days_left'],
            ];
        } else {
            $lot = CabinetItem::where('family_id', $family->id)->findOrFail($validated['cabinet_item_id']);
            if ($lot->is_expired) {
                return redirect()->route('cabinet.supply')
                    ->with('warning', "\"{$lot->display_name}\" đã hết hạn: hãy loại bỏ khỏi tủ. Nếu vẫn cần dùng, hỏi Bác sĩ để được kê đơn mới.");
            }
            $attributes = [
                'source' => RefillRequest::SOURCE_CABINET,
                'cabinet_item_id' => $lot->id,
                'prescription_item_id' => $lot->prescription_item_id,
                'medicine_id' => $lot->medicine_id,
                'medicine_name' => $lot->display_name,
                'unit' => $lot->unit,
                'current_stock' => $lot->quantity,
            ];
        }

        // Mỗi thuốc chỉ có một yêu cầu đang xử lý
        $duplicate = RefillRequest::where('family_id', $family->id)
            ->whereIn('status', [RefillRequest::STATUS_PENDING, RefillRequest::STATUS_APPROVED])
            ->get()
            ->first(fn (RefillRequest $r) => ($attributes['prescription_item_id'] && (int) $r->prescription_item_id === (int) $attributes['prescription_item_id'])
                || $matcher->normalizeName($r->medicine_name) === $matcher->normalizeName($attributes['medicine_name']));
        if ($duplicate) {
            return redirect()->route('cabinet.supply')
                ->with('info', "\"{$attributes['medicine_name']}\" đã có yêu cầu bổ sung ({$duplicate->status_label}).");
        }

        $refill = RefillRequest::create(array_merge($attributes, [
            'family_id' => $family->id,
            'requested_by' => $request->user()->id,
            'requester_note' => $validated['requester_note'] ?? null,
            'status' => RefillRequest::STATUS_PENDING,
        ]));

        $hasDoctor = $this->familyHasDoctor($family);
        SmartAlert::create([
            'family_id' => $family->id,
            'user_id' => $request->user()->id,
            'type' => 'refill_request',
            'level' => 'info',
            'title' => "Yêu cầu bổ sung thuốc: {$refill->medicine_name}",
            'content' => "{$request->user()->name} báo \"{$refill->medicine_name}\" sắp hết (còn {$this->qty($refill->current_stock)} {$refill->unit}"
                . ($refill->days_left !== null ? ', đủ khoảng ' . $this->qty($refill->days_left) . ' ngày' : '') . '). '
                . ($hasDoctor ? 'Bác sĩ phụ trách vui lòng kê tiếp hoặc cho ngưng thuốc.' : 'Gia đình chưa có Bác sĩ — Quản trị viên hệ thống sẽ xử lý.'),
            'is_read' => false,
            'action_url' => $this->alertPath($refill),
        ]);

        return redirect()->route('cabinet.supply')->with('success', $hasDoctor
            ? "Đã gửi yêu cầu bổ sung \"{$refill->medicine_name}\" cho Bác sĩ."
            : "Đã gửi yêu cầu bổ sung \"{$refill->medicine_name}\". Gia đình chưa có Bác sĩ nên Quản trị viên hệ thống sẽ xử lý.");
    }

    /**
     * Bác sĩ kê tiếp (thuốc theo đơn) hoặc đồng ý mua thêm (thuốc ngoài đơn)
     */
    public function approve(Request $request, RefillRequest $refillRequest)
    {
        $family = $this->ensureReviewer($request, $refillRequest);
        $validated = $request->validate([
            'approved_quantity' => ['required', 'numeric', 'min:0.5', 'max:999999.99'],
            'extended_until' => ['nullable', 'date', 'after_or_equal:today'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'approved_quantity.required' => 'Vui lòng ghi số lượng kê thêm / được mua thêm.',
        ]);

        if (!$refillRequest->isPending()) {
            return $this->redirectAfterReview($request)->with('warning', 'Yêu cầu này đã được xử lý trước đó.');
        }

        DB::transaction(function () use ($refillRequest, $validated, $request) {
            $item = $refillRequest->prescriptionItem;
            if ($item) {
                // Kê tiếp: cộng thêm vào tổng số lượng kê của thuốc trong đơn → gia đình thấy ngay trong "Cần mua theo đơn"
                $base = max((float) $item->total_prescribed_quantity, $item->purchased_quantity);
                $item->update(['total_prescribed_quantity' => round($base + (float) $validated['approved_quantity'], 2)]);

                if (!empty($validated['extended_until'])) {
                    $until = Carbon::parse($validated['extended_until']);
                    $course = $item->treatmentCourse;
                    if ($course && $course->end_date && $course->end_date->lt($until)) {
                        $course->update(['end_date' => $until]);
                    }
                    MedicationSchedule::where('prescription_item_id', $item->id)
                        ->where('is_active', true)
                        ->whereNotNull('end_date')
                        ->whereDate('end_date', '<', $until)
                        ->update(['end_date' => $until->toDateString()]);
                }
            }

            $refillRequest->update([
                'status' => RefillRequest::STATUS_APPROVED,
                'approved_quantity' => $validated['approved_quantity'],
                'extended_until' => $validated['extended_until'] ?? null,
                'review_note' => $validated['review_note'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        $refillRequest->refresh();
        $this->replaceAlert($refillRequest, 'info',
            ($refillRequest->prescription_item_id ? 'Bác sĩ đã kê tiếp: ' : 'Được mua thêm: ') . $refillRequest->medicine_name,
            "{$request->user()->name} đồng ý bổ sung {$this->qty($refillRequest->approved_quantity)} {$refillRequest->unit} \"{$refillRequest->medicine_name}\". Gia đình mua về rồi bấm \"Đã mua\" trong mục Cần mua & bổ sung."
            . ($refillRequest->review_note ? " Dặn dò: {$refillRequest->review_note}" : ''));
        $this->refreshAlerts($family);

        return $this->redirectAfterReview($request)->with('success', "Đã đồng ý bổ sung \"{$refillRequest->medicine_name}\".");
    }

    /**
     * Bác sĩ cho ngưng thuốc: lịch uống của thuốc này dừng sau hôm nay (lịch sử điểm danh vẫn giữ nguyên)
     */
    public function stop(Request $request, RefillRequest $refillRequest)
    {
        $family = $this->ensureReviewer($request, $refillRequest);
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ], [
            'review_note.required' => 'Vui lòng ghi lý do ngưng thuốc để gia đình hiểu.',
        ]);

        if (!$refillRequest->isPending()) {
            return $this->redirectAfterReview($request)->with('warning', 'Yêu cầu này đã được xử lý trước đó.');
        }

        DB::transaction(function () use ($refillRequest, $validated, $request) {
            if ($refillRequest->prescription_item_id) {
                MedicationSchedule::where('prescription_item_id', $refillRequest->prescription_item_id)
                    ->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>', today()))
                    ->update(['end_date' => today()->toDateString()]);
            }

            $refillRequest->update([
                'status' => RefillRequest::STATUS_STOPPED,
                'review_note' => $validated['review_note'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        $this->replaceAlert($refillRequest, 'warning', "Bác sĩ cho ngưng thuốc: {$refillRequest->medicine_name}",
            "{$request->user()->name} cho ngưng \"{$refillRequest->medicine_name}\", không cần mua thêm. Lý do: {$validated['review_note']}");
        $this->refreshAlerts($family);

        return $this->redirectAfterReview($request)->with('success', "Đã cho ngưng thuốc \"{$refillRequest->medicine_name}\".");
    }

    public function reject(Request $request, RefillRequest $refillRequest)
    {
        $this->ensureReviewer($request, $refillRequest);
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ], [
            'review_note.required' => 'Vui lòng ghi lý do không mua thêm.',
        ]);

        if (!$refillRequest->isPending()) {
            return $this->redirectAfterReview($request)->with('warning', 'Yêu cầu này đã được xử lý trước đó.');
        }

        $refillRequest->update([
            'status' => RefillRequest::STATUS_REJECTED,
            'review_note' => $validated['review_note'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $this->replaceAlert($refillRequest, 'warning', "Không mua thêm: {$refillRequest->medicine_name}",
            "{$request->user()->name} cho biết chưa cần mua thêm \"{$refillRequest->medicine_name}\". Lý do: {$validated['review_note']}");

        return $this->redirectAfterReview($request)->with('success', 'Đã phản hồi yêu cầu bổ sung.');
    }

    public function cancel(Request $request, RefillRequest $refillRequest)
    {
        $family = $this->ensureFamily($request);
        abort_unless((int) $refillRequest->family_id === (int) $family->id, 404);
        abort_unless((int) $refillRequest->requested_by === (int) $request->user()->id, 403, 'Bạn chỉ có thể hủy yêu cầu do chính mình gửi.');

        if (!$refillRequest->isPending()) {
            return back()->with('warning', 'Chỉ hủy được yêu cầu đang chờ Bác sĩ.');
        }

        $refillRequest->update(['status' => RefillRequest::STATUS_CANCELLED]);
        SmartAlert::where('family_id', $family->id)->where('type', 'refill_request')
            ->where('action_url', $this->alertPath($refillRequest))->delete();

        return back()->with('success', 'Đã hủy yêu cầu bổ sung.');
    }

    private function replaceAlert(RefillRequest $refill, string $level, string $title, string $content): void
    {
        SmartAlert::where('family_id', $refill->family_id)->where('type', 'refill_request')
            ->where('action_url', $this->alertPath($refill))->delete();

        SmartAlert::create([
            'family_id' => $refill->family_id,
            'user_id' => $refill->requested_by,
            'type' => 'refill_request',
            'level' => $level,
            'title' => $title,
            'content' => $content,
            'is_read' => false,
            'action_url' => $this->alertPath($refill),
        ]);
    }

    private function alertPath(RefillRequest $refill): string
    {
        return route('cabinet.supply', [], false) . '#refill-' . $refill->id;
    }

    private function familyHasDoctor(Family $family): bool
    {
        return User::where('family_id', $family->id)->where('role', 'doctor_caregiver')->exists();
    }

    private function refreshAlerts(Family $family): void
    {
        try {
            app(AlertScannerService::class)->scanFamily($family);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function redirectAfterReview(Request $request)
    {
        return $request->user()->family_id
            ? redirect()->route('cabinet.supply')
            : redirect()->route('admin.cabinet-requests.index');
    }

    private function ensureFamily(Request $request): Family
    {
        $family = $request->user()->family;
        abort_unless($family, 403, 'Tài khoản chưa thuộc hộ gia đình.');

        return $family;
    }

    /**
     * Người xử lý: Bác sĩ / Người chăm sóc của gia đình, hoặc Quản trị viên hệ thống (xử lý thay mọi gia đình)
     */
    private function ensureReviewer(Request $request, RefillRequest $refillRequest): Family
    {
        $user = $request->user();
        if ($user->isSystemAdmin()) {
            return $refillRequest->family()->firstOrFail();
        }

        $family = $this->ensureFamily($request);
        abort_unless((int) $refillRequest->family_id === (int) $family->id, 404);
        abort_unless($user->canManageTreatments(), 403, 'Chỉ Bác sĩ / Người chăm sóc y tế mới được kê tiếp hoặc cho ngưng thuốc.');

        return $family;
    }

    private function qty(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }
}
