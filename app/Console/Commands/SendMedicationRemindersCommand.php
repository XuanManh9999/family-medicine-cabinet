<?php

namespace App\Console\Commands;

use App\Services\MedicationReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendMedicationRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'medication:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nhắc đến giờ uống thuốc và cảnh báo cữ thuốc quá giờ chưa điểm danh (thông báo trong app + email)';

    /**
     * Execute the console command.
     */
    public function handle(MedicationReminderService $reminders): int
    {
        $stats = $reminders->run();

        // Ghi nhận lần chạy gần nhất để trang Quản trị biết bộ lập lịch có đang hoạt động
        Cache::forever('scheduler:last_run:medication-reminders', now()->toIso8601String());

        $this->info('Đã kiểm tra lịch nhắc uống thuốc.');
        $this->line("- Số lịch đã kiểm tra: {$stats['checked']}");
        $this->line("- Nhắc đến giờ uống: {$stats['due_sent']}");
        $this->line("- Cảnh báo quá giờ chưa uống: {$stats['missed_sent']}");
        $this->line("- Email đã gửi: {$stats['emails']}");
        $this->line("- Thông báo cũ đã dọn: {$stats['cleaned']}");

        return self::SUCCESS;
    }
}
