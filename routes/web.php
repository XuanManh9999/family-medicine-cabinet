<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CabinetItemController;
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

    // Các phân hệ tiếp theo (tạm thời trỏ về view/thông báo tính năng đang phát triển)
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
    Route::resource('members', \App\Http\Controllers\FamilyMemberController::class);

    Route::get('/treatment-courses', function () {
        return redirect()->route('dashboard')->with('info', 'Phân hệ Đơn thuốc & Điều trị đang được kích hoạt.');
    })->name('treatment-courses.index');

    Route::get('/schedules', function () {
        return redirect()->route('dashboard')->with('info', 'Phân hệ Lịch uống thuốc đang được kích hoạt.');
    })->name('schedules.index');

    Route::get('/expenses', function () {
        return redirect()->route('dashboard')->with('info', 'Phân hệ Thống kê Chi phí đang được kích hoạt.');
    })->name('expenses.index');

    Route::get('/medicines', function () {
        return redirect()->route('dashboard')->with('info', 'Phân hệ Tra cứu Thuốc chuẩn đang được kích hoạt.');
    })->name('medicines.index');

    Route::get('/ai-assistant', function () {
        return redirect()->route('dashboard')->with('info', 'Trợ lý Y tế AI đang được kích hoạt.');
    })->name('ai-assistant.index');

    Route::get('/alerts', function () {
        return redirect()->route('dashboard')->with('info', 'Hệ thống Cảnh báo Thông minh.');
    })->name('alerts.index');
});
