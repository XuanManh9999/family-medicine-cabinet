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
}
