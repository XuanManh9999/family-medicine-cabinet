<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Nhật ký đã nhắc một cữ thuốc (chống nhắc trùng)
 */
class MedicationReminder extends Model
{
    public const STAGE_DUE = 'due';
    public const STAGE_MISSED = 'missed';

    protected $fillable = [
        'medication_schedule_id',
        'scheduled_for',
        'stage',
        'smart_alert_id',
        'emailed_to',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
        ];
    }

    public function schedule()
    {
        return $this->belongsTo(MedicationSchedule::class, 'medication_schedule_id');
    }

    public function alert()
    {
        return $this->belongsTo(SmartAlert::class, 'smart_alert_id');
    }
}
