<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\CabinetRequest;
use App\Models\Family;
use App\Models\Medicine;
use App\Models\MedicineExpense;
use App\Models\SmartAlert;
use App\Services\AlertScannerService;
use App\Support\CabinetItemValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Luồng Đề nghị nhập thuốc: Chủ hộ / Thành viên mua thuốc về gửi đề nghị,
 * Bác sĩ (hoặc Quản trị viên) kiểm tra, chỉnh sửa nếu cần rồi duyệt vào tủ hoặc từ chối.
 */
class CabinetRequestController extends Controller
{
    public function index(Request $request)
    {
        $family = $this->ensureFamily($request);
        $user = $request->user();

        $status = $request->query('status');
        $query = CabinetRequest::where('family_id', $family->id)
            ->with(['requester', 'reviewer', 'medicine'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest();

        if (in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $requests = $query->paginate(15)->withQueryString();
        $pendingCount = CabinetRequest::where('family_id', $family->id)->pending()->count();

        return view('cabinet-requests.index', compact('requests', 'pendingCount', 'status', 'user'));
    }

    public function create(Request $request)
    {
        $this->ensureRequester($request);

        $item = new CabinetItem();
        if ($medicineId = $request->query('medicine_id')) {
            $medicine = Medicine::find($medicineId);
            if ($medicine) {
                $item->fill([
                    'medicine_id' => $medicine->id,
                    'custom_name' => $medicine->name,
                    'unit' => $medicine->default_unit ?: 'viên',
                ]);
            }
        }

        return view('cabinet.form', [
            'item' => $item,
            'medicines' => Medicine::with('category')->orderBy('name')->get(),
            'editing' => false,
            'pageTitle' => 'Đề nghị nhập thuốc - Tủ thuốc gia đình',
            'formHeading' => 'Đề nghị nhập thuốc mua thêm vào tủ',
            'formIntro' => 'Khai báo thuốc gia đình vừa mua. Bác sĩ phụ trách sẽ kiểm tra thông tin và duyệt đưa vào tủ thuốc.',
            'formAction' => route('cabinet.requests.store'),
            'backUrl' => route('cabinet.requests.index'),
            'submitLabel' => 'Gửi đề nghị cho Bác sĩ duyệt',
            'extraPartial' => 'cabinet-requests.partials.request-extra',
        ]);
    }

    public function store(Request $request)
    {
        $family = $this->ensureRequester($request);
        $data = CabinetItemValidator::make($request->all(), true)->validate();

        $cabinetRequest = CabinetRequest::create(array_merge($data, [
            'family_id' => $family->id,
            'requested_by' => $request->user()->id,
            'record_expense' => $request->user()->isFamilyAdmin() && $request->boolean('record_expense'),
            'status' => CabinetRequest::STATUS_PENDING,
        ]));

        // Thông báo trong Trung tâm cảnh báo để Bác sĩ thấy có đề nghị chờ duyệt
        SmartAlert::create([
            'family_id' => $family->id,
            'user_id' => $request->user()->id,
            'type' => 'cabinet_request',
            'level' => 'info',
            'title' => "Đề nghị nhập thuốc chờ duyệt: {$cabinetRequest->display_name}",
            'content' => "{$request->user()->name} đề nghị nhập {$this->quantityText($cabinetRequest)} \"{$cabinetRequest->display_name}\" (HSD {$cabinetRequest->expiry_date->format('d/m/Y')}) vào tủ thuốc. Bác sĩ phụ trách vui lòng kiểm tra và duyệt.",
            'is_read' => false,
            'action_url' => $this->reviewPath($cabinetRequest),
        ]);

        return redirect()->route('cabinet.requests.index')
            ->with('success', "Đã gửi đề nghị nhập \"{$cabinetRequest->display_name}\". Bác sĩ sẽ duyệt trước khi thuốc được đưa vào tủ.");
    }

    public function review(Request $request, CabinetRequest $cabinetRequest)
    {
        $this->ensureReviewer($request, $cabinetRequest);

        if (!$cabinetRequest->isPending()) {
            return redirect()->route('cabinet.requests.index')->with('info', 'Đề nghị này đã được xử lý trước đó.');
        }

        return view('cabinet.form', [
            'item' => new CabinetItem($cabinetRequest->itemAttributes()),
            'medicines' => Medicine::with('category')->orderBy('name')->get(),
            'editing' => false,
            'cabinetRequest' => $cabinetRequest->load('requester'),
            'pageTitle' => 'Duyệt đề nghị nhập thuốc - Tủ thuốc gia đình',
            'formHeading' => 'Duyệt đề nghị nhập thuốc: ' . $cabinetRequest->display_name,
            'formIntro' => 'Kiểm tra lại thông tin (tên thuốc, số lô, hạn dùng, số lượng). Bạn có thể chỉnh sửa trước khi duyệt vào tủ.',
            'formAction' => route('cabinet.requests.approve', $cabinetRequest),
            'backUrl' => route('cabinet.requests.index'),
            'submitLabel' => 'Duyệt & nhập vào tủ thuốc',
            'extraPartial' => 'cabinet-requests.partials.review-info',
            'afterFormPartial' => 'cabinet-requests.partials.reject-form',
        ]);
    }

    public function approve(Request $request, CabinetRequest $cabinetRequest)
    {
        $family = $this->ensureReviewer($request, $cabinetRequest);
        $data = CabinetItemValidator::make($request->all(), true)->validate();

        $result = DB::transaction(function () use ($cabinetRequest, $data, $family, $request) {
            $locked = CabinetRequest::whereKey($cabinetRequest->id)->lockForUpdate()->first();
            if (!$locked || !$locked->isPending()) {
                return null;
            }

            $data['family_id'] = $family->id;
            $data['status'] = CabinetItem::computeStatus($data['expiry_date'], $data['quantity']);
            $item = CabinetItem::create($data);

            $expense = null;
            $total = (float) $data['quantity'] * (float) ($data['price_per_unit'] ?? 0);
            if ($locked->record_expense && $total > 0) {
                $expense = MedicineExpense::create([
                    'family_id' => $family->id,
                    'title' => 'Mua thuốc: ' . $item->display_name,
                    'amount' => round($total, 2),
                    'purchase_date' => $locked->created_at->toDateString(),
                    'notes' => "Ghi nhận tự động từ đề nghị nhập thuốc #{$locked->id}",
                ]);
            }

            $locked->update(array_merge($data, [
                'status' => CabinetRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'cabinet_item_id' => $item->id,
                'medicine_expense_id' => $expense?->id,
            ]));

            $this->replaceRequestAlert($locked, 'info', "Đã duyệt nhập thuốc: {$item->display_name}",
                "{$request->user()->name} đã duyệt đưa {$this->quantityText($locked)} \"{$item->display_name}\" vào tủ thuốc"
                . ($expense ? ' và ghi nhận chi phí ' . number_format($total, 0, ',', '.') . ' đ.' : '.'),
                route('cabinet.index', [], false));

            return $item;
        });

        if (!$result) {
            return redirect()->route('cabinet.requests.index')->with('warning', 'Đề nghị này đã được xử lý trước đó.');
        }

        $this->refreshAlerts($family);

        return redirect()->route('cabinet.requests.index')
            ->with('success', "Đã duyệt và nhập \"{$result->display_name}\" vào tủ thuốc gia đình.");
    }

    public function reject(Request $request, CabinetRequest $cabinetRequest)
    {
        $this->ensureReviewer($request, $cabinetRequest);
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ], [
            'review_note.required' => 'Vui lòng ghi lý do từ chối để gia đình biết cần bổ sung gì.',
        ]);

        if (!$cabinetRequest->isPending()) {
            return redirect()->route('cabinet.requests.index')->with('warning', 'Đề nghị này đã được xử lý trước đó.');
        }

        $cabinetRequest->update([
            'status' => CabinetRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'],
        ]);

        $this->replaceRequestAlert($cabinetRequest, 'warning', "Từ chối đề nghị nhập thuốc: {$cabinetRequest->display_name}",
            "{$request->user()->name} đã từ chối đề nghị nhập \"{$cabinetRequest->display_name}\". Lý do: {$validated['review_note']}",
            route('cabinet.requests.index', [], false));

        return redirect()->route('cabinet.requests.index')->with('success', 'Đã từ chối đề nghị nhập thuốc.');
    }

    public function cancel(Request $request, CabinetRequest $cabinetRequest)
    {
        $this->ensureFamilyOwns($request, $cabinetRequest);
        abort_unless((int) $cabinetRequest->requested_by === (int) $request->user()->id, 403, 'Bạn chỉ có thể hủy đề nghị do chính mình gửi.');

        if (!$cabinetRequest->isPending()) {
            return back()->with('warning', 'Chỉ hủy được đề nghị đang chờ duyệt.');
        }

        $cabinetRequest->update(['status' => CabinetRequest::STATUS_CANCELLED]);
        SmartAlert::where('family_id', $cabinetRequest->family_id)
            ->where('type', 'cabinet_request')
            ->where('action_url', $this->reviewPath($cabinetRequest))
            ->delete();

        return back()->with('success', 'Đã hủy đề nghị nhập thuốc.');
    }

    /**
     * Thay cảnh báo "chờ duyệt" bằng thông báo kết quả cho gia đình
     */
    private function replaceRequestAlert(CabinetRequest $cabinetRequest, string $level, string $title, string $content, string $actionUrl): void
    {
        SmartAlert::where('family_id', $cabinetRequest->family_id)
            ->where('type', 'cabinet_request')
            ->where('action_url', $this->reviewPath($cabinetRequest))
            ->delete();

        SmartAlert::create([
            'family_id' => $cabinetRequest->family_id,
            'user_id' => $cabinetRequest->requested_by,
            'type' => 'cabinet_request',
            'level' => $level,
            'title' => $title,
            'content' => $content,
            'is_read' => false,
            'action_url' => $actionUrl,
        ]);
    }

    private function reviewPath(CabinetRequest $cabinetRequest): string
    {
        return route('cabinet.requests.review', $cabinetRequest, false);
    }

    private function quantityText(CabinetRequest $cabinetRequest): string
    {
        return rtrim(rtrim(number_format((float) $cabinetRequest->quantity, 2, ',', '.'), '0'), ',') . ' ' . $cabinetRequest->unit;
    }

    private function refreshAlerts(Family $family): void
    {
        try {
            app(AlertScannerService::class)->scanFamily($family);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function ensureFamily(Request $request): Family
    {
        $family = $request->user()->family;
        abort_unless($family, 403, 'Tài khoản chưa thuộc hộ gia đình.');

        return $family;
    }

    private function ensureRequester(Request $request): Family
    {
        $family = $this->ensureFamily($request);
        abort_unless($request->user()->canRequestCabinetItems(), 403, 'Bác sĩ / Quản trị viên nhập thuốc trực tiếp vào tủ, không cần gửi đề nghị.');

        return $family;
    }

    private function ensureFamilyOwns(Request $request, CabinetRequest $cabinetRequest): Family
    {
        $family = $this->ensureFamily($request);
        abort_unless((int) $cabinetRequest->family_id === (int) $family->id, 404);

        return $family;
    }

    private function ensureReviewer(Request $request, CabinetRequest $cabinetRequest): Family
    {
        $family = $this->ensureFamilyOwns($request, $cabinetRequest);
        abort_unless($request->user()->canManageCabinet(), 403, 'Chỉ Bác sĩ / Người chăm sóc y tế hoặc Quản trị viên mới được duyệt đề nghị nhập thuốc.');

        return $family;
    }
}
