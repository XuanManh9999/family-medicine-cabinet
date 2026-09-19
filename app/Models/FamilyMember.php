<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'user_id',
        'name',
        'relationship',
        'dob',
        'gender',
        'blood_type',
        'allergies',
        'underlying_diseases',
        'avatar',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
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

    public function treatmentCourses()
    {
        return $this->hasMany(TreatmentCourse::class);
    }

    public function medicationSchedules()
    {
        return $this->hasMany(MedicationSchedule::class);
    }

    public function medicationLogs()
    {
        return $this->hasMany(MedicationLog::class);
    }

    public function expenses()
    {
        return $this->hasMany(MedicineExpense::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->dob ? Carbon::parse($this->dob)->age : null;
    }

    public function getRelationshipLabelAttribute(): string
    {
        return match ($this->relationship) {
            'ong_ba' => 'Ông / Bà',
            'bo_me' => 'Bố / Mẹ',
            'vo_chong' => 'Vợ / Chồng',
            'con_cai' => 'Con cái',
            'ban_than' => 'Bản thân',
            default => 'Thành viên khác',
        };
    }
}
