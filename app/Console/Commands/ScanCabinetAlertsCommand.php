<?php

namespace App\Console\Commands;

use App\Services\AlertScannerService;
use Illuminate\Console\Command;

class ScanCabinetAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cabinet:scan-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tự động quét hạn sử dụng và số lượng tồn kho trong tủ thuốc để tạo cảnh báo thông minh';

    /**
     * Execute the console command.
     * Dùng chung bộ máy AlertScannerService với nút "Quét ngay" để không sinh cảnh báo trùng lặp.
     */
    public function handle(AlertScannerService $scanner): int
    {
        $this->info('Đang quét toàn bộ tủ thuốc gia đình...');

        $totals = $scanner->scanAllFamilies();

        // Ghi nhận lần chạy gần nhất để trang Quản trị biết bộ lập lịch có đang hoạt động
        \Illuminate\Support\Facades\Cache::forever('scheduler:last_run:cabinet-scan', now()->toIso8601String());

        $this->info('Quét hoàn tất!');
        $this->line("- Số hộ gia đình đã quét: " . ($totals['families_scanned'] ?? 0));
        $this->line("- Số lô thuốc đã kiểm tra: " . ($totals['scanned_cabinet_items'] ?? 0));
        $this->line("- Số lô cập nhật lại trạng thái: " . ($totals['statuses_synced'] ?? 0));
        $this->line("- Thuốc đã hết hạn (còn tồn): " . ($totals['expired_count'] ?? 0));
        $this->line("- Thuốc cận date (≤ 30 ngày): " . ($totals['near_expiry_count'] ?? 0));
        $this->line("- Thuốc sắp hết hàng (≤ " . AlertScannerService::LOW_STOCK_THRESHOLD . "): " . ($totals['low_stock_count'] ?? 0));
        $this->line("- Thuốc đã hết hàng: " . ($totals['out_of_stock_count'] ?? 0));
        $this->line("- Hộ có nguy cơ trùng Paracetamol: " . ($totals['interaction_count'] ?? 0));
        $this->line("- Cảnh báo mới tạo: " . ($totals['new_alerts_created'] ?? 0)
            . " | cập nhật: " . ($totals['alerts_updated'] ?? 0)
            . " | đã gỡ (hết hiệu lực): " . ($totals['alerts_resolved'] ?? 0));

        return Command::SUCCESS;
    }
}
