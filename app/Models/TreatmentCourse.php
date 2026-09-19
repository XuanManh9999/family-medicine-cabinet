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

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'dang_dieu_tri');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'da_hoan_thanh');
    }
}
