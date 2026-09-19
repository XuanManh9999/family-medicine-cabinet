<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\FamilyMember;
use App\Models\MedicationSchedule;
use App\Models\MedicineExpense;
use App\Models\SmartAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Hiển thị bảng điều khiển tổng quan (Dashboard)
     */
    public function index()
    {
        $user = Auth::user();
        $family = $user->family;

        if (!$family) {
            return redirect()->route('profile')->with('warning', 'Vui lòng liên kết hoặc tạo Hộ gia đình để bắt đầu sử dụng.');
        }

        // 1. Thống kê KPI Tủ thuốc
        $totalItems = $family->cabinetItems()->count();
        $expiredItemsCount = $family->cabinetItems()->expired()->count();
        $nearExpiryItemsCount = $family->cabinetItems()->nearExpiry(30)->count();
        $lowStockItemsCount = $family->cabinetItems()->lowStock(5)->count();
        $safeItemsCount = max(0, $totalItems - $expiredItemsCount - $nearExpiryItemsCount);

        // 2. Lịch uống thuốc hôm nay của các thành viên
        $todaySchedules = $family->medicationSchedules()
            ->activeToday()
            ->with(['familyMember', 'prescriptionItem.medicine', 'logs' => function ($query) {
                $query->whereDate('scheduled_time', today());
            }])
            ->get();

        // Thống kê tiến độ uống hôm nay
        $totalSchedulesToday = $todaySchedules->count();
        $takenCount = 0;
        foreach ($todaySchedules as $sch) {
            if ($sch->logs->contains('status', 'da_uong')) {
                $takenCount++;
            }
        }
        $complianceRate = $totalSchedulesToday > 0 ? round(($takenCount / $totalSchedulesToday) * 100) : 100;

        // 3. Danh sách cảnh báo thông minh khẩn cấp (Chưa đọc)
        $urgentAlerts = $family->smartAlerts()
            ->unread()
            ->latest()
            ->take(5)
            ->get();

        // 4. Danh sách các thuốc trong tủ thuốc gần đây
        $recentCabinetItems = $family->cabinetItems()
            ->with('medicine.category')
            ->latest()
            ->take(6)
            ->get();

        // 5. Hồ sơ các thành viên trong gia đình
        $members = $family->members()
            ->withCount(['medicationSchedules' => function ($q) {
                $q->where('is_active', true);
            }])
            ->get();

        // 6. Tổng chi phí thuốc tháng này (VNĐ)
        $monthlyExpense = $family->expenses()
            ->thisMonth()
            ->sum('amount');

        return view('dashboard.index', compact(
            'family',
            'user',
            'totalItems',
            'expiredItemsCount',
            'nearExpiryItemsCount',
            'lowStockItemsCount',
            'safeItemsCount',
            'todaySchedules',
            'totalSchedulesToday',
            'takenCount',
            'complianceRate',
            'urgentAlerts',
            'recentCabinetItems',
            'members',
            'monthlyExpense'
        ));
    }
}
