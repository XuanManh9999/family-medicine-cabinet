<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CabinetItemController;
use App\Http\Controllers\TreatmentCourseController;
use App\Http\Controllers\MedicationScheduleController;
use App\Http\Controllers\MedicineExpenseController;
use App\Http\Controllers\SmartAlertController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\AiAssistantController;
use Illuminate\Support\Facades\Route;

// Trang chủ: Chuyển hướng theo trạng thái đăng nhập và vai trò
Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->isSystemAdmin() ? redirect()->route('admin.dashboard') : redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// Trang giới thiệu & Giới thiệu Nền tảng
Route::get('/landing', fn () => view('welcome'))->name('landing');

// Nhóm Routes cho Khách (Chưa đăng nhập)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');

    // Quên mật khẩu & Đặt lại qua mã OTP
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetOtp'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/verify-otp', [AuthController::class, 'showVerifyOtpForm'])->name('password.otp');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('password.otp.verify');
    Route::get('/reset-password', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1')->name('password.update');
});

// Nhóm Routes yêu cầu Đăng nhập
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard chính
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Hồ sơ người dùng & Gia đình
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');

    // Chủ hộ quản lý tài khoản trong gia đình (chỉ định Bác sĩ, gỡ tài khoản, tạo lại mã mời)
    Route::patch('/family/users/{user}/role', [\App\Http\Controllers\FamilyAccountController::class, 'updateRole'])->name('family.users.role');
    Route::delete('/family/users/{user}', [\App\Http\Controllers\FamilyAccountController::class, 'remove'])->name('family.users.remove');
    Route::post('/family/regenerate-code', [\App\Http\Controllers\FamilyAccountController::class, 'regenerateCode'])->name('family.regenerate-code');

    // Tủ thuốc gia đình
    Route::get('/cabinet', [CabinetItemController::class, 'index'])->name('cabinet.index');
    Route::get('/cabinet/items', fn () => redirect()->route('cabinet.index', request()->query()));
    Route::get('/cabinet/export-excel', [CabinetItemController::class, 'exportExcel'])->name('cabinet.export-excel');
    Route::get('/cabinet/sample-excel', [CabinetItemController::class, 'sampleExcel'])->name('cabinet.sample-excel');
    Route::post('/cabinet/import-excel', [CabinetItemController::class, 'importExcel'])->name('cabinet.import-excel');
    Route::get('/cabinet/items/create', [CabinetItemController::class, 'create'])->name('cabinet.items.create');
    Route::post('/cabinet/items', [CabinetItemController::class, 'store'])->name('cabinet.items.store');
    Route::get('/cabinet/items/{cabinetItem}/edit', [CabinetItemController::class, 'edit'])->name('cabinet.items.edit');
    Route::put('/cabinet/items/{cabinetItem}', [CabinetItemController::class, 'update'])->name('cabinet.items.update');
    Route::patch('/cabinet/items/{cabinetItem}/quantity', [CabinetItemController::class, 'updateQuantity'])->name('cabinet.items.quantity');
    Route::delete('/cabinet/items/{cabinetItem}', [CabinetItemController::class, 'destroy'])->name('cabinet.items.destroy');

    // Cần mua & bổ sung: mua theo đơn Bác sĩ, thuốc đang uống sắp hết, yêu cầu bổ sung
    Route::get('/cabinet/supply', [\App\Http\Controllers\CabinetSupplyController::class, 'index'])->name('cabinet.supply');
    Route::get('/cabinet/purchases/prescription/{prescriptionItem}', [\App\Http\Controllers\CabinetPurchaseController::class, 'createForPrescription'])->name('cabinet.purchases.prescription.create');
    Route::post('/cabinet/purchases/prescription/{prescriptionItem}', [\App\Http\Controllers\CabinetPurchaseController::class, 'storeForPrescription'])->name('cabinet.purchases.prescription.store');
    Route::post('/cabinet/purchases/prescription/{prescriptionItem}/link', [\App\Http\Controllers\CabinetPurchaseController::class, 'linkExisting'])->name('cabinet.purchases.prescription.link');
    Route::get('/cabinet/purchases/refill/{refillRequest}', [\App\Http\Controllers\CabinetPurchaseController::class, 'createForRefill'])->name('cabinet.purchases.refill.create');
    Route::post('/cabinet/purchases/refill/{refillRequest}', [\App\Http\Controllers\CabinetPurchaseController::class, 'storeForRefill'])->name('cabinet.purchases.refill.store');
    Route::post('/cabinet/lots/{lot}/confirm', [\App\Http\Controllers\CabinetLotReviewController::class, 'confirm'])->whereNumber('lot')->name('cabinet.lots.confirm');
    Route::post('/cabinet/lots/{lot}/return', [\App\Http\Controllers\CabinetLotReviewController::class, 'returnLot'])->whereNumber('lot')->name('cabinet.lots.return');
    Route::get('/cabinet/lots/{lot}/photos/{index}', [\App\Http\Controllers\CabinetLotReviewController::class, 'photo'])->whereNumber(['lot', 'index'])->name('cabinet.lots.photo');
    Route::post('/cabinet/refills', [\App\Http\Controllers\RefillRequestController::class, 'store'])->name('cabinet.refills.store');
    Route::post('/cabinet/refills/{refillRequest}/approve', [\App\Http\Controllers\RefillRequestController::class, 'approve'])->name('cabinet.refills.approve');
    Route::post('/cabinet/refills/{refillRequest}/stop', [\App\Http\Controllers\RefillRequestController::class, 'stop'])->name('cabinet.refills.stop');
    Route::post('/cabinet/refills/{refillRequest}/reject', [\App\Http\Controllers\RefillRequestController::class, 'reject'])->name('cabinet.refills.reject');
    Route::post('/cabinet/refills/{refillRequest}/cancel', [\App\Http\Controllers\RefillRequestController::class, 'cancel'])->name('cabinet.refills.cancel');

    // Đề nghị nhập thuốc: Chủ hộ / Thành viên gửi, Bác sĩ duyệt
    Route::get('/cabinet/requests', [\App\Http\Controllers\CabinetRequestController::class, 'index'])->name('cabinet.requests.index');
    Route::get('/cabinet/requests/create', [\App\Http\Controllers\CabinetRequestController::class, 'create'])->name('cabinet.requests.create');
    Route::post('/cabinet/requests', [\App\Http\Controllers\CabinetRequestController::class, 'store'])->name('cabinet.requests.store');
    Route::post('/cabinet/requests/scan-box', [\App\Http\Controllers\CabinetRequestController::class, 'scanBox'])->middleware('throttle:ai')->name('cabinet.requests.scan-box');
    Route::get('/cabinet/requests/{cabinetRequest}/photos/{index}', [\App\Http\Controllers\CabinetRequestController::class, 'photo'])->whereNumber('index')->name('cabinet.requests.photo');
    Route::get('/cabinet/requests/{cabinetRequest}/review', [\App\Http\Controllers\CabinetRequestController::class, 'review'])->name('cabinet.requests.review');
    Route::post('/cabinet/requests/{cabinetRequest}/approve', [\App\Http\Controllers\CabinetRequestController::class, 'approve'])->name('cabinet.requests.approve');
    Route::post('/cabinet/requests/{cabinetRequest}/reject', [\App\Http\Controllers\CabinetRequestController::class, 'reject'])->name('cabinet.requests.reject');
    Route::post('/cabinet/requests/{cabinetRequest}/cancel', [\App\Http\Controllers\CabinetRequestController::class, 'cancel'])->name('cabinet.requests.cancel');

    // Phân hệ Quản lý Thành viên & Hồ sơ Sức khỏe gia đình
    Route::resource('members', FamilyMemberController::class);

    // Phân hệ Đơn thuốc & Đợt điều trị ngoại trú
    Route::patch('/treatment-courses/{treatmentCourse}/toggle-status', [TreatmentCourseController::class, 'toggleStatus'])->name('treatment-courses.toggle-status');
    Route::post('/treatment-courses/{treatmentCourse}/explain-ai', [TreatmentCourseController::class, 'explainAi'])->middleware('throttle:ai')->name('treatment-courses.explain-ai');
    Route::resource('treatment-courses', TreatmentCourseController::class);

    // Phân hệ Lịch uống thuốc & Nhật ký tuân thủ điều trị
    Route::get('/schedules/calendar-events', [MedicationScheduleController::class, 'calendarEvents'])->name('schedules.calendar-events');
    Route::post('/schedules/log', [MedicationScheduleController::class, 'recordLog'])->name('schedules.log');
    Route::patch('/schedules/{schedule}/toggle', [MedicationScheduleController::class, 'toggle'])->name('schedules.toggle');
    Route::resource('schedules', MedicationScheduleController::class)->except(['show']);

    // Phân hệ Thống kê Chi phí thuốc & Viện phí gia đình
    Route::get('/expenses/export-csv', [MedicineExpenseController::class, 'exportCsv'])->name('expenses.export-csv');
    Route::resource('expenses', MedicineExpenseController::class)->except(['show']);

    // Phân hệ Tra cứu Thuốc chuẩn Việt Nam (Master Data)
    Route::get('/medicines', [MedicineController::class, 'index'])->name('medicines.index');
    Route::get('/medicines/{medicine}', [MedicineController::class, 'show'])->name('medicines.show');
    Route::post('/medicines/{medicine}/add-to-cabinet', [MedicineController::class, 'quickAddToCabinet'])->name('medicines.add-to-cabinet');

    // Phân hệ Trợ lý Y tế & Dược sĩ Gia đình AI
    Route::get('/ai-assistant', [AiAssistantController::class, 'index'])->name('ai-assistant.index');
    Route::post('/ai-assistant/scan', [AiAssistantController::class, 'scanPrescription'])->middleware('throttle:ai')->name('ai-assistant.scan');
    Route::post('/ai-assistant/chat', [AiAssistantController::class, 'chat'])->middleware('throttle:ai')->name('ai-assistant.chat');

    // Phân hệ Trung tâm Cảnh báo Thông minh
    Route::post('/alerts/scan', [SmartAlertController::class, 'scan'])->name('alerts.scan');
    Route::post('/alerts/mark-all-read', [SmartAlertController::class, 'markAllAsRead'])->name('alerts.mark-all-read');
    Route::patch('/alerts/{alert}/read', [SmartAlertController::class, 'markAsRead'])->name('alerts.read');
    Route::delete('/alerts/{alert}', [SmartAlertController::class, 'destroy'])->name('alerts.destroy');
    Route::get('/alerts', [SmartAlertController::class, 'index'])->name('alerts.index');
});

// Nhóm Routes Dành riêng cho Quản trị viên Hệ thống (System Admin)
Route::middleware(['auth', 'system_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');
    Route::post('/scan-alerts', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'scanAlerts'])->name('scan-alerts');
    Route::resource('medicines', \App\Http\Controllers\Admin\AdminMedicineController::class)->except(['show']);
    Route::resource('categories', \App\Http\Controllers\Admin\AdminCategoryController::class)->except(['create', 'show', 'edit']);
    Route::get('/families', [\App\Http\Controllers\Admin\AdminFamilyController::class, 'index'])->name('families.index');
    Route::get('/families/{family}', [\App\Http\Controllers\Admin\AdminFamilyController::class, 'show'])->name('families.show');
    Route::get('/users', [\App\Http\Controllers\Admin\AdminUserController::class, 'index'])->name('users.index');
    Route::get('/cabinet-requests', [\App\Http\Controllers\Admin\AdminCabinetRequestController::class, 'index'])->name('cabinet-requests.index');
    Route::patch('/users/{user}/role', [\App\Http\Controllers\Admin\AdminUserController::class, 'updateRole'])->name('users.role');
    Route::patch('/users/{user}/reset-password', [\App\Http\Controllers\Admin\AdminUserController::class, 'resetPassword'])->name('users.reset-password');
});
