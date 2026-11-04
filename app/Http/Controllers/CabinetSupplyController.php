<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\CabinetRequest;
use App\Models\RefillRequest;
use App\Models\User;
use App\Services\CabinetStockMatcher;
use App\Services\MedicationSupplyService;
use Illuminate\Http\Request;

/**
 * Trang "Cần mua & bổ sung" — một chỗ để cả nhà biết phải mua gì:
 * thuốc Bác sĩ đã kê chưa mua, thuốc đang uống sắp hết, thuốc trong tủ sắp hết / hết hạn, và các yêu cầu bổ sung.
 */
class CabinetSupplyController extends Controller
{
    public function index(Request $request, MedicationSupplyService $supply, CabinetStockMatcher $matcher)
    {
        $user = $request->user();
        $family = $user->family;
        abort_unless($family, 403, 'Tài khoản chưa thuộc hộ gia đình.');

        $purchaseList = $supply->purchaseList($family);
        $supplyRows = $supply->forFamily($family);

        $awaitingRefills = RefillRequest::where('family_id', $family->id)->awaitingPurchase()->latest()->get();
        $refills = RefillRequest::where('family_id', $family->id)
            ->with(['requester', 'reviewer', 'prescriptionItem.treatmentCourse', 'familyMember'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->take(30)
            ->get();
        $openRefillNames = $refills->filter(fn (RefillRequest $r) => $r->isOpen())
            ->map(fn (RefillRequest $r) => $matcher->normalizeName($r->medicine_name))
            ->all();

        // Thuốc trong tủ sắp hết / hết hạn nhưng không nằm trong lịch uống (thuốc dùng khi cần, vật tư y tế...)
        $scheduledNames = $supplyRows->pluck('medicine_name')->map(fn ($n) => $matcher->normalizeName($n))->all();
        $scheduledMedicineIds = $supplyRows->pluck('medicine_id')->filter()->all();
        $cabinetLow = CabinetItem::where('family_id', $family->id)
            ->with('medicine')
            ->where(fn ($q) => $q->where('quantity', '<=', 5)->orWhereDate('expiry_date', '<', today()))
            ->orderBy('quantity')
            ->orderBy('expiry_date')
            ->get()
            ->reject(fn (CabinetItem $item) => in_array($matcher->normalizeName($item->display_name), $scheduledNames, true)
                || ($item->medicine_id && in_array($item->medicine_id, $scheduledMedicineIds)))
            ->map(fn (CabinetItem $item) => [
                'item' => $item,
                'has_open_refill' => in_array($matcher->normalizeName($item->display_name), $openRefillNames, true),
            ])
            ->values();

        // Thuốc đã mua chờ Bác sĩ xác nhận, và thuốc vừa bị trả lại (để gia đình đổi / mua lại)
        $pendingLots = CabinetItem::withAnyApproval()
            ->where('family_id', $family->id)
            ->where('approval_status', CabinetItem::APPROVAL_PENDING)
            ->with(['purchaser', 'prescriptionItem.treatmentCourse.familyMember', 'medicine'])
            ->oldest()
            ->get();
        $returnedLots = CabinetItem::withAnyApproval()
            ->where('family_id', $family->id)
            ->where('approval_status', CabinetItem::APPROVAL_RETURNED)
            ->where('approved_at', '>=', now()->subDays(14))
            ->with('approver')
            ->latest('approved_at')
            ->get();

        $hasDoctor = User::where('family_id', $family->id)->where('role', 'doctor_caregiver')->exists();
        $pendingCabinetRequests = CabinetRequest::where('family_id', $family->id)->pending()->count();

        return view('cabinet-supply.index', compact(
            'user',
            'pendingLots',
            'returnedLots',
            'purchaseList',
            'supplyRows',
            'awaitingRefills',
            'refills',
            'cabinetLow',
            'hasDoctor',
            'pendingCabinetRequests'
        ));
    }
}
