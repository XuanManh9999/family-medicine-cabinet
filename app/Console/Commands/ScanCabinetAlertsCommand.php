<?php

namespace App\Console\Commands;

use App\Models\CabinetItem;
use App\Models\SmartAlert;
use Carbon\Carbon;
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
     */
    public function handle(): int
    {
        $this->info('Đang quét toàn bộ tủ thuốc gia đình...');
        $today = Carbon::today();
        $expiredCount = 0;
        $nearExpiryCount = 0;
        $lowStockCount = 0;

        $items = CabinetItem::with('family')->get();

        foreach ($items as $item) {
            $expiryDate = Carbon::parse($item->expiry_date);
            $displayName = $item->display_name;
            $location = $item->storage_location ?: 'Tủ thuốc';

            // 1. Kiểm tra thuốc đã hết hạn
            if ($expiryDate->lt($today)) {
                if ($item->status !== 'qua_han') {
                    $item->update(['status' => 'qua_han']);
                }

                // Kiểm tra xem đã có cảnh báo hết hạn chưa đọc chưa để tránh lặp
                $existingAlert = SmartAlert::where('family_id', $item->family_id)
                    ->where('type', 'expired')
                    ->where('title', 'like', "%{$displayName}%")
                    ->where('is_read', false)
                    ->first();

                if (!$existingAlert) {
                    SmartAlert::create([
                        'family_id' => $item->family_id,
                        'user_id' => null,
                        'type' => 'expired',
                        'level' => 'danger',
                        'title' => "CẢNH BÁO NGUY HIỂM: Thuốc '{$displayName}' đã quá hạn sử dụng!",
                        'content' => "Thuốc '{$displayName}' tại {$location} đã hết hạn vào ngày {$expiryDate->format('d/m/Y')}. Tuyệt đối không sử dụng và cần tiêu hủy ngay lập tức!",
                        'is_read' => false,
                        'action_url' => '/cabinet?status=expired',
                    ]);
                    $expiredCount++;
                }
            }
            // 2. Kiểm tra thuốc cận date (< 30 ngày)
            elseif ($expiryDate->gte($today) && $expiryDate->lte($today->copy()->addDays(30))) {
                if ($item->status !== 'sap_het_han') {
                    $item->update(['status' => 'sap_het_han']);
                }

                $daysRemaining = $today->diffInDays($expiryDate);
                $existingAlert = SmartAlert::where('family_id', $item->family_id)
                    ->where('type', 'near_expiry')
                    ->where('title', 'like', "%{$displayName}%")
                    ->where('is_read', false)
                    ->first();

                if (!$existingAlert) {
                    SmartAlert::create([
                        'family_id' => $item->family_id,
                        'user_id' => null,
                        'type' => 'near_expiry',
                        'level' => 'warning',
                        'title' => "Cảnh báo thuốc cận date: '{$displayName}'",
                        'content' => "Thuốc '{$displayName}' chỉ còn {$daysRemaining} ngày hạn sử dụng (hết hạn ngày {$expiryDate->format('d/m/Y')}). Hãy ưu tiên sử dụng trước hoặc có kế hoạch thay thế.",
                        'is_read' => false,
                        'action_url' => '/cabinet?status=near_expiry',
                    ]);
                    $nearExpiryCount++;
                }
            }
            // 3. Thuốc còn hạn dài
            else {
                if ($item->quantity > 0 && $item->status !== 'con_han') {
                    $item->update(['status' => 'con_han']);
                }
            }

            // 4. Kiểm tra số lượng tồn kho thấp (<= 5)
            if ($item->quantity > 0 && $item->quantity <= 5) {
                $existingLowStock = SmartAlert::where('family_id', $item->family_id)
                    ->where('type', 'low_stock')
                    ->where('title', 'like', "%{$displayName}%")
                    ->where('is_read', false)
                    ->first();

                if (!$existingLowStock) {
                    SmartAlert::create([
                        'family_id' => $item->family_id,
                        'user_id' => null,
                        'type' => 'low_stock',
                        'level' => 'warning',
                        'title' => "Vật tư sắp hết: '{$displayName}'",
                        'content' => "Thuốc '{$displayName}' trong tủ chỉ còn {$item->quantity} {$item->unit}. Vui lòng lên danh sách mua bổ sung.",
                        'is_read' => false,
                        'action_url' => '/cabinet?status=low_stock',
                    ]);
                    $lowStockCount++;
                }
            } elseif ($item->quantity <= 0) {
                if ($item->status !== 'het_hang') {
                    $item->update(['status' => 'het_hang']);
                }
            }
        }

        $this->info("Quét hoàn tất!");
        $this->line("- Cảnh báo thuốc hết hạn mới: {$expiredCount}");
        $this->line("- Cảnh báo thuốc cận date mới: {$nearExpiryCount}");
        $this->line("- Cảnh báo thuốc sắp hết hàng mới: {$lowStockCount}");

        return Command::SUCCESS;
    }
}
