<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Bộ lọc thời gian dùng chung cho khu Quản trị (theo ngày / tháng / năm / khoảng ngày / mốc nhanh)
 *
 * Tham số query: period, date (Y-m-d), month (Y-m), year (Y), from, to (Y-m-d)
 */
class DateRangeFilter
{
    public const PERIODS = [
        '' => 'Toàn thời gian',
        'today' => 'Hôm nay',
        '7d' => '7 ngày qua',
        '30d' => '30 ngày qua',
        'this_month' => 'Tháng này',
        'last_month' => 'Tháng trước',
        'this_quarter' => 'Quý này',
        'this_year' => 'Năm nay',
        'day' => 'Theo ngày cụ thể',
        'month' => 'Theo tháng',
        'year' => 'Theo năm',
        'range' => 'Khoảng ngày tùy chọn',
    ];

    public function __construct(
        public readonly string $period = '',
        public readonly ?Carbon $start = null,
        public readonly ?Carbon $end = null,
        public readonly ?string $date = null,
        public readonly ?string $month = null,
        public readonly ?int $year = null,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $period = $request->textQuery('period');
        if (!array_key_exists($period, self::PERIODS)) {
            $period = '';
        }

        $date = self::validDate($request->query('date'), 'Y-m-d');
        $month = self::validDate($request->query('month'), 'Y-m');
        $yearInput = $request->query('year');
        $year = is_numeric($yearInput) && (int) $yearInput >= 2000 && (int) $yearInput <= 2100 ? (int) $yearInput : null;
        $from = self::validDate($request->query('from'), 'Y-m-d');
        $to = self::validDate($request->query('to'), 'Y-m-d');

        $now = Carbon::now();
        [$start, $end] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'day' => $date ? [Carbon::parse($date)->startOfDay(), Carbon::parse($date)->endOfDay()] : [null, null],
            'month' => $month ? [Carbon::createFromFormat('!Y-m', $month)->startOfMonth(), Carbon::createFromFormat('!Y-m', $month)->endOfMonth()] : [null, null],
            'year' => $year ? [Carbon::create($year)->startOfYear(), Carbon::create($year)->endOfYear()] : [null, null],
            'range' => [$from ? Carbon::parse($from)->startOfDay() : null, $to ? Carbon::parse($to)->endOfDay() : null],
            default => [null, null],
        };

        // Người dùng nhập ngược "Từ ngày" > "Đến ngày" thì tự đảo lại
        if ($start && $end && $start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return new self($period, $start, $end, $date, $month, $year, $from, $to);
    }

    public function isActive(): bool
    {
        return $this->start !== null || $this->end !== null;
    }

    /**
     * Áp bộ lọc lên một cột ngày/giờ của truy vấn
     */
    public function apply(Builder $query, string $column = 'created_at'): Builder
    {
        if ($this->start) {
            $query->where($column, '>=', $this->start);
        }
        if ($this->end) {
            $query->where($column, '<=', $this->end);
        }

        return $query;
    }

    /**
     * Nhãn mô tả khoảng thời gian đang lọc, ví dụ "01/11/2026 – 30/11/2026"
     */
    public function label(): string
    {
        if (!$this->isActive()) {
            return self::PERIODS[''];
        }

        $startText = $this->start?->format('d/m/Y') ?? '…';
        $endText = $this->end?->format('d/m/Y') ?? '…';

        return $startText === $endText ? $startText : "{$startText} – {$endText}";
    }

    /**
     * Danh sách năm cho ô chọn năm (từ năm nhỏ nhất có dữ liệu đến năm hiện tại)
     */
    public static function yearOptions(?int $fromYear = null): array
    {
        $current = (int) Carbon::now()->year;
        $fromYear = min($fromYear ?? $current, $current);

        return range($current, max(2000, $fromYear));
    }

    private static function validDate(mixed $value, string $format): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $parsed = \DateTime::createFromFormat('!' . $format, $value);

        return $parsed && $parsed->format($format) === $value ? $value : null;
    }
}
