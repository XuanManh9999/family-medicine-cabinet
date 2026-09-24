<?php

namespace App\Services;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\MedicationSchedule;
use App\Models\SmartAlert;
use Carbon\Carbon;

class AlertScannerService
{
    /**
     * Quét tự động toàn diện tủ thuốc và sinh cảnh báo thông minh cho gia đình
     *
     * @param Family $family
     * @return array Thống kê số lượng cảnh báo được tạo mới hoặc cập nhật
     */
    public function scanFamily(Family $family): array
    {
        $createdCount = 0;
        $today = Carbon::today();

        // 1. QUÉT THUỐC ĐÃ HẾT HẠN SỬ DỤNG (DANGER)
        $expiredItems = $family->cabinetItems()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $today)
            ->where('quantity', '>', 0)
            ->get();

        foreach ($expiredItems as $item) {
            $alert = SmartAlert::updateOrCreate(
                [
                    'family_id' => $family->id,
                    'type' => 'expired',
                    'title' => "CẢNH BÁO NGUY HIỂM: Thuốc {$item->display_name} đã hết hạn!",
                ],
                [
                    'level' => 'danger',
                    'content' => "Thuốc \"{$item->display_name}\" tại {$item->storage_location} đã hết hạn sử dụng vào ngày {$item->expiry_date->format('d/m/Y')}. Tuyệt đối không cho người thân sử dụng và cần loại bỏ khỏi tủ thuốc ngay lập tức!",
                    'is_read' => false,
                    'action_url' => route('cabinet.index', ['filter' => 'expired']),
                ]
            );
            if ($alert->wasRecentlyCreated) {
                $createdCount++;
            }
        }

        // 2. QUÉT THUỐC CẬN DATE DƯỚI 30 NGÀY (WARNING)
        $nearExpiryItems = $family->cabinetItems()
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$today, $today->copy()->addDays(30)])
            ->where('quantity', '>', 0)
            ->get();

        foreach ($nearExpiryItems as $item) {
            $daysLeft = max(0, $today->diffInDays($item->expiry_date, false));
            $alert = SmartAlert::updateOrCreate(
                [
                    'family_id' => $family->id,
                    'type' => 'near_expiry',
                    'title' => "Cảnh báo thuốc cận date: {$item->display_name}",
                ],
                [
                    'level' => 'warning',
                    'content' => "Thuốc \"{$item->display_name}\" chỉ còn {$daysLeft} ngày nữa là hết hạn (HSD: {$item->expiry_date->format('d/m/Y')}). Hãy ưu tiên dùng trước nếu có chỉ định hoặc lên kế hoạch thay thế.",
                    'is_read' => false,
                    'action_url' => route('cabinet.index', ['filter' => 'near_expiry']),
                ]
            );
            if ($alert->wasRecentlyCreated) {
                $createdCount++;
            }
        }

        // 3. QUÉT THUỐC SẮP HẾT HOẶC ĐÃ HẾT TỒN KHO (LOW STOCK)
        $lowStockItems = $family->cabinetItems()
            ->where('quantity', '<=', 2)
            ->get();

        foreach ($lowStockItems as $item) {
            if ($item->quantity == 0) {
                $title = "Tủ thuốc đã hết: {$item->display_name}";
                $content = "Vật tư / thuốc \"{$item->display_name}\" trong tủ thuốc đã hết sạch (0 {$item->unit}). Hãy mua bổ sung vào tủ thuốc để sẵn sàng khi cần cấp cứu.";
            } else {
                $title = "Tồn kho sắp hết: {$item->display_name}";
                $content = "Thuốc \"{$item->display_name}\" chỉ còn lại {$item->quantity} {$item->unit}. Cần lên kế hoạch mua dự trữ thêm.";
            }

            $alert = SmartAlert::updateOrCreate(
                [
                    'family_id' => $family->id,
                    'type' => 'low_stock',
                    'title' => $title,
                ],
                [
                    'level' => 'warning',
                    'content' => $content,
                    'is_read' => false,
                    'action_url' => route('cabinet.index', ['filter' => 'low_stock']),
                ]
            );
            if ($alert->wasRecentlyCreated) {
                $createdCount++;
            }
        }

        // 4. QUÉT NGUY CƠ TRÙNG HOẠT CHẤT PARACETAMOL GÂY QUÁ LIỀU
        $paracetamolItems = $family->cabinetItems()
            ->where('quantity', '>', 0)
            ->get()
            ->filter(function ($item) {
                $name = strtolower($item->display_name);
                return str_contains($name, 'paracetamol') ||
                       str_contains($name, 'panadol') ||
                       str_contains($name, 'hapacol') ||
                       str_contains($name, 'efferalgan') ||
                       str_contains($name, 'decolgen');
            });

        if ($paracetamolItems->count() >= 2) {
            $names = $paracetamolItems->pluck('display_name')->take(3)->implode(', ');
            $alert = SmartAlert::updateOrCreate(
                [
                    'family_id' => $family->id,
                    'type' => 'drug_interaction',
                    'title' => 'CẢNH BÁO TƯƠNG TÁC AI: Nguy cơ quá liều Paracetamol!',
                ],
                [
                    'level' => 'danger',
                    'content' => "Hệ thống AI phát hiện tủ thuốc có nhiều chế phẩm cùng chứa Paracetamol ({$names}). Khi hạ sốt/giảm đau, chỉ được dùng duy nhất 1 loại và cách nhau tối thiểu 4-6 giờ để phòng tránh ngộ độc gan cấp tính.",
                    'is_read' => false,
                    'action_url' => route('cabinet.index'),
                ]
            );
            if ($alert->wasRecentlyCreated) {
                $createdCount++;
            }
        }

        return [
            'scanned_cabinet_items' => $family->cabinetItems()->count(),
            'expired_count' => $expiredItems->count(),
            'near_expiry_count' => $nearExpiryItems->count(),
            'low_stock_count' => $lowStockItems->count(),
            'new_alerts_created' => $createdCount,
        ];
    }
}
