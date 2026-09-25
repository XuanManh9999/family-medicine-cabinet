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

// Trang chủ: Chuyển hướng theo trạng thái đăng nhập
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Nhóm Routes cho Khách (Chưa đăng nhập)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Quên mật khẩu & Đặt lại qua mã OTP
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetOtp'])->name('password.email');
    Route::get('/verify-otp', [AuthController::class, 'showVerifyOtpForm'])->name('password.otp');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('password.otp.verify');
    Route::get('/reset-password', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

// Nhóm Routes yêu cầu Đăng nhập
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard chính
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Hồ sơ người dùng & Gia đình
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');

    // Tủ thuốc gia đình
    Route::get('/cabinet', [CabinetItemController::class, 'index'])->name('cabinet.index');
    Route::get('/cabinet/export-excel', [CabinetItemController::class, 'exportExcel'])->name('cabinet.export-excel');
    Route::get('/cabinet/sample-excel', [CabinetItemController::class, 'sampleExcel'])->name('cabinet.sample-excel');
    Route::post('/cabinet/import-excel', [CabinetItemController::class, 'importExcel'])->name('cabinet.import-excel');
    Route::get('/cabinet/items/create', [CabinetItemController::class, 'create'])->name('cabinet.items.create');
    Route::post('/cabinet/items', [CabinetItemController::class, 'store'])->name('cabinet.items.store');
    Route::get('/cabinet/items/{cabinetItem}/edit', [CabinetItemController::class, 'edit'])->name('cabinet.items.edit');
    Route::put('/cabinet/items/{cabinetItem}', [CabinetItemController::class, 'update'])->name('cabinet.items.update');
    Route::patch('/cabinet/items/{cabinetItem}/quantity', [CabinetItemController::class, 'updateQuantity'])->name('cabinet.items.quantity');
    Route::delete('/cabinet/items/{cabinetItem}', [CabinetItemController::class, 'destroy'])->name('cabinet.items.destroy');

    // Phân hệ Quản lý Thành viên & Hồ sơ Sức khỏe gia đình
    Route::resource('members', FamilyMemberController::class);

    // Phân hệ Đơn thuốc & Đợt điều trị ngoại trú
    Route::patch('/treatment-courses/{treatmentCourse}/toggle-status', [TreatmentCourseController::class, 'toggleStatus'])->name('treatment-courses.toggle-status');
    Route::post('/treatment-courses/{treatmentCourse}/explain-ai', [TreatmentCourseController::class, 'explainAi'])->name('treatment-courses.explain-ai');
    Route::resource('treatment-courses', TreatmentCourseController::class);

    // Phân hệ Lịch uống thuốc & Nhật ký tuân thủ điều trị
    Route::get('/schedules/calendar-events', [MedicationScheduleController::class, 'calendarEvents'])->name('schedules.calendar-events');
    Route::post('/schedules/log', [MedicationScheduleController::class, 'recordLog'])->name('schedules.log');
    Route::patch('/schedules/{schedule}/toggle', [MedicationScheduleController::class, 'toggle'])->name('schedules.toggle');
    Route::resource('schedules', MedicationScheduleController::class);

    // Phân hệ Thống kê Chi phí thuốc & Viện phí gia đình
    Route::get('/expenses/export-csv', [MedicineExpenseController::class, 'exportCsv'])->name('expenses.export-csv');
    Route::resource('expenses', MedicineExpenseController::class);

    // Phân hệ Tra cứu Thuốc chuẩn Việt Nam (Master Data)
    Route::get('/medicines', [MedicineController::class, 'index'])->name('medicines.index');
    Route::get('/medicines/{medicine}', [MedicineController::class, 'show'])->name('medicines.show');
    Route::post('/medicines/{medicine}/add-to-cabinet', [MedicineController::class, 'quickAddToCabinet'])->name('medicines.add-to-cabinet');

    // Phân hệ Trợ lý Y tế & Dược sĩ Gia đình AI
    Route::get('/ai-assistant', [AiAssistantController::class, 'index'])->name('ai-assistant.index');
    Route::post('/ai-assistant/scan', [AiAssistantController::class, 'scanPrescription'])->name('ai-assistant.scan');
    Route::post('/ai-assistant/chat', [AiAssistantController::class, 'chat'])->name('ai-assistant.chat');

    // Phân hệ Trung tâm Cảnh báo Thông minh
    Route::post('/alerts/scan', [SmartAlertController::class, 'scan'])->name('alerts.scan');
    Route::post('/alerts/mark-all-read', [SmartAlertController::class, 'markAllAsRead'])->name('alerts.mark-all-read');
    Route::patch('/alerts/{alert}/read', [SmartAlertController::class, 'markAsRead'])->name('alerts.read');
    Route::delete('/alerts/{alert}', [SmartAlertController::class, 'destroy'])->name('alerts.destroy');
    Route::get('/alerts', [SmartAlertController::class, 'index'])->name('alerts.index');
});
