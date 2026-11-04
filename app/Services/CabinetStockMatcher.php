<?php

namespace App\Services;

use App\Models\CabinetItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Tìm lô thuốc trong tủ ứng với một thuốc đang uống (dùng chung cho trừ kho khi điểm danh và tính số ngày còn đủ thuốc).
 * Thứ tự ưu tiên: lô mua theo đúng thuốc trong đơn → cùng thuốc trong danh mục → trùng tên → tên chứa nhau.
 * Chỉ lấy lô còn hàng, còn hạn; cùng mức ưu tiên thì lô hết hạn sớm dùng trước (FEFO).
 */
class CabinetStockMatcher
{
    public function candidates(int $familyId, ?int $prescriptionItemId, ?int $medicineId, ?string $medicineName): Collection
    {
        $target = $this->normalizeName($medicineName);

        $items = CabinetItem::where('family_id', $familyId)
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '>=', Carbon::today()->toDateString())
            ->with('medicine')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get();

        $ranked = [];
        foreach ($items as $position => $item) {
            $rank = null;
            if ($prescriptionItemId && (int) $item->prescription_item_id === (int) $prescriptionItemId) {
                $rank = 0;
            } elseif ($medicineId && (int) $item->medicine_id === (int) $medicineId) {
                $rank = 1;
            } else {
                $names = array_values(array_filter([
                    $this->normalizeName($item->custom_name),
                    $this->normalizeName($item->medicine?->name),
                ]));

                foreach ($names as $name) {
                    if ($target !== '' && $name === $target) {
                        $rank = 2;
                        break;
                    }
                }

                if ($rank === null && mb_strlen($target) >= 3) {
                    foreach ($names as $name) {
                        if (mb_strlen($name) >= 3 && (str_contains($name, $target) || str_contains($target, $name))) {
                            $rank = 3;
                            break;
                        }
                    }
                }
            }

            if ($rank !== null) {
                $ranked[] = ['rank' => $rank, 'position' => $position, 'item' => $item];
            }
        }

        usort($ranked, fn ($a, $b) => [$a['rank'], $a['position']] <=> [$b['rank'], $b['position']]);

        return collect($ranked)->pluck('item')->values();
    }

    /**
     * So khớp đơn vị tủ thuốc với đơn vị liều uống (không phân biệt hoa thường; "viên" ~ "viên nén")
     */
    public function unitsCompatible(?string $cabinetUnit, ?string $doseUnit, ?string $doseText): bool
    {
        $cab = $this->normalizeName($cabinetUnit);
        $unit = $this->normalizeName($doseUnit);
        $text = $this->normalizeName($doseText);

        if ($cab === '' || ($unit === '' && $text === '')) {
            return true;
        }
        if ($unit !== '' && ($cab === $unit || str_contains($cab, $unit) || str_contains($unit, $cab))) {
            return true;
        }

        return $text !== '' && str_contains($text, $cab);
    }

    public function normalizeName(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower((string) $value)));
    }
}
