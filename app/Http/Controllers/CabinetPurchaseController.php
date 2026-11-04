<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\MedicineExpense;
use App\Models\PrescriptionItem;
use App\Models\RefillRequest;
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
            $item = $this->createLot($family, $data, [
                'medicine_id' => $prescriptionItem->medicine_id,
                'prescription_item_id' => $prescriptionItem->id,
                'custom_name' => $prescriptionItem->medicine_name,
                'notes' => trim("Mua theo đơn: {$course->title}. " . ($prescriptionItem->instructions ?? '')),
            ]);

            $this->recordExpense($request, $family, $item, $data, $course->id, $course->family_member_id);

            // Đã mua đủ theo đơn → các yêu cầu bổ sung đã được kê tiếp của thuốc này coi như hoàn tất
            if (!$prescriptionItem->fresh()->needs_purchase) {
                RefillRequest::where('prescription_item_id', $prescriptionItem->id)
                    ->where('status', RefillRequest::STATUS_APPROVED)
                    ->update(['status' => RefillRequest::STATUS_FULFILLED]);
            }

            return $item;
        });

        $this->refreshAlerts($family);

        return $this->redirectBack($request)
            ->with('success', "Đã nhập {$this->qty($item->quantity)} {$item->unit} \"{$item->display_name}\" vào tủ thuốc theo đơn \"{$course->title}\".");
    }

    /**
     * Thuốc theo đơn đã có sẵn trong tủ (mua từ trước) → gắn lô đó vào đơn, không cần nhập lại
     */
    public function linkExisting(Request $request, PrescriptionItem $prescriptionItem)
    {
        $family = $this->authorizePrescriptionItem($request, $prescriptionItem);
        $validated = $request->validate(['cabinet_item_id' => ['required', 'integer']]);

        $lot = CabinetItem::where('family_id', $family->id)
            ->whereNull('prescription_item_id')
            ->findOrFail($validated['cabinet_item_id']);

        $lot->update([
            'prescription_item_id' => $prescriptionItem->id,
            'purchased_quantity' => $lot->purchased_quantity ?? $lot->quantity,
        ]);

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
            $previousLot = $refillRequest->cabinetItem;
            $item = $this->createLot($family, $data, [
                'medicine_id' => $refillRequest->medicine_id ?? $previousLot?->medicine_id,
                'custom_name' => $refillRequest->medicine_name,
                'notes' => $previousLot?->notes ?: 'Mua bổ sung theo yêu cầu đã được Bác sĩ đồng ý.',
                'storage_location' => $data['storage_location'] ?? $previousLot?->storage_location,
            ]);

            $this->recordExpense($request, $family, $item, $data, null, $refillRequest->family_member_id);
            $refillRequest->update(['status' => RefillRequest::STATUS_FULFILLED]);

            return $item;
        });

        $this->refreshAlerts($family);

        return $this->redirectBack($request)
            ->with('success', "Đã nhập {$this->qty($item->quantity)} {$item->unit} \"{$item->display_name}\" mua bổ sung vào tủ thuốc.");
    }

    private function validatePurchase(Request $request): array
    {
        return $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.5', 'max:999999.99'],
            'unit' => ['required', 'string', 'max:30'],
            'expiry_date' => ['required', 'date', 'after_or_equal:today'],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'price_per_unit' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ], [
            'quantity.required' => 'Vui lòng nhập số lượng thực mua.',
            'quantity.min' => 'Số lượng tối thiểu là 0,5.',
            'expiry_date.required' => 'Vui lòng nhập hạn sử dụng in trên vỏ hộp (HSD / EXP).',
            'expiry_date.after_or_equal' => 'Thuốc đã quá hạn sử dụng — không nên đưa vào tủ thuốc. Hãy đổi lại ở hiệu thuốc.',
        ]);
    }

    private function createLot(Family $family, array $data, array $extra): CabinetItem
    {
        $attributes = array_merge([
            'family_id' => $family->id,
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

        MedicineExpense::create([
            'family_id' => $family->id,
            'treatment_course_id' => $courseId,
            'family_member_id' => $memberId,
            'title' => 'Mua thuốc: ' . $item->display_name,
            'amount' => round($total, 2),
            'purchase_date' => today()->toDateString(),
            'notes' => 'Ghi nhận tự động khi nhập thuốc vào tủ',
        ]);
    }

    private function canRecordExpense(Request $request): bool
    {
        $user = $request->user();

        return $user->isFamilyAdmin() || $user->isDoctorOrCaregiver() || $user->isSystemAdmin();
    }

    private function authorizePrescriptionItem(Request $request, PrescriptionItem $prescriptionItem): Family
    {
        $family = $this->ensureFamily($request);
        abort_unless((int) $prescriptionItem->treatmentCourse?->family_id === (int) $family->id, 404);

        return $family;
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
