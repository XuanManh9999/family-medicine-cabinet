<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\SmartAlert;
use App\Services\AlertScannerService;
use Illuminate\Http\Request;

class SmartAlertController extends Controller
{
    /**
     * Bảng điều khiển Trung tâm Cảnh báo Thông minh
     */
    public function index(Request $request)
    {
        $family = $this->ensureFamily($request);

        $level = $request->query('level');
        $type = $request->query('type');
        $status = $request->query('status', 'all'); // 'all', 'unread', 'read'
        $search = trim((string)$request->query('q'));

        $query = $family->smartAlerts();

        if ($level && in_array($level, ['danger', 'warning', 'info'])) {
            $query->where('level', $level);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($status === 'unread') {
            $query->where('is_read', false);
        } elseif ($status === 'read') {
            $query->where('is_read', true);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $alerts = $query->orderBy('is_read', 'asc')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // Thống kê KPI
        $allAlerts = $family->smartAlerts;
        $totalAlerts = $allAlerts->count();
        $unreadCount = $allAlerts->where('is_read', false)->count();
        $dangerCount = $allAlerts->where('level', 'danger')->count();
        $warningCount = $allAlerts->where('level', 'warning')->count();

        return view('alerts.index', compact(
            'alerts',
            'totalAlerts',
            'unreadCount',
            'dangerCount',
            'warningCount',
            'level',
            'type',
            'status',
            'search'
        ));
    }

    /**
     * Đánh dấu 1 cảnh báo là đã đọc
     */
    public function markAsRead(Request $request, SmartAlert $alert)
    {
        $this->authorizeAlert($request, $alert);

        $alert->update(['is_read' => true]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Đã đánh dấu cảnh báo là đã đọc.');
    }

    /**
     * Đánh dấu tất cả cảnh báo là đã đọc
     */
    public function markAllAsRead(Request $request)
    {
        $family = $this->ensureFamily($request);

        $updated = $family->smartAlerts()->unread()->update(['is_read' => true]);

        return back()->with('success', "Đã đánh dấu {$updated} cảnh báo là đã đọc.");
    }

    /**
     * Kích hoạt quét tự động toàn bộ tủ thuốc để cập nhật cảnh báo mới
     */
    public function scan(Request $request, AlertScannerService $scanner)
    {
        $family = $this->ensureFamily($request);

        $results = $scanner->scanFamily($family);

        $msg = "Đã quét hoàn tất tủ thuốc ({$results['scanned_cabinet_items']} mục). ";
        if ($results['expired_count'] > 0) {
            $msg .= "Phát hiện {$results['expired_count']} thuốc đã hết hạn! ";
        }
        if ($results['near_expiry_count'] > 0) {
            $msg .= "Có {$results['near_expiry_count']} thuốc cận date. ";
        }
        if ($results['low_stock_count'] > 0) {
            $msg .= "Có {$results['low_stock_count']} thuốc/vật tư sắp hết. ";
        }

        return redirect()->route('alerts.index')->with('success', $msg);
    }

    /**
     * Xóa một thông báo cảnh báo
     */
    public function destroy(Request $request, SmartAlert $alert)
    {
        $this->authorizeAlert($request, $alert);

        $alert->delete();

        return back()->with('success', 'Đã xóa cảnh báo khỏi hệ thống.');
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
     * Kiểm tra quyền sở hữu cảnh báo
     */
    protected function authorizeAlert(Request $request, SmartAlert $alert): void
    {
        $family = $this->ensureFamily($request);
        if ($alert->family_id !== $family->id) {
            abort(403, 'Bạn không có quyền thao tác trên cảnh báo này.');
        }
    }
}
