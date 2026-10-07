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

    public function isSuperAdmin(): bool
    {
        return $this->isSystemAdmin();
    }

    public function isFamilyAdmin(): bool
    {
        return $this->role === 'family_admin';
    }

    public function isDoctorOrCaregiver(): bool
    {
        return $this->role === 'doctor_caregiver';
    }

    public function isDoctorCaregiver(): bool
    {
        return $this->isDoctorOrCaregiver();
    }

    public function isMember(): bool
    {
        return $this->role === 'member';
    }

    /**
     * Quyền quản lý Tủ thuốc (Thêm, sửa, xóa, nhập Excel)
     */
    public function canManageCabinet(): bool
    {
        return $this->isFamilyAdmin() || $this->isSystemAdmin();
    }

    /**
     * Quyền quản lý Sổ thành viên gia đình (Thêm, xóa thành viên)
     */
    public function canManageMembers(): bool
    {
        return $this->isFamilyAdmin() || $this->isSystemAdmin();
    }

    /**
     * Quyền chỉnh sửa hồ sơ sức khỏe một thành viên
     * - Chủ hộ hoặc Super Admin: sửa được mọi hồ sơ
     * - Thành viên thường: chỉ sửa được hồ sơ của chính mình
     */
    public function canEditMember(?FamilyMember $member): bool
    {
        if ($this->canManageMembers()) {
            return true;
        }

        if (!$member) {
            return false;
        }

        return (int) $member->user_id === (int) $this->id;
    }

    /**
     * Quyền kê đơn / lập phác đồ điều trị
     */
    public function canManageTreatments(): bool
    {
        return $this->isFamilyAdmin() || $this->isDoctorOrCaregiver() || $this->isSystemAdmin();
    }

    /**
     * Quyền xóa đợt điều trị
     */
    public function canDeleteTreatments(): bool
    {
        return $this->isFamilyAdmin() || $this->isSystemAdmin();
    }

    /**
     * Quyền cấu hình và tạo lịch uống thuốc
     */
    public function canManageSchedules(): bool
    {
        return $this->isFamilyAdmin() || $this->isDoctorOrCaregiver() || $this->isSystemAdmin();
    }

    /**
     * Quyền xóa khoản chi phí y tế
     */
    public function canDeleteExpenses(): bool
    {
        return $this->isFamilyAdmin() || $this->isSystemAdmin();
    }

    /**
     * Quyền xóa cảnh báo an toàn
     */
    public function canDeleteAlerts(): bool
    {
        return $this->isFamilyAdmin() || $this->isSystemAdmin();
    }

    /**
     * Nhãn vai trò tiếng Việt hiển thị trên giao diện
     */
    public function getRoleNameAttribute(): string
    {
        return match ($this->role) {
            'system_admin' => 'Quản trị viên Hệ thống',
            'family_admin' => 'Chủ hộ (Admin Gia đình)',
            'doctor_caregiver' => 'Bác sĩ / Chăm sóc y tế',
            'member' => 'Thành viên gia đình',
            default => 'Thành viên gia đình',
        };
    }

    /**
     * CSS Badge class cho vai trò
     */
    public function getRoleBadgeClassAttribute(): string
    {
        return match ($this->role) {
            'system_admin' => 'bg-danger text-white',
            'family_admin' => 'bg-primary text-white',
            'doctor_caregiver' => 'bg-success text-white',
            'member' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            default => 'bg-secondary-subtle text-secondary',
        };
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

