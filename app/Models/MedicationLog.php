<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'family_member_id',
        'scheduled_time',
        'actual_taken_time',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_time' => 'datetime',
            'actual_taken_time' => 'datetime',
        ];
    }

    public function schedule()
    {
        return $this->belongsTo(MedicationSchedule::class, 'schedule_id');
    }

    public function familyMember()
    {
        return $this->belongsTo(FamilyMember::class, 'family_member_id');
    }

    // Scopes
    public function scopeTaken(Builder $query): Builder
    {
        return $query->where('status', 'da_uong');
    }

    public function scopeMissed(Builder $query): Builder
    {
        return $query->where('status', 'quen_uong');
    }

    public function scopeSkipped(Builder $query): Builder
    {
        return $query->where('status', 'bo_qua');
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'da_uong' => 'Đã uống',
            'quen_uong' => 'Quên uống',
            'bo_qua' => 'Bỏ qua',
            default => 'Chưa rõ',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'da_uong' => 'bg-success',
            'quen_uong' => 'bg-danger',
            'bo_qua' => 'bg-secondary',
            default => 'bg-light text-dark',
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match ($this->status) {
            'da_uong' => 'bi bi-check-circle-fill text-success',
            'quen_uong' => 'bi bi-x-circle-fill text-danger',
            'bo_qua' => 'bi bi-dash-circle-fill text-secondary',
            default => 'bi bi-clock text-muted',
        };
    }
}
