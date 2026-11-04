<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicationSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'family_member_id',
        'prescription_item_id',
        'medicine_name',
        'dosage',
        'unit',
        'time_of_day',
        'scheduled_time',
        'meal_timing',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function familyMember()
    {
        return $this->belongsTo(FamilyMember::class);
    }

    public function prescriptionItem()
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function logs()
    {
        return $this->hasMany(MedicationLog::class, 'schedule_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeActiveToday(Builder $query): Builder
    {
        return $this->scopeActiveOnDate($query, Carbon::today());
    }

    public function scopeActiveOnDate(Builder $query, Carbon|string $date): Builder
    {
        $d = is_string($date) ? Carbon::parse($date)->startOfDay() : $date->copy()->startOfDay();
        return $query->where('is_active', true)
            ->where('start_date', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $d);
            });
    }

    /**
     * Lịch hiển thị trên dòng thời gian/lịch biểu của 1 ngày: còn trong khoảng áp dụng và
     * (đang bật HOẶC đã có nhật ký trong ngày đó) để lịch sử không biến mất khi đợt điều trị kết thúc / tạm dừng
     */
    public function scopeVisibleOnDate(Builder $query, Carbon|string $date): Builder
    {
        $d = is_string($date) ? Carbon::parse($date)->startOfDay() : $date->copy()->startOfDay();
        $dateStr = $d->toDateString();

        return $query->where('start_date', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $d);
            })
            ->where(function ($q) use ($dateStr) {
                $q->where('is_active', true)
                  ->orWhereHas('logs', function ($logQ) use ($dateStr) {
                      $logQ->whereDate('scheduled_time', $dateStr);
                  });
            });
    }

    public function scopeForMember(Builder $query, int $memberId): Builder
    {
        return $query->where('family_member_id', $memberId);
    }

    public function getLogForDate(Carbon|string $date): ?MedicationLog
    {
        $targetDate = is_string($date) ? Carbon::parse($date)->toDateString() : $date->toDateString();
        return $this->logs->first(function ($log) use ($targetDate) {
            return $log->scheduled_time && $log->scheduled_time->toDateString() === $targetDate;
        });
    }

    /**
     * Tách số lượng của 1 liều từ chuỗi liều dùng:
     * "1 viên" → 1, "1/2 viên" / "½ viên" / "nửa viên" → 0.5, "0,5 viên" → 0.5, "1 1/2 viên" / "1½" → 1.5
     * Trả về null nếu không đọc được số lượng (VD: "Theo chỉ định").
     *
     * @return array{amount: float, unit_text: string}|null
     */
    public static function parseDose(?string $dosage): ?array
    {
        $text = trim((string) $dosage);
        if ($text === '') {
            return null;
        }

        $text = strtr($text, ['½' => ' 1/2', '¼' => ' 1/4', '¾' => ' 3/4', '⅓' => ' 1/3', '⅔' => ' 2/3']);
        $text = preg_replace('/(\d),(\d)/u', '$1.$2', $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        $amount = null;
        $rest = '';

        if (preg_match('/^nửa(?:\s+(.*))?$/iu', $text, $m)) {
            $amount = 0.5;
            $rest = $m[1] ?? '';
        } elseif (preg_match('/^(\d+(?:\.\d+)?)\s+(\d+)\s*\/\s*(\d+)(.*)$/u', $text, $m) && (int) $m[3] > 0) {
            $amount = (float) $m[1] + ((int) $m[2] / (int) $m[3]);
            $rest = $m[4];
        } elseif (preg_match('/^(\d+)\s*\/\s*(\d+)(.*)$/u', $text, $m) && (int) $m[2] > 0) {
            $amount = (int) $m[1] / (int) $m[2];
            $rest = $m[3];
        } elseif (preg_match('/^(\d+(?:\.\d+)?)(.*)$/u', $text, $m)) {
            $amount = (float) $m[1];
            $rest = $m[2];
        }

        if ($amount === null || $amount <= 0) {
            return null;
        }

        return [
            'amount' => round($amount, 2),
            'unit_text' => trim($rest),
        ];
    }

    // Accessors
    public function getTimeOfDayLabelAttribute(): string
    {
        return match ($this->time_of_day) {
            'sang' => 'Sáng',
            'trua' => 'Trưa',
            'chieu' => 'Chiều',
            'toi' => 'Tối',
            default => ucfirst($this->time_of_day),
        };
    }

    public function getTimeOfDayIconAttribute(): string
    {
        return match ($this->time_of_day) {
            'sang' => 'bi bi-brightness-high text-warning',
            'trua' => 'bi bi-sun text-info',
            'chieu' => 'bi bi-sunset text-warning-emphasis',
            'toi' => 'bi bi-moon-stars text-primary',
            default => 'bi bi-clock text-secondary',
        };
    }

    public function getTimeOfDayBadgeClassAttribute(): string
    {
        return match ($this->time_of_day) {
            'sang' => 'bg-warning text-dark',
            'trua' => 'bg-info text-dark',
            'chieu' => 'bg-secondary text-white',
            'toi' => 'bg-primary text-white',
            default => 'bg-light text-dark',
        };
    }

    public function getMealTimingLabelAttribute(): string
    {
        return match ($this->meal_timing) {
            'truoc_an' => 'Trước khi ăn 30 phút',
            'sau_an' => 'Sau khi ăn no',
            'trong_khi_an' => 'Trong khi ăn',
            'khong_phu_thuoc' => 'Không phụ thuộc bữa ăn',
            default => $this->meal_timing,
        };
    }
}
