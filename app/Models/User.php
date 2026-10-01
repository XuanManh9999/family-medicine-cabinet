<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'family_id',
        'name',
        'email',
        'phone',
        'avatar',
        'role',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }

    public function familyMember()
    {
        return $this->hasOne(FamilyMember::class);
    }

    public function smartAlerts()
    {
        return $this->hasMany(SmartAlert::class);
    }

    public function isSystemAdmin(): bool
    {
        return $this->role === 'system_admin';
    }

    public function isFamilyAdmin(): bool
    {
        return $this->role === 'family_admin';
    }

    public function isDoctorOrCaregiver(): bool
    {
        return $this->role === 'doctor_caregiver';
    }

    /**
     * Lấy URL hiển thị của ảnh đại diện (Hỗ trợ cả Base64 và file Storage)
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (empty($this->avatar)) {
            return null;
        }

        if (str_starts_with($this->avatar, 'data:image')) {
            return $this->avatar;
        }

        return asset('storage/' . $this->avatar);
    }
}
