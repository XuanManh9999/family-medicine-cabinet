<?php

namespace App\Services;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\SmartAlert;
use Carbon\Carbon;

/**
 * Bộ máy quét cảnh báo DUY NHẤT của hệ thống (dùng chung cho nút "Quét ngay", lệnh cabinet:scan-alerts và sau mỗi thao tác tủ thuốc).
 *
 * Nguyên tắc:
 * - Mỗi lô thuốc có tối đa 1 cảnh báo cho mỗi loại, khóa theo (family_id, type, cabinet_item_id).
 * - Chỉ đặt lại "chưa đọc" khi cảnh báo mới được tạo, nâng mức độ nghiêm trọng hoặc thay đổi nội dung quan trọng (đổi tên, đổi HSD...).
 * - Cuối mỗi lần quét, tự động gỡ (xóa) các cảnh báo theo lô không còn đúng (đã bổ sung hàng, đã xóa lô, chuyển từ cận date sang quá hạn...).
 */
class AlertScannerService
{
    /** Ngưỡng tồn kho thấp (đồng bộ với CabinetItem::scopeLowStock & is_low_stock) */
    public const LOW_STOCK_THRESHOLD = 5;

    /** Số ngày coi là cận date (đồng bộ với CabinetItem::computeStatus) */
    public const NEAR_EXPIRY_DAYS = 30;

    /** Các loại cảnh báo gắn với từng lô thuốc */
    public const ITEM_ALERT_TYPES = ['expired', 'near_expiry', 'low_stock', 'out_of_stock'];

    public const INTERACTION_TITLE = 'CẢNH BÁO TƯƠNG TÁC AI: Nguy cơ quá liều Paracetamol!';

    /** Từ khóa nhận diện chế phẩm chứa Paracetamol (tên biệt dược hoặc hoạt chất) */
    private const PARACETAMOL_KEYWORDS = ['paracetamol', 'acetaminophen', 'panadol', 'hapacol', 'efferalgan', 'decolgen', 'tylenol', 'partamol'];

    private const LEVEL_RANK = ['info' => 1, 'warning' => 2, 'danger' => 3];

    /**
     * Quét tự động toàn diện tủ thuốc và sinh cảnh báo thông minh cho gia đình
     *
     * @param Family $family
     * @return array Thống kê số lượng cảnh báo được tạo mới / cập nhật / gỡ bỏ
     */
    public function scanFamily(Family $family): array
    {
        $today = Carbon::today();
        $stats = [
            'scanned_cabinet_items' => 0,
            'expired_count' => 0,
            'near_expiry_count' => 0,
            'low_stock_count' => 0,
            'out_of_stock_count' => 0,
            'interaction_count' => 0,
            'new_alerts_created' => 0,
            'alerts_updated' => 0,
            'alerts_resolved' => 0,
            'statuses_synced' => 0,
        ];

        $items = $family->cabinetItems()->with('medicine')->get();
        $stats['scanned_cabinet_items'] = $items->count();

        $existingAlerts = $family->smartAlerts()
            ->whereIn('type', self::ITEM_ALERT_TYPES)
            ->whereNotNull('cabinet_item_id')
            ->get()
            ->keyBy(fn (SmartAlert $alert) => $alert->type . '#' . $alert->cabinet_item_id);

        $keptAlertIds = [];

        foreach ($items as $item) {
            // Đồng bộ lại cột trạng thái theo quy tắc chung
            $oldStatus = $item->status;
            $item->syncStatus();
            if ($oldStatus !== $item->status) {
                $stats['statuses_synced']++;
            }

            foreach ($this->desiredAlertsFor($item, $today) as $desired) {
                $statKey = $desired['type'] . '_count';
                $stats[$statKey]++;

                $existing = $existingAlerts->get($desired['type'] . '#' . $item->id);
                $alert = $this->upsertAlert($family, $existing, $desired, $item->id, $stats);
                $keptAlertIds[] = $alert->id;
            }
        }

        // Gỡ các cảnh báo theo lô thuốc không còn hiệu lực
        $staleQuery = $family->smartAlerts()
            ->whereIn('type', self::ITEM_ALERT_TYPES)
            ->whereNotNull('cabinet_item_id');
        if (!empty($keptAlertIds)) {
            $staleQuery->whereNotIn('id', $keptAlertIds);
        }
        $stats['alerts_resolved'] += $staleQuery->delete();

        // QUÉT NGUY CƠ TRÙNG HOẠT CHẤT PARACETAMOL GÂY QUÁ LIỀU
        $paracetamolItems = $items
            ->filter(fn (CabinetItem $item) => (float) $item->quantity > 0 && !$item->is_expired && $this->containsParacetamol($item))
            ->sortBy('display_name')
            ->values();

        $existingInteraction = $family->smartAlerts()
            ->where('type', 'drug_interaction')
            ->whereNull('cabinet_item_id')
            ->where('title', self::INTERACTION_TITLE)
            ->first();

        if ($paracetamolItems->count() >= 2) {
            $stats['interaction_count'] = 1;
            $names = $paracetamolItems->pluck('display_name')->unique()->values();
            $shownNames = $names->take(3)->implode(', ') . ($names->count() > 3 ? '...' : '');

            $this->upsertAlert($family, $existingInteraction, [
                'type' => 'drug_interaction',
                'level' => 'danger',
                'title' => self::INTERACTION_TITLE,
                'content' => "Hệ thống AI phát hiện tủ thuốc có {$paracetamolItems->count()} chế phẩm cùng chứa Paracetamol ({$shownNames}). Khi hạ sốt/giảm đau, chỉ được dùng duy nhất 1 loại và cách nhau tối thiểu 4-6 giờ để phòng tránh ngộ độc gan cấp tính.",
                'action_url' => route('ai-assistant.index', [], false),
                // Có thêm chế phẩm mới chứa Paracetamol => cần nhắc lại người dùng
                'signature' => $names->take(3)->all(),
            ], null, $stats);
        } elseif ($existingInteraction) {
            $existingInteraction->delete();
            $stats['alerts_resolved']++;
        }

        return $stats;
    }

    /**
     * Quét toàn bộ các hộ gia đình trong hệ thống, trả về thống kê cộng dồn
     */
    public function scanAllFamilies(): array
    {
        $totals = ['families_scanned' => 0];

        Family::query()->orderBy('id')->each(function (Family $family) use (&$totals) {
            $totals['families_scanned']++;
            foreach ($this->scanFamily($family) as $key => $value) {
                $totals[$key] = ($totals[$key] ?? 0) + $value;
            }
        });

        return $totals;
    }

    /**
     * Danh sách cảnh báo cần có cho một lô thuốc ở thời điểm hiện tại
     */
    private function desiredAlertsFor(CabinetItem $item, Carbon $today): array
    {
        $alerts = [];
        $name = $item->display_name;
        $location = $item->storage_location ?: 'Tủ thuốc';
        $quantity = (float) $item->quantity;
        $qtyText = rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');

        // Thuốc đã hết (tồn kho 0) không cần cảnh báo hạn dùng, chỉ cần nhắc mua bổ sung
        if ($quantity <= 0) {
            $alerts[] = [
                'type' => 'out_of_stock',
                'level' => 'warning',
                'title' => "Tủ thuốc đã hết: {$name}",
                'content' => "Vật tư / thuốc \"{$name}\" trong tủ thuốc đã hết sạch (0 {$item->unit}). Hãy mua bổ sung vào tủ thuốc để sẵn sàng khi cần.",
                'action_url' => route('cabinet.index', ['status' => 'out_of_stock'], false),
                'signature' => [],
            ];

            return $alerts;
        }

        if ($item->expiry_date) {
            $expiry = Carbon::parse($item->expiry_date)->startOfDay();
            $expiryText = $expiry->format('d/m/Y');

            if ($expiry->lt($today)) {
                $alerts[] = [
                    'type' => 'expired',
                    'level' => 'danger',
                    'title' => "CẢNH BÁO NGUY HIỂM: Thuốc {$name} đã hết hạn!",
                    'content' => "Thuốc \"{$name}\" tại {$location} đã hết hạn sử dụng vào ngày {$expiryText}. Tuyệt đối không cho người thân sử dụng và cần loại bỏ khỏi tủ thuốc ngay lập tức!",
                    'action_url' => route('cabinet.index', ['status' => 'expired'], false),
                    'signature' => [$expiryText],
                ];
            } elseif ($expiry->lte($today->copy()->addDays(self::NEAR_EXPIRY_DAYS))) {
                $daysLeft = (int) $today->diffInDays($expiry, false);
                $alerts[] = [
                    'type' => 'near_expiry',
                    'level' => 'warning',
                    'title' => "Cảnh báo thuốc cận date: {$name}",
                    'content' => "Thuốc \"{$name}\" chỉ còn {$daysLeft} ngày nữa là hết hạn (HSD: {$expiryText}). Hãy ưu tiên dùng trước nếu có chỉ định hoặc lên kế hoạch thay thế.",
                    'action_url' => route('cabinet.index', ['status' => 'near_expiry'], false),
                    'signature' => [$expiryText],
                ];
            }
        }

        if ($quantity <= self::LOW_STOCK_THRESHOLD) {
            $alerts[] = [
                'type' => 'low_stock',
                'level' => 'warning',
                'title' => "Tồn kho sắp hết: {$name}",
                'content' => "Thuốc \"{$name}\" chỉ còn lại {$qtyText} {$item->unit}. Cần lên kế hoạch mua dự trữ thêm.",
                'action_url' => route('cabinet.index', ['status' => 'low_stock'], false),
                'signature' => [],
            ];
        }

        return $alerts;
    }

    /**
     * Tạo mới hoặc cập nhật cảnh báo, giữ nguyên trạng thái "đã đọc" nếu không có thay đổi quan trọng
     */
    private function upsertAlert(Family $family, ?SmartAlert $existing, array $desired, ?int $cabinetItemId, array &$stats): SmartAlert
    {
        $attributes = [
            'level' => $desired['level'],
            'title' => $desired['title'],
            'content' => $desired['content'],
            'action_url' => $desired['action_url'],
        ];

        if (!$existing) {
            $stats['new_alerts_created']++;

            return SmartAlert::create($attributes + [
                'family_id' => $family->id,
                'cabinet_item_id' => $cabinetItemId,
                'type' => $desired['type'],
                'is_read' => false,
            ]);
        }

        $escalated = (self::LEVEL_RANK[$desired['level']] ?? 0) > (self::LEVEL_RANK[$existing->level] ?? 0);
        $materialChange = $escalated || $existing->title !== $desired['title'];
        foreach ($desired['signature'] ?? [] as $token) {
            if (!str_contains((string) $existing->content, (string) $token)) {
                $materialChange = true;
                break;
            }
        }

        $existing->fill($attributes);
        if ($materialChange) {
            $existing->is_read = false;
        }

        if ($existing->isDirty()) {
            $existing->save();
            $stats['alerts_updated']++;
        }

        return $existing;
    }

    private function containsParacetamol(CabinetItem $item): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $item->custom_name,
            $item->medicine?->name,
            $item->medicine?->active_ingredient,
        ])), 'UTF-8');

        foreach (self::PARACETAMOL_KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
