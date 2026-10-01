<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\MedicationSchedule;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineExpense;
use App\Models\SmartAlert;
use App\Models\TreatmentCourse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Bảng điều khiển Tổng quan Quản trị viên Hệ thống (System Admin Dashboard)
     */
    public function index()
    {
        // 1. Chỉ số KPI toàn hệ thống
        $totalFamilies = Family::count();
        $totalUsers = User::count();
        $totalMembers = FamilyMember::count();
        $totalMedicines = Medicine::count();
        $totalCategories = MedicineCategory::count();
        $totalCabinetItems = CabinetItem::count();
        $totalTreatmentCourses = TreatmentCourse::count();
        $totalSchedules = MedicationSchedule::count();
        $totalExpenses = (float) MedicineExpense::sum('amount');
        $totalAlerts = SmartAlert::count();

        // 2. Thống kê tỷ lệ phân bố thuốc theo danh mục
        $categoryStats = MedicineCategory::withCount('medicines')
            ->orderByDesc('medicines_count')
            ->get();

        $categoryChartLabels = $categoryStats->pluck('name')->toArray();
        $categoryChartCounts = $categoryStats->pluck('medicines_count')->toArray();

        // 3. Cơ cấu người dùng theo vai trò
        $userRoleCounts = [
            'Super Admin' => User::where('role', 'system_admin')->count(),
            'Chủ hộ' => User::where('role', 'family_admin')->count(),
            'Thành viên' => User::where('role', 'member')->count(),
            'Bác sĩ/Chăm sóc' => User::where('role', 'doctor_caregiver')->count(),
        ];

        // 4. Xu hướng tăng trưởng Hộ gia đình mới trong năm nay (12 tháng)
        $currentYear = Carbon::now()->year;
        $monthlyFamiliesData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyFamiliesData[] = Family::whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $m)
                ->count();
        }

        // 5. Kiểm tra tình trạng sức khỏe hệ thống (System Health Check)
        $dbConnected = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbConnected = false;
        }

        $systemHealth = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'db_status' => $dbConnected,
            'db_driver' => config('database.default'),
            'storage_linked' => file_exists(public_path('storage')),
            'gemini_configured' => !empty(config('services.gemini.api_key')),
            'gemini_model' => config('services.gemini.model', 'gemini-1.5-flash'),
        ];

        // 6. Các hộ gia đình mới đăng ký gần đây
        $recentFamilies = Family::withCount(['users', 'members', 'cabinetItems'])
            ->latest()
            ->take(5)
            ->get();

        // 7. Các tài khoản người dùng mới nhất
        $recentUsers = User::with('family')
            ->latest()
            ->take(6)
            ->get();

        // 8. Các dược phẩm mới cập nhật vào Master Data
        $recentMedicines = Medicine::with('category')
            ->latest()
            ->take(6)
            ->get();

        return view('admin.dashboard.index', compact(
            'totalFamilies',
            'totalUsers',
            'totalMembers',
            'totalMedicines',
            'totalCategories',
            'totalCabinetItems',
            'totalTreatmentCourses',
            'totalSchedules',
            'totalExpenses',
            'totalAlerts',
            'categoryStats',
            'categoryChartLabels',
            'categoryChartCounts',
            'userRoleCounts',
            'monthlyFamiliesData',
            'currentYear',
            'systemHealth',
            'recentFamilies',
            'recentUsers',
            'recentMedicines'
        ));
    }

    /**
     * Kích hoạt quét cảnh báo tủ thuốc tự động toàn quốc từ giao diện quản trị
     */
    public function scanAlerts()
    {
        try {
            Artisan::call('cabinet:scan-alerts');
            return back()->with('success', 'Đã thực thi quét hạn dùng và tồn kho tủ thuốc tự động trên toàn bộ hệ thống thành công!');
        } catch (\Throwable $e) {
            return back()->with('warning', 'Lỗi khi quét cảnh báo: ' . $e->getMessage());
        }
    }
}
