<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreatmentCourse extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'family_member_id',
        'title',
        'hospital_doctor',
        'diagnosis',
        'start_date',
        'end_date',
        'prescription_image_path',
        'ai_analysis_data',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'ai_analysis_data' => 'array',
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

    public function prescriptionItems()
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function expenses()
    {
        return $this->hasMany(MedicineExpense::class);
    }

    public function schedules()
    {
        return $this->hasManyThrough(MedicationSchedule::class, PrescriptionItem::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'dang_dieu_tri');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'da_hoan_thanh');
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'dang_dieu_tri' => 'Đang điều trị',
            'da_hoan_thanh' => 'Đã hoàn thành',
            'tam_dung' => 'Tạm dừng',
            default => 'Không rõ',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'dang_dieu_tri' => 'bg-success',
            'da_hoan_thanh' => 'bg-secondary',
            'tam_dung' => 'bg-warning text-dark',
            default => 'bg-light text-dark',
        };
    }

    public function getHospitalNameAttribute(): string
    {
        if (!$this->hospital_doctor) {
            return '';
        }
        $parts = explode('-', $this->hospital_doctor, 2);
        return trim($parts[0]);
    }

    public function getDoctorNameAttribute(): string
    {
        if (!$this->hospital_doctor) {
            return '';
        }
        $parts = explode('-', $this->hospital_doctor, 2);
        return count($parts) > 1 ? trim($parts[1]) : '';
    }

    public function getPrescriptionImageUrlAttribute(): ?string
    {
        if (!$this->prescription_image_path) {
            return null;
        }
        if (str_starts_with($this->prescription_image_path, 'http://') || str_starts_with($this->prescription_image_path, 'https://')) {
            return $this->prescription_image_path;
        }
        return asset('storage/' . $this->prescription_image_path);
    }

    public function getSafetyRiskLevelAttribute(): string
    {
        return $this->ai_analysis_data['risk_level'] ?? ($this->ai_analysis_data['safety_status'] ?? 'safe');
    }
}
