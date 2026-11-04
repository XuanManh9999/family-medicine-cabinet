<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Các tác vụ này chỉ chạy khi có tiến trình gọi "php artisan schedule:run" mỗi phút
// (Windows/XAMPP: chạy scripts\windows\register-scheduler-task.bat một lần, hoặc giữ cửa sổ scripts\windows\run-scheduler.bat khi demo)
Schedule::command('cabinet:scan-alerts')->dailyAt('06:00')->withoutOverlapping()->description('Tự động quét hạn dùng và tồn kho tủ thuốc mỗi ngày');
Schedule::command('medication:send-reminders')->everyFiveMinutes()->withoutOverlapping()->description('Nhắc đến giờ uống thuốc & cảnh báo quá giờ chưa điểm danh');
