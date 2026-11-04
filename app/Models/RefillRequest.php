<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Yêu cầu bổ sung thuốc khi thuốc đang uống / thuốc trong tủ sắp hết
 */
class RefillRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';   // Bác sĩ kê tiếp / đồng ý mua thêm → gia đình đi mua
    public const STATUS_FULFILLED = 'fulfilled'; // Đã mua về, nhập tủ xong
    public const STATUS_STOPPED = 'stopped';     // Bác sĩ cho ngưng thuốc
    public const STATUS_REJECTED = 'rejected';   // Không cần / không nên mua thêm
    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_SCHEDULE = 'schedule';
    public const SOURCE_CABINET = 'cabinet';

    protected $fillable = [
        'family_id',
        'requested_by',
        'prescription_item_id',
        'cabinet_item_id',
        'medicine_id',
        'family_member_id',
        'medicine_name',
        'unit',
        'source',
        'current_stock',
        'days_left',
        'requester_note',
        'status',
        'approved_quantity',
        'extended_until',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:2',
            'days_left' => 'decimal:1',
            'approved_quantity' => 'decimal:2',
            'extended_until' => 'date',
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

    public function prescriptionItem()
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function cabinetItem()
    {
        return $this->belongsTo(CabinetItem::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function familyMember()
    {
        return $this->belongsTo(FamilyMember::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /** Đã được duyệt nhưng chưa mua (với thuốc không gắn đơn) */
    public function scopeAwaitingPurchase(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)->whereNull('prescription_item_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Yêu cầu còn "đang xử lý" (chặn gửi trùng): đang chờ Bác sĩ, hoặc đã được đồng ý mua mà gia đình chưa mua xong.
     * Yêu cầu kê tiếp theo đơn chỉ còn mở khi đơn đó còn đang điều trị và thuốc vẫn còn phải mua.
     */
    public function isOpen(): bool
    {
        if ($this->status === self::STATUS_PENDING) {
            return true;
        }
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }
        if (!$this->prescription_item_id) {
            return true;
        }

        $item = $this->prescriptionItem;

        return $item && $item->treatmentCourse?->status === 'dang_dieu_tri' && $item->needs_purchase;
    }

    /** Gia đình được hủy: yêu cầu đang chờ, hoặc thuốc ngoài đơn đã được đồng ý nhưng chưa mua */
    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_PENDING
            || ($this->status === self::STATUS_APPROVED && $this->prescription_item_id === null);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => $this->prescription_item_id ? 'Đã kê tiếp' : 'Được mua thêm',
            self::STATUS_FULFILLED => 'Đã mua về',
            self::STATUS_STOPPED => 'Ngưng thuốc',
            self::STATUS_REJECTED => 'Không mua thêm',
            self::STATUS_CANCELLED => 'Đã hủy',
            default => 'Chờ Bác sĩ',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'bg-primary-subtle text-primary border border-primary-subtle',
            self::STATUS_FULFILLED => 'bg-success-subtle text-success border border-success-subtle',
            self::STATUS_STOPPED, self::STATUS_REJECTED => 'bg-danger-subtle text-danger border border-danger-subtle',
            self::STATUS_CANCELLED => 'bg-secondary-subtle text-secondary border',
            default => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        };
    }
}
