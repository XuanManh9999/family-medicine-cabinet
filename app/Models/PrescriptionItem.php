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
