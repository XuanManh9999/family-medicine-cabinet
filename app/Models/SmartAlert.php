<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmartAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'user_id',
        'type',
        'level',
        'title',
        'content',
        'is_read',
        'action_url',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeDanger(Builder $query): Builder
    {
        return $query->where('level', 'danger');
    }

    public function scopeWarning(Builder $query): Builder
    {
        return $query->where('level', 'warning');
    }

    public function scopeRecent(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    public function scopeLevel(Builder $query, ?string $level): Builder
    {
        if ($level && in_array($level, ['danger', 'warning', 'info'])) {
            return $query->where('level', $level);
        }
        return $query;
    }

    public function scopeType(Builder $query, ?string $type): Builder
    {
        if ($type) {
            return $query->where('type', $type);
        }
        return $query;
    }

    // Accessors
    public function getMessageAttribute(): string
    {
        return $this->content ?? '';
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'expired' => 'Thuốc đã hết hạn',
            'near_expiry' => 'Cận date (< 30 ngày)',
            'low_stock' => 'Sắp hết tồn kho',
            'schedule_reminder' => 'Nhắc nhở uống thuốc',
            'drug_interaction' => 'Tương tác thuốc nguy hiểm',
            default => 'Cảnh báo y tế',
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'expired' => 'bi-exclamation-octagon-fill text-danger',
            'near_expiry' => 'bi-hourglass-split text-warning',
            'low_stock' => 'bi-box-seam text-warning',
            'schedule_reminder' => 'bi-alarm text-info',
            'drug_interaction' => 'bi-capsule-pill text-danger',
            default => 'bi-bell text-secondary',
        };
    }

    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            'danger' => 'Nguy hiểm',
            'warning' => 'Cảnh báo',
            'info' => 'Thông tin',
            default => 'Thông thường',
        };
    }

    public function getLevelBadgeClassAttribute(): string
    {
        return match ($this->level) {
            'danger' => 'bg-danger text-white',
            'warning' => 'bg-warning text-dark',
            'info' => 'bg-info text-dark',
            default => 'bg-secondary text-white',
        };
    }

    public function getLevelSubtleBadgeClassAttribute(): string
    {
        return match ($this->level) {
            'danger' => 'bg-danger-subtle text-danger border border-danger-subtle',
            'warning' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
            'info' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
            default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }
}
