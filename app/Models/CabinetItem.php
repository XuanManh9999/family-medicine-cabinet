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
        'prescription_item_id',
        'custom_name',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'quantity',
        'purchased_quantity',
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
            'purchased_quantity' => 'decimal:2',
            'price_per_unit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Xóa lô thuốc => gỡ luôn các cảnh báo gắn với lô đó (tránh cảnh báo "mồ côi")
        static::deleting(function (CabinetItem $item) {
            SmartAlert::where('cabinet_item_id', $item->id)->delete();
        });
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function smartAlerts()
    {
        return $this->hasMany(SmartAlert::class);
    }

    /** Thuốc trong đơn mà lô này được mua theo (nếu mua theo đơn bác sĩ) */
    public function prescriptionItem()
    {
        return $this->belongsTo(PrescriptionItem::class);
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

    /**
     * Quy tắc trạng thái DUY NHẤT của lô thuốc (dùng chung cho form, nhập Excel, quét cảnh báo, trừ tồn kho):
     * quá hạn > cận hạn 30 ngày > hết hàng > còn hạn
     */
    public static function computeStatus(string|Carbon|null $expiryDate, int|float|string|null $quantity): string
    {
        if ($expiryDate) {
            $expiry = Carbon::parse($expiryDate)->startOfDay();
            if ($expiry->lt(Carbon::today())) {
                return 'qua_han';
            }
            if ($expiry->lte(Carbon::today()->addDays(30))) {
                return 'sap_het_han';
            }
        }

        return (float) $quantity <= 0 ? 'het_hang' : 'con_han';
    }

    /**
     * Cập nhật lại cột status theo hạn dùng & tồn kho hiện tại (chỉ ghi khi thay đổi)
     */
    public function syncStatus(): void
    {
        $status = self::computeStatus($this->expiry_date, $this->quantity);
        if ($this->status !== $status) {
            $this->forceFill(['status' => $status])->save();
        }
    }

    public function getStatusLabelAttribute(): string
    {
        return match (self::computeStatus($this->expiry_date, $this->quantity)) {
            'qua_han' => 'Quá hạn',
            'sap_het_han' => 'Cận hạn',
            'het_hang' => 'Hết hàng',
            default => $this->is_low_stock ? 'Sắp hết' : 'Còn hạn',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match (self::computeStatus($this->expiry_date, $this->quantity)) {
            'qua_han' => 'bg-danger text-white',
            'sap_het_han' => 'bg-warning text-dark',
            'het_hang' => 'bg-secondary text-white',
            default => $this->is_low_stock ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'bg-success-subtle text-success border border-success-subtle',
        };
    }
}
