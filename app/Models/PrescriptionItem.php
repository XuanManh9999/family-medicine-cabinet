<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_course_id',
        'medicine_id',
        'medicine_name',
        'dosage',
        'frequency',
        'instructions',
        'total_prescribed_quantity',
        'unit',
        'meal_timing',
    ];

    protected function casts(): array
    {
        return [
            'total_prescribed_quantity' => 'decimal:2',
        ];
    }

    public function treatmentCourse()
    {
        return $this->belongsTo(TreatmentCourse::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function schedules()
    {
        return $this->hasMany(MedicationSchedule::class);
    }

    /**
     * Thời điểm uống so với bữa ăn: ưu tiên cột trên thuốc, dữ liệu cũ thì lấy theo lịch uống đầu tiên
     */
    public function getEffectiveMealTimingAttribute(): string
    {
        if (!empty($this->attributes['meal_timing'])) {
            return $this->attributes['meal_timing'];
        }
        return $this->schedules->first()?->meal_timing ?? 'sau_an';
    }

    public function getMealTimingLabelAttribute(): string
    {
        return match ($this->effective_meal_timing) {
            'truoc_an' => 'Trước ăn 30p',
            'trong_khi_an' => 'Trong bữa ăn',
            'khong_phu_thuoc' => 'Không phụ thuộc',
            default => 'Sau khi ăn no',
        };
    }

    public function getDosageMorningAttribute(): ?string
    {
        $sched = $this->schedules->firstWhere('time_of_day', 'sang');
        return $sched ? $sched->dosage : null;
    }

    public function getDosageNoonAttribute(): ?string
    {
        $sched = $this->schedules->firstWhere('time_of_day', 'trua');
        return $sched ? $sched->dosage : null;
    }

    public function getDosageEveningAttribute(): ?string
    {
        $sched = $this->schedules->firstWhere('time_of_day', 'toi');
        return $sched ? $sched->dosage : null;
    }
}
