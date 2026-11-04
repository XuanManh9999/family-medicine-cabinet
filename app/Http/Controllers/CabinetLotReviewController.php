<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\RefillRequest;
use App\Models\SmartAlert;
use App\Services\AlertScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Bác sĩ xác nhận thuốc gia đình đã mua về (đúng thuốc, đúng hàm lượng, còn hạn) trước khi được dùng,
 * hoặc trả lại kèm lý do để gia đình đổi / mua lại. Gia đình chưa có Bác sĩ thì Quản trị viên xác nhận thay.
 */
class CabinetLotReviewController extends Controller
{
    public function confirm(Request $request, int $lot)
    {
        $item = $this->findPendingLot($lot);
        $family = $this->ensureReviewer($request, $item);
        // Kiểm tra chéo: người mua không tự xác nhận thuốc mình mua
        abort_if((int) $item->purchased_by === (int) $request->user()->id, 403,
            'Bạn là người mua lô thuốc này nên không tự xác nhận được. Cần Bác sĩ khác hoặc Quản trị viên hệ thống xác nhận.');
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($item->is_expired) {
            return back()->withErrors(['review_note' => "\"{$item->display_name}\" đã quá hạn sử dụng ({$item->expiry_date->format('d/m/Y')}) — không thể xác nhận, hãy trả lại để gia đình đổi thuốc."]);
        }

        $confirmed = DB::transaction(function () use ($item, $request, $validated) {
            $locked = CabinetItem::withAnyApproval()->whereKey($item->id)->lockForUpdate()->first();
            if (!$locked || !$locked->isPendingReview()) {
                return false;
            }

            return $locked->update([
                'approval_status' => CabinetItem::APPROVAL_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'review_note' => $validated['review_note'] ?? null,
                'notes' => trim(($item->notes ?? '') . (!empty($validated['review_note']) ? "\nDặn dò của Bác sĩ: {$validated['review_note']}" : '')),
            ]);
        });

        if (!$confirmed) {
            return $this->redirectAfterReview($request)->with('warning', 'Lô thuốc này đã được xử lý trước đó.');
        }
        $item->refresh();

        $this->replaceAlert($item, 'info', "Đã xác nhận, được dùng: {$item->display_name}",
            "{$request->user()->name} xác nhận \"{$item->display_name}\" ({$this->qty($item->quantity)} {$item->unit}, HSD {$item->expiry_date->format('d/m/Y')}) đúng thuốc. Thuốc đã có trong tủ và được dùng theo lịch."
            . (!empty($validated['review_note']) ? " Dặn dò: {$validated['review_note']}" : ''));
        $this->refreshAlerts($family);

        return $this->redirectAfterReview($request)->with('success', "Đã xác nhận \"{$item->display_name}\" — thuốc được đưa vào tủ.");
    }

    public function returnLot(Request $request, int $lot)
    {
        $item = $this->findPendingLot($lot);
        $family = $this->ensureReviewer($request, $item);
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ], [
            'review_note.required' => 'Vui lòng ghi lý do trả lại để gia đình biết cần đổi thuốc gì.',
        ]);

        $returned = DB::transaction(function () use ($item, $validated, $request) {
            $locked = CabinetItem::withAnyApproval()->whereKey($item->id)->lockForUpdate()->first();
            if (!$locked || !$locked->isPendingReview()) {
                return false;
            }

            $locked->update([
                'approval_status' => CabinetItem::APPROVAL_RETURNED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'review_note' => $validated['review_note'],
            ]);

            // Thuốc bị trả lại → gia đình cần mua lại: chỉ mở lại đúng yêu cầu bổ sung mà lô này đáp ứng
            if ($locked->refill_request_id) {
                RefillRequest::whereKey($locked->refill_request_id)
                    ->where('status', RefillRequest::STATUS_FULFILLED)
                    ->update(['status' => RefillRequest::STATUS_APPROVED]);
            }

            // Đổi thuốc ở hiệu thuốc không phải khoản chi mới: gỡ chi phí đã ghi lúc bấm "Đã mua"
            if ($locked->medicine_expense_id) {
                \App\Models\MedicineExpense::whereKey($locked->medicine_expense_id)->delete();
            }

            return true;
        });

        if (!$returned) {
            return $this->redirectAfterReview($request)->with('warning', 'Lô thuốc này đã được xử lý trước đó.');
        }

        $this->replaceAlert($item, 'warning', "Bác sĩ trả lại thuốc: {$item->display_name}",
            "{$request->user()->name} chưa đồng ý dùng \"{$item->display_name}\" vừa mua. Lý do: {$validated['review_note']}. Hãy đổi lại ở hiệu thuốc rồi bấm \"Đã mua\" lần nữa.");
        $this->refreshAlerts($family);

        return $this->redirectAfterReview($request)->with('success', "Đã trả lại \"{$item->display_name}\" kèm lý do cho gia đình.");
    }

    /**
     * Ảnh vỏ hộp của lô thuốc (chỉ người trong gia đình hoặc Quản trị viên hệ thống)
     */
    public function photo(Request $request, int $lot, int $index)
    {
        $item = CabinetItem::withAnyApproval()->findOrFail($lot);
        $user = $request->user();
        abort_unless($user->isSystemAdmin() || (int) $user->family_id === (int) $item->family_id, 404);

        $path = ($item->box_image_paths ?? [])[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    private function findPendingLot(int $lot): CabinetItem
    {
        $item = CabinetItem::withAnyApproval()->findOrFail($lot);
        abort_unless($item->isPendingReview(), 409, 'Lô thuốc này đã được xử lý trước đó.');

        return $item;
    }

    /**
     * Người xác nhận: Bác sĩ / Người chăm sóc của chính gia đình, hoặc Quản trị viên hệ thống
     */
    private function ensureReviewer(Request $request, CabinetItem $item): Family
    {
        $user = $request->user();
        if ($user->isSystemAdmin()) {
            return $item->family()->firstOrFail();
        }

        $family = $user->family;
        abort_unless($family && (int) $family->id === (int) $item->family_id, 404);
        abort_unless($user->canManageCabinet(), 403, 'Chỉ Bác sĩ / Người chăm sóc y tế mới được xác nhận thuốc trước khi dùng.');

        return $family;
    }

    private function replaceAlert(CabinetItem $item, string $level, string $title, string $content): void
    {
        $path = route('cabinet.supply', [], false) . '#lot-' . $item->id;
        SmartAlert::where('family_id', $item->family_id)->where('type', 'purchase_review')->where('action_url', $path)->delete();

        SmartAlert::create([
            'family_id' => $item->family_id,
            'user_id' => $item->purchased_by,
            'type' => 'purchase_review',
            'level' => $level,
            'title' => $title,
            'content' => $content,
            'is_read' => false,
            'action_url' => $path,
        ]);
    }

    private function redirectAfterReview(Request $request)
    {
        return $request->user()->family_id
            ? redirect()->route('cabinet.supply')
            : redirect()->route('admin.cabinet-requests.index');
    }

    private function refreshAlerts(Family $family): void
    {
        try {
            app(AlertScannerService::class)->scanFamily($family);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function qty(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }
}
