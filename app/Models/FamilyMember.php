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
            default => 'Người thân khác',
        };
    }

    public function getRelationshipTextAttribute(): string
    {
        return $this->relationship_label;
    }

    public function getBloodGroupAttribute(): ?string
    {
        return $this->blood_type;
    }

    public function getGenderLabelAttribute(): string
    {
        return match ($this->gender) {
            'nam' => 'Nam',
            'nu' => 'Nữ',
            default => 'Khác',
        };
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            if (str_starts_with($this->avatar, 'http') || str_starts_with($this->avatar, 'data:')) {
                return $this->avatar;
            }
            return asset('storage/' . $this->avatar);
        }

        // Tạo avatar chữ cái mặc định với màu gradient nhẹ
        $bgColor = match ($this->gender) {
            'nu' => 'e91e63',
            'nam' => '0284c7',
            default => '64748b',
        };

        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=' . $bgColor . '&color=ffffff&size=128&bold=true';
    }

    public function getIsElderlyAttribute(): bool
    {
        return $this->age !== null && $this->age >= 60;
    }

    public function getIsChildAttribute(): bool
    {
        return $this->age !== null && $this->age < 16;
    }

    public function getHasAllergiesAttribute(): bool
    {
        return !empty(trim((string)$this->allergies));
    }

    public function getHasUnderlyingDiseasesAttribute(): bool
    {
        return !empty(trim((string)$this->underlying_diseases));
    }
}
