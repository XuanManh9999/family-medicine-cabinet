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
        return Carbon::today()->diffInDays(Carbon::parse($this->expiry_date), false);
    }

    public function getIsExpiredAttribute(): bool
    {
        return Carbon::parse($this->expiry_date)->isPast();
    }

    public function getIsNearExpiryAttribute(): bool
    {
        $days = $this->days_until_expiry;
        return $days >= 0 && $days <= 30;
    }
}
