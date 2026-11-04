<?php

namespace App\Services;

use App\Models\CabinetItem;
use App\Models\Family;
use App\Models\MedicationSchedule;
use App\Models\RefillRequest;
use App\Models\SmartAlert;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Tính "thuốc đang uống còn đủ dùng mấy ngày" cho từng thuốc có lịch uống:
 * số lượng còn trong tủ ÷ tổng liều mỗi ngày (cộng mọi cữ, mọi thành viên cùng dùng thuốc đó).
 * Nếu đủ dùng tới hết đợt điều trị thì không cần mua thêm.
 */
class MedicationSupplyService
{
    /** Còn đủ dùng từ ngần này ngày trở xuống thì báo sắp hết */
    public const LOW_DAYS = 3;

    private const STATUS_ORDER = ['out' => 0, 'low' => 1, 'unknown' => 2, 'ok' => 3, 'enough' => 4];

    public function __construct(private CabinetStockMatcher $matcher)
    {
    }

    /**
     * @return Collection<int, array> mỗi phần tử là một thuốc đang uống, sắp xếp thuốc sắp hết lên đầu
     */
    public function forFamily(Family $family): Collection
    {
        $today = Carbon::today();

        $schedules = MedicationSchedule::where('family_id', $family->id)
            ->activeOnDate($today)
            ->with(['prescriptionItem.treatmentCourse', 'prescriptionItem.cabinetItems', 'familyMember'])
            ->get();

        $openRefills = RefillRequest::where('family_id', $family->id)
            ->whereIn('status', [RefillRequest::STATUS_PENDING, RefillRequest::STATUS_APPROVED])
            ->get();

        $toBuyIds = $this->purchaseList($family)->pluck('item.id')->all();

        return $schedules
            ->groupBy(fn (MedicationSchedule $s) => $this->groupKey($s))
            ->map(fn (Collection $group, string $key) => $this->summarize($family, $key, $group, $openRefills, $today, $toBuyIds))
            ->sortBy(fn (array $row) => [self::STATUS_ORDER[$row['status']], $row['days_left'] ?? 9999])
            ->values();
    }

    /**
     * Danh sách "Cần mua theo đơn": thuốc trong các đợt đang điều trị mà gia đình chưa mua đủ.
     * - Đơn có ghi tổng số lượng kê → còn thiếu bao nhiêu thì cần mua bấy nhiêu
     * - Đơn không ghi số lượng → cần mua nếu chưa mua lần nào và trong tủ cũng chưa có thuốc đó
     */
    public function purchaseList(Family $family): Collection
    {
        $items = \App\Models\PrescriptionItem::whereHas('treatmentCourse', fn ($q) => $q
                ->where('family_id', $family->id)
                ->where('status', 'dang_dieu_tri'))
            ->with(['treatmentCourse.familyMember', 'cabinetItems', 'schedules', 'medicine'])
            ->orderBy('treatment_course_id')
            ->get();

        $today = Carbon::today();

        return $items->map(function (\App\Models\PrescriptionItem $item) use ($family, $today) {
            // Lô thuốc cùng loại đang có sẵn trong tủ nhưng chưa gắn với đơn (VD: mua từ trước khi có đơn)
            $existingStock = $this->matcher
                ->candidates($family->id, null, $item->medicine_id, $item->medicine_name)
                ->filter(fn (CabinetItem $lot) => $lot->prescription_item_id === null)
                ->values();

            $remaining = $item->remaining_to_buy;
            $purchased = $item->purchased_quantity;

            if ($remaining === null && ($purchased > 0 || $existingStock->isNotEmpty())) {
                return null;
            }
            if ($remaining !== null && $remaining <= 0) {
                return null;
            }

            // Gợi ý số lượng cần mua khi đơn không ghi: liều mỗi ngày × số ngày còn lại của đợt
            $suggested = $remaining;
            if ($suggested === null) {
                $daily = $item->schedules->where('is_active', true)
                    ->sum(fn (MedicationSchedule $s) => MedicationSchedule::parseDose($s->dosage)['amount'] ?? 0);
                $end = $item->treatmentCourse?->end_date;
                if ($daily > 0 && $end && Carbon::parse($end)->gte($today)) {
                    $suggested = round($daily * ($today->diffInDays(Carbon::parse($end)->startOfDay()) + 1), 2);
                }
            }

            return [
                'item' => $item,
                'course' => $item->treatmentCourse,
                'member' => $item->treatmentCourse?->familyMember,
                'prescribed' => (float) $item->total_prescribed_quantity ?: null,
                'purchased' => $purchased,
                'remaining' => $remaining,
                'suggested' => $suggested,
                'existing_stock' => $existingStock,
            ];
        })->filter()->values();
    }

    /**
     * Đồng bộ cảnh báo "Sắp hết thuốc đang uống" trong Trung tâm cảnh báo. Trả về số thuốc cần bổ sung.
     */
    public function syncAlerts(Family $family): int
    {
        $rows = $this->forFamily($family)
            ->filter(fn (array $row) => in_array($row['status'], ['out', 'low'], true) && !$row['awaiting_purchase']);

        $keptIds = [];
        foreach ($rows as $row) {
            $level = $row['status'] === 'out' ? 'danger' : 'warning';
            $daysText = $row['status'] === 'out'
                ? 'đã hết trong tủ'
                : 'chỉ còn đủ dùng khoảng ' . $this->formatNumber($row['days_left']) . ' ngày';

            $existing = SmartAlert::where('family_id', $family->id)
                ->where('type', 'refill_needed')
                ->where('medication_schedule_id', $row['schedule_id'])
                ->first();

            $values = [
                'level' => $level,
                'title' => "Sắp hết thuốc đang uống: {$row['medicine_name']}",
                'content' => "\"{$row['medicine_name']}\" ({$row['members']}) {$daysText} "
                    . "(còn {$this->formatNumber($row['stock'])} {$row['unit']}, dùng {$this->formatNumber($row['daily_usage'])} {$row['unit']}/ngày). "
                    . ($row['refill_status'] ? 'Đã có yêu cầu bổ sung đang xử lý.' : 'Hãy gửi yêu cầu bổ sung để Bác sĩ kê tiếp hoặc cho ngưng thuốc.'),
                'action_url' => route('cabinet.supply', [], false),
            ];

            if ($existing) {
                // Chỉ đánh dấu lại "chưa đọc" khi tình trạng nặng lên (sắp hết → đã hết)
                if ($existing->level !== $level) {
                    $values['is_read'] = false;
                }
                $existing->update($values);
                $keptIds[] = $existing->id;
            } else {
                $keptIds[] = SmartAlert::create(array_merge($values, [
                    'family_id' => $family->id,
                    'medication_schedule_id' => $row['schedule_id'],
                    'type' => 'refill_needed',
                    'is_read' => false,
                ]))->id;
            }
        }

        SmartAlert::where('family_id', $family->id)
            ->where('type', 'refill_needed')
            ->when($keptIds, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->delete();

        return $rows->count();
    }

    private function groupKey(MedicationSchedule $schedule): string
    {
        $medicineId = $schedule->prescriptionItem?->medicine_id;

        return $medicineId ? "m{$medicineId}" : 'n' . $this->matcher->normalizeName($schedule->medicine_name);
    }

    private function summarize(Family $family, string $key, Collection $group, Collection $openRefills, Carbon $today, array $toBuyIds): array
    {
        /** @var MedicationSchedule $first */
        $first = $group->first();

        // Thuốc trong đơn đang điều trị mới nhất (để yêu cầu Bác sĩ kê tiếp đúng đơn)
        $prescriptionItem = $group->pluck('prescriptionItem')->filter()
            ->sortByDesc(fn ($item) => [$item->treatmentCourse?->status === 'dang_dieu_tri' ? 1 : 0, $item->id])
            ->first();
        $medicineId = $prescriptionItem?->medicine_id;
        $unit = $first->unit ?: ($prescriptionItem?->unit ?: 'viên');

        $dailyUsage = 0.0;
        $parseable = true;
        foreach ($group as $schedule) {
            $dose = MedicationSchedule::parseDose($schedule->dosage);
            if (!$dose) {
                $parseable = false;
                continue;
            }
            $dailyUsage += $dose['amount'];
        }

        $doseUnitText = MedicationSchedule::parseDose($first->dosage)['unit_text'] ?? '';
        $stockItems = $this->matcher
            ->candidates($family->id, $prescriptionItem?->id, $medicineId, $first->medicine_name)
            ->filter(fn (CabinetItem $item) => $this->matcher->unitsCompatible($item->unit, $unit, $doseUnitText))
            ->values();
        $stock = (float) $stockItems->sum(fn (CabinetItem $item) => (float) $item->quantity);

        $daysLeft = ($parseable && $dailyUsage > 0) ? floor($stock / $dailyUsage * 10) / 10 : null;

        // Đợt điều trị có ngày kết thúc: chỉ cần đủ thuốc tới ngày đó
        $endDates = $group->pluck('end_date');
        $until = $endDates->contains(null) ? null : $endDates->max();
        $neededDays = $until ? $today->diffInDays(Carbon::parse($until)->startOfDay()) + 1 : null;

        $status = match (true) {
            $daysLeft === null => 'unknown',
            $stock <= 0 => 'out',
            $neededDays !== null && $daysLeft >= $neededDays => 'enough',
            $daysLeft <= self::LOW_DAYS => 'low',
            default => 'ok',
        };

        $refill = $openRefills->first(function (RefillRequest $refill) use ($prescriptionItem, $medicineId, $first) {
            return ($prescriptionItem && (int) $refill->prescription_item_id === (int) $prescriptionItem->id)
                || ($medicineId && (int) $refill->medicine_id === (int) $medicineId)
                || $this->matcher->normalizeName($refill->medicine_name) === $this->matcher->normalizeName($first->medicine_name);
        });

        return [
            'key' => $key,
            'schedule_id' => $first->id,
            'schedule_ids' => $group->pluck('id')->all(),
            'medicine_name' => $prescriptionItem?->medicine_name ?: $first->medicine_name,
            'medicine_id' => $medicineId,
            'prescription_item' => $prescriptionItem,
            'members' => $group->pluck('familyMember.name')->filter()->unique()->implode(', ') ?: 'Gia đình',
            'family_member_id' => $group->pluck('family_member_id')->unique()->count() === 1 ? $first->family_member_id : null,
            'doses_per_day' => $group->count(),
            'daily_usage' => round($dailyUsage, 2),
            'unit' => $unit,
            'stock' => round($stock, 2),
            'days_left' => $daysLeft,
            'until' => $until ? Carbon::parse($until) : null,
            'needed_days' => $neededDays,
            'status' => $status,
            // Bác sĩ đã kê nhưng gia đình chưa mua đủ → việc cần làm là đi mua, không phải xin thêm
            // (chỉ khi tủ thực sự thiếu thuốc này; đủ dùng rồi thì không nhắc đi mua)
            'awaiting_purchase' => $prescriptionItem && in_array($prescriptionItem->id, $toBuyIds, true) && !in_array($status, ['enough', 'ok'], true),
            'refill_status' => $refill?->status,
            'refill' => $refill,
        ];
    }

    private function formatNumber(float|int|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',');
    }
}
