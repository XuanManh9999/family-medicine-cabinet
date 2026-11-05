<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\MedicineExpense;
use App\Models\PrescriptionItem;
use App\Models\RefillRequest;
use App\Models\SmartAlert;
use App\Models\User;
use App\Services\AlertScannerService;
use App\Services\CabinetStockMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Đã mua về": thuốc do Bác sĩ đã kê (hoặc đã đồng ý mua thêm) nên gia đình chỉ cần ghi
 * số lượng thực mua + hạn sử dụng in trên vỏ, thuốc vào tủ ngay, không cần duyệt lại.
 */
class CabinetPurchaseController extends Controller
{
    public function createForPrescription(Request $request, PrescriptionItem $prescriptionItem)
    {
        $family = $this->authorizePrescriptionItem($request, $prescriptionItem);
        if ($reason = $this->purchaseClosedReason($prescriptionItem)) {
            return redirect()->route('cabinet.supply')->with('warning', $reason);
        }
        $prescriptionItem->load(['treatmentCourse.familyMember', 'cabinetItems', 'medicine']);

        $existingStock = app(CabinetStockMatcher::class)
            ->candidates($family->id, null, $prescriptionItem->medicine_id, $prescriptionItem->medicine_name)
            ->filter(fn (CabinetItem $lot) => $lot->prescription_item_id === null)
            ->values();

        return view('cabinet-purchases.create', [
            'target' => 'prescription',
            'prescriptionItem' => $prescriptionItem,
            'refill' => null,
            'medicineName' => $prescriptionItem->medicine_name,
            'unit' => $prescriptionItem->unit ?: ($prescriptionItem->medicine?->default_unit ?: 'viên'),
            'suggestedQuantity' => $prescriptionItem->remaining_to_buy,
            'existingStock' => $existingStock,
            'formAction' => route('cabinet.purchases.prescription.store', $prescriptionItem),
            'canRecordExpense' => $this->canRecordExpense($request),
        ]);
    }

    public function storeForPrescription(Request $request, PrescriptionItem $prescriptionItem)
    {
        $family = $this->authorizePrescriptionItem($request, $prescriptionItem);
        $data = $this->validatePurchase($request);
        $course = $prescriptionItem->treatmentCourse;

        $item = DB::transaction(function () use ($request, $family, $prescriptionItem, $course, $data) {
            // Khóa dòng thuốc trong đơn: bấm "Đã mua" 2 lần / 2 người cùng ghi nhận không tạo lô + chi phí trùng
            $locked = PrescriptionItem::whereKey($prescriptionItem->id)->lockForUpdate()->first();
            if (!$locked || $this->purchaseClosedReason($locked) !== null) {
                return null;
            }

            $item = $this->createLot($request, $family, $data, [
                'refill_request_id' => RefillRequest::where('prescription_item_id', $prescriptionItem->id)
                    ->where('status', RefillRequest::STATUS_APPROVED)->latest('id')->value('id'),
                'medicine_id' => $prescriptionItem->medicine_id,
                'prescription_item_id' => $prescriptionItem->id,
                'custom_name' => $prescriptionItem->medicine_name,
                'notes' => trim("Mua theo đơn: {$course->title}. " . ($prescriptionItem->instructions ?? '')),
            ]);

            $this->recordExpense($request, $family, $item, $data, $course->id, $course->family_member_id);

            $this->fulfillPrescriptionRefills($prescriptionItem);

            return $item;
        });

        if (!$item) {
            return $this->redirectBack($request)->with('warning',
                $this->purchaseClosedReason($prescriptionItem->fresh()) ?? 'Thuốc này đã được ghi nhận mua trước đó.');
        }

        $this->refreshAlerts($family);

        $this->notifyReviewer($request, $family, $item, "theo đơn \"{$course->title}\"");

        return $this->redirectBack($request)
            ->with('success', "Đã ghi nhận mua {$this->qty($item->quantity)} {$item->unit} \"{$item->display_name}\" theo đơn \"{$course->title}\". " . $this->waitingText($family));
    }

    /**
     * Thuốc theo đơn đã có sẵn trong tủ (mua từ trước) → gắn lô đó vào đơn, không cần nhập lại
     */
    public function linkExisting(Request $request, PrescriptionItem $prescriptionItem)
    {
        $family = $this->authorizePrescriptionItem($request, $prescriptionItem);
        $validated = $request->validate(['cabinet_item_id' => ['required', 'integer']]);

        // Chỉ chấp nhận lô thực sự là thuốc này (cùng thuốc / cùng tên), còn hàng, còn hạn, chưa gắn đơn khác
        $lot = app(CabinetStockMatcher::class)
            ->candidates($family->id, null, $prescriptionItem->medicine_id, $prescriptionItem->medicine_name)
            ->filter(fn (CabinetItem $candidate) => $candidate->prescription_item_id === null)
            ->firstWhere('id', (int) $validated['cabinet_item_id']);
        abort_unless($lot, 422, 'Lô thuốc này không phải thuốc trong đơn, đã hết hạn hoặc đã hết hàng.');

        DB::transaction(function () use ($lot, $prescriptionItem) {
            $lot->update([
                'prescription_item_id' => $prescriptionItem->id,
                'purchased_quantity' => $lot->purchased_quantity ?? $lot->quantity,
            ]);
            $this->fulfillPrescriptionRefills($prescriptionItem);
        });

        $this->refreshAlerts($family);

        return $this->redirectBack($request)
            ->with('success', "Đã dùng lô \"{$lot->display_name}\" có sẵn trong tủ cho đơn thuốc.");
    }

    public function createForRefill(Request $request, RefillRequest $refillRequest)
    {
        $this->authorizeRefill($request, $refillRequest);
        $refillRequest->load(['cabinetItem', 'medicine']);

        return view('cabinet-purchases.create', [
            'target' => 'refill',
            'prescriptionItem' => null,
            'refill' => $refillRequest,
            'medicineName' => $refillRequest->medicine_name,
            'unit' => $refillRequest->unit ?: 'viên',
            'suggestedQuantity' => $refillRequest->approved_quantity ? (float) $refillRequest->approved_quantity : null,
            'existingStock' => collect(),
            'formAction' => route('cabinet.purchases.refill.store', $refillRequest),
            'canRecordExpense' => $this->canRecordExpense($request),
        ]);
    }

    public function storeForRefill(Request $request, RefillRequest $refillRequest)
    {
        $family = $this->authorizeRefill($request, $refillRequest);
        $data = $this->validatePurchase($request);

        $item = DB::transaction(function () use ($request, $family, $refillRequest, $data) {
            $locked = RefillRequest::whereKey($refillRequest->id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== RefillRequest::STATUS_APPROVED) {
                return null;
            }
            $previousLot = $refillRequest->cabinetItem;
            $item = $this->createLot($request, $family, $data, [
                'refill_request_id' => $refillRequest->id,
                'medicine_id' => $refillRequest->medicine_id ?? $previousLot?->medicine_id,
                'custom_name' => $refillRequest->medicine_name,
                'notes' => $previousLot?->notes ?: 'Mua bổ sung theo yêu cầu đã được Bác sĩ đồng ý.',
                'storage_location' => $data['storage_location'] ?? $previousLot?->storage_location,
            ]);

            $this->recordExpense($request, $family, $item, $data, null, $refillRequest->family_member_id);
            $locked->update(['status' => RefillRequest::STATUS_FULFILLED]);

            return $item;
        });

        if (!$item) {
            return $this->redirectBack($request)->with('warning', 'Thuốc bổ sung này đã được ghi nhận mua trước đó.');
        }

        $this->refreshAlerts($family);

        $this->notifyReviewer($request, $family, $item, 'bổ sung theo yêu cầu đã được đồng ý');

        return $this->redirectBack($request)
            ->with('success', "Đã ghi nhận mua bổ sung {$this->qty($item->quantity)} {$item->unit} \"{$item->display_name}\". " . $this->waitingText($family));
    }

    private function validatePurchase(Request $request): array
    {
        return $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.5', 'max:999999.99'],
            'unit' => ['required', 'string', 'max:30'],
            'expiry_date' => ['required', 'date', 'after_or_equal:today'],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'price_per_unit' => ['nullable', 'numeric', 'min:0', 'max:999999999.99', function ($attribute, $value, $fail) use ($request) {
                // Thành tiền ghi vào sổ chi tiêu (decimal(12,2)) — gõ nhầm đơn giá quá lớn sẽ gây lỗi CSDL
                if (is_numeric($value) && is_numeric($request->input('quantity'))
                    && (float) $value * (float) $request->input('quantity') > 9999999999.99) {
                    $fail('Thành tiền (số lượng × đơn giá) quá lớn, vui lòng kiểm tra lại đơn giá.');
                }
            }],
            'box_photos' => ['nullable', 'array', 'max:3'],
            'box_photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ], [
            'box_photos.max' => 'Chỉ gửi tối đa 3 ảnh vỏ hộp.',
            'box_photos.*.image' => 'Ảnh vỏ hộp phải là hình ảnh (JPG, PNG, WEBP).',
            'box_photos.*.max' => 'Mỗi ảnh vỏ hộp tối đa 5MB.',
            'quantity.required' => 'Vui lòng nhập số lượng thực mua.',
            'quantity.min' => 'Số lượng tối thiểu là 0,5.',
            'expiry_date.required' => 'Vui lòng nhập hạn sử dụng in trên vỏ hộp (HSD / EXP).',
            'expiry_date.after_or_equal' => 'Thuốc đã quá hạn sử dụng — không nên đưa vào tủ thuốc. Hãy đổi lại ở hiệu thuốc.',
        ]);
    }

    /**
     * Lô thuốc mới mua: CHỜ BÁC SĨ XÁC NHẬN (chưa nằm trong tủ, chưa được dùng)
     */
    private function createLot(Request $request, Family $family, array $data, array $extra): CabinetItem
    {
        $photoPaths = [];
        foreach ($request->file('box_photos', []) as $photo) {
            $photoPaths[] = $photo->store("cabinet-lots/{$family->id}", 'local');
        }

        $attributes = array_merge([
            'family_id' => $family->id,
            'approval_status' => CabinetItem::APPROVAL_PENDING,
            'purchased_by' => $request->user()->id,
            'box_image_paths' => $photoPaths ?: null,
            'batch_number' => $data['batch_number'] ?? null,
            'expiry_date' => $data['expiry_date'],
            'quantity' => $data['quantity'],
            'purchased_quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'storage_location' => $data['storage_location'] ?? null,
            'price_per_unit' => $data['price_per_unit'] ?? null,
        ], array_filter($extra, fn ($value) => $value !== null));
        $attributes['status'] = CabinetItem::computeStatus($attributes['expiry_date'], $attributes['quantity']);

        return CabinetItem::create($attributes);
    }

    private function recordExpense(Request $request, Family $family, CabinetItem $item, array $data, ?int $courseId, ?int $memberId): void
    {
        $total = (float) $data['quantity'] * (float) ($data['price_per_unit'] ?? 0);
        if (!$this->canRecordExpense($request) || !$request->boolean('record_expense') || $total <= 0) {
            return;
        }

        $expense = MedicineExpense::create([
            'family_id' => $family->id,
            'treatment_course_id' => $courseId,
            'family_member_id' => $memberId,
            'title' => 'Mua thuốc: ' . $item->display_name,
            'amount' => round($total, 2),
            'purchase_date' => today()->toDateString(),
            'notes' => 'Ghi nhận tự động khi nhập thuốc vào tủ',
        ]);
        $item->update(['medicine_expense_id' => $expense->id]);
    }

    /**
     * Đã mua đủ theo đơn (kể cả lô đang chờ xác nhận) → các yêu cầu kê tiếp của thuốc này hoàn tất
     */
    private function fulfillPrescriptionRefills(PrescriptionItem $prescriptionItem): void
    {
        if (!$prescriptionItem->fresh()->needs_purchase) {
            RefillRequest::where('prescription_item_id', $prescriptionItem->id)
                ->where('status', RefillRequest::STATUS_APPROVED)
                ->update(['status' => RefillRequest::STATUS_FULFILLED]);
        }
    }

    /**
     * Báo Bác sĩ (hoặc Quản trị viên nếu gia đình chưa có Bác sĩ) có thuốc mới mua cần xác nhận
     */
    private function notifyReviewer(Request $request, Family $family, CabinetItem $item, string $context): void
    {
        $photos = count($item->box_image_paths ?? []);

        SmartAlert::create([
            'family_id' => $family->id,
            'user_id' => $request->user()->id,
            'type' => 'purchase_review',
            'level' => 'info',
            'title' => "Thuốc mới mua chờ xác nhận: {$item->display_name}",
            'content' => "{$request->user()->name} đã mua {$this->qty($item->quantity)} {$item->unit} \"{$item->display_name}\" {$context}"
                . " (HSD {$item->expiry_date->format('d/m/Y')}" . ($photos ? ", kèm {$photos} ảnh vỏ hộp" : '') . '). '
                . ($this->familyHasDoctor($family) ? 'Bác sĩ vui lòng kiểm tra đúng thuốc, đúng hàm lượng rồi xác nhận.' : 'Gia đình chưa có Bác sĩ — Quản trị viên hệ thống sẽ xác nhận thay.'),
            'is_read' => false,
            'action_url' => route('cabinet.supply', [], false) . '#lot-' . $item->id,
        ]);
    }

    private function waitingText(Family $family): string
    {
        return $this->familyHasDoctor($family)
            ? 'Thuốc sẽ được dùng sau khi Bác sĩ xác nhận đúng thuốc.'
            : 'Gia đình chưa có Bác sĩ nên Quản trị viên hệ thống sẽ xác nhận thay trước khi dùng.';
    }

    private function familyHasDoctor(Family $family): bool
    {
        return User::where('family_id', $family->id)->where('role', 'doctor_caregiver')->exists();
    }

    private function canRecordExpense(Request $request): bool
    {
        $user = $request->user();

        // Ghi chi phí vào sổ chi tiêu chỉ dành cho Chủ hộ (người quản lý sổ chi tiêu gia đình)
        return $user->isFamilyAdmin() || $user->isSystemAdmin();
    }

    private function authorizePrescriptionItem(Request $request, PrescriptionItem $prescriptionItem): Family
    {
        $family = $this->ensureFamily($request);
        abort_unless((int) $prescriptionItem->treatmentCourse?->family_id === (int) $family->id, 404);

        return $family;
    }

    /**
     * Lý do không cho ghi nhận mua theo đơn nữa (null = vẫn được mua):
     * Bác sĩ đã cho ngưng thuốc, đợt điều trị không còn đang điều trị, hoặc đã mua đủ số lượng kê.
     */
    private function purchaseClosedReason(PrescriptionItem $prescriptionItem): ?string
    {
        if ($prescriptionItem->stopped_at !== null) {
            return "Bác sĩ đã cho ngưng thuốc \"{$prescriptionItem->medicine_name}\" — không cần mua thêm.";
        }
        if ($prescriptionItem->treatmentCourse?->status !== 'dang_dieu_tri') {
            return 'Đợt điều trị này không còn đang điều trị — không cần mua thêm thuốc theo đơn.';
        }
        if (!$prescriptionItem->needs_purchase) {
            return "Thuốc \"{$prescriptionItem->medicine_name}\" đã được mua đủ theo đơn (kể cả lô đang chờ Bác sĩ xác nhận).";
        }

        return null;
    }

    private function authorizeRefill(Request $request, RefillRequest $refillRequest): Family
    {
        $family = $this->ensureFamily($request);
        abort_unless((int) $refillRequest->family_id === (int) $family->id, 404);
        abort_unless($refillRequest->status === RefillRequest::STATUS_APPROVED && $refillRequest->prescription_item_id === null, 403,
            'Yêu cầu này chưa được Bác sĩ đồng ý mua thêm hoặc đã được xử lý.');

        return $family;
    }

    private function ensureFamily(Request $request): Family
    {
        $family = $request->user()->family;
        abort_unless($family, 403, 'Tài khoản chưa thuộc hộ gia đình.');

        return $family;
    }

    private function redirectBack(Request $request)
    {
        $back = $request->input('return_to');

        return is_string($back) && str_starts_with($back, '/') && !str_starts_with($back, '//')
            ? redirect($back)
            : redirect()->route('cabinet.supply');
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
