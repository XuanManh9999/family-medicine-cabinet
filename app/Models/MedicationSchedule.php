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
    public function scopeActiveToday(Builder $query): Builder
    {
        $today = Carbon::today();
        return $query->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $today);
            });
    }

    public function scopeForMember(Builder $query, int $memberId): Builder
    {
        return $query->where('family_member_id', $memberId);
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
