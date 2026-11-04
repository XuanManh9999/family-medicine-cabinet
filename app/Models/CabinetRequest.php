<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Đề nghị nhập thuốc vào tủ (chờ Bác sĩ / Quản trị viên duyệt)
 */
class CabinetRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    /** Các trường thông tin lô thuốc, trùng tên với cabinet_items */
    public const ITEM_FIELDS = [
        'medicine_id',
        'custom_name',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'quantity',
        'unit',
        'storage_location',
        'price_per_unit',
        'notes',
    ];

    protected $fillable = [
        'family_id',
        'requested_by',
        'medicine_id',
        'custom_name',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'quantity',
        'unit',
        'storage_location',
        'price_per_unit',
        'notes',
        'record_expense',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'cabinet_item_id',
        'medicine_expense_id',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
            'quantity' => 'decimal:2',
            'price_per_unit' => 'decimal:2',
            'record_expense' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function cabinetItem()
    {
        return $this->belongsTo(CabinetItem::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->custom_name ?: ($this->medicine?->name ?? 'Thuốc không rõ tên');
    }

    public function getEstimatedTotalAttribute(): float
    {
        return (float) $this->quantity * (float) ($this->price_per_unit ?? 0);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Đã duyệt',
            self::STATUS_REJECTED => 'Từ chối',
            self::STATUS_CANCELLED => 'Đã hủy',
            default => 'Chờ duyệt',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'bg-success-subtle text-success border border-success-subtle',
            self::STATUS_REJECTED => 'bg-danger-subtle text-danger border border-danger-subtle',
            self::STATUS_CANCELLED => 'bg-secondary-subtle text-secondary border',
            default => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        };
    }

    /**
     * Dữ liệu lô thuốc dạng mảng chuỗi để đưa vào form / bộ kiểm tra dữ liệu
     */
    public function itemAttributes(): array
    {
        return [
            'medicine_id' => $this->medicine_id,
            'custom_name' => $this->custom_name,
            'batch_number' => $this->batch_number,
            'manufacturing_date' => $this->manufacturing_date?->format('Y-m-d'),
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'storage_location' => $this->storage_location,
            'price_per_unit' => $this->price_per_unit,
            'notes' => $this->notes,
        ];
    }
}
