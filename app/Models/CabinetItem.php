<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CabinetItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'medicine_id',
        'custom_name',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'quantity',
        'unit',
        'storage_location',
        'price_per_unit',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
            'quantity' => 'decimal:2',
            'price_per_unit' => 'decimal:2',
        ];
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    // Scopes
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expiry_date', '<', Carbon::today());
    }

    public function scopeNearExpiry(Builder $query, int $days = 30): Builder
    {
        return $query->where('expiry_date', '>=', Carbon::today())
            ->where('expiry_date', '<=', Carbon::today()->addDays($days));
    }

    public function scopeLowStock(Builder $query, float $threshold = 5): Builder
    {
        return $query->where('quantity', '>', 0)->where('quantity', '<=', $threshold);
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('quantity', '<=', 0);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0);
    }

    // Accessors
    public function getDisplayNameAttribute(): string
    {
        return $this->custom_name ?: ($this->medicine?->name ?? 'Thuốc không rõ tên');
    }

    public function getDaysUntilExpiryAttribute(): int
    {
        $expiry = Carbon::parse($this->expiry_date)->startOfDay();
        return (int) Carbon::today()->diffInDays($expiry, false);
    }

    public function getIsExpiredAttribute(): bool
    {
        return Carbon::parse($this->expiry_date)->startOfDay()->lt(Carbon::today());
    }

    public function getIsNearExpiryAttribute(): bool
    {
        $days = $this->days_until_expiry;
        return $days >= 0 && $days <= 30;
    }

    public function getExpiryRemainingTextAttribute(): string
    {
        $expiry = Carbon::parse($this->expiry_date)->startOfDay();
        $today = Carbon::today();

        if ($expiry->lt($today)) {
            $daysAgo = abs($this->days_until_expiry);
            return $daysAgo === 0 ? 'Hết hạn hôm nay' : "Đã hết hạn {$daysAgo} ngày";
        }

        $days = $this->days_until_expiry;
        if ($days === 0) {
            return 'Hết hạn trong hôm nay';
        }
        if ($days <= 30) {
            return "Cận hạn: còn {$days} ngày";
        }
        if ($days < 365) {
            $months = max(1, (int) round($days / 30.4));
            return "Còn {$months} tháng ({$days} ngày)";
        }
        $years = round($days / 365.25, 1);
        return "Còn ~{$years} năm ({$days} ngày)";
    }

    public function getIsLowStockAttribute(): bool
    {
        return (float) $this->quantity > 0 && (float) $this->quantity <= 5;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return (float) $this->quantity <= 0;
    }

    public function getTotalValueAttribute(): float
    {
        return (float) $this->quantity * (float) ($this->price_per_unit ?? 0);
    }
}
