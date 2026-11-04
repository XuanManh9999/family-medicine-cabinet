<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $familyAdmin;
    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->familyAdmin = User::where('email', 'an.nguyen@gmail.com')->first();
        $this->member = User::where('email', 'hoa.tran@gmail.com')->first();
    }

    public function test_family_admin_can_assign_doctor_role_within_family(): void
    {
        $this->actingAs($this->familyAdmin)
            ->patch(route('family.users.role', $this->member), ['role' => 'doctor_caregiver'])
            ->assertSessionHas('success');

        $this->assertTrue($this->member->fresh()->isDoctorOrCaregiver());

        // Không được nâng lên Chủ hộ / Quản trị viên qua kênh này
        $this->actingAs($this->familyAdmin)
            ->patch(route('family.users.role', $this->member), ['role' => 'system_admin'])
            ->assertSessionHasErrors('role');
    }

    public function test_family_admin_cannot_touch_admins_or_other_families(): void
    {
        // Quản trị viên hệ thống không thuộc gia đình nên chủ hộ không thấy / không đổi được
        $systemAdmin = User::where('email', 'admin@medcabinet.vn')->first();
        $this->assertNull($systemAdmin->family_id);
        $this->actingAs($this->familyAdmin)
            ->patch(route('family.users.role', $systemAdmin), ['role' => 'member'])
            ->assertNotFound();

        $outsider = User::create([
            'family_id' => null,
            'name' => 'Người ngoài',
            'email' => 'outsider@example.com',
            'password' => bcrypt('password123'),
            'role' => 'member',
        ]);
        $this->actingAs($this->familyAdmin)
            ->delete(route('family.users.remove', $outsider))
            ->assertNotFound();

        // Thành viên thường không có quyền quản lý tài khoản
        $this->actingAs($this->member)
            ->post(route('family.regenerate-code'))
            ->assertForbidden();
    }

    public function test_family_admin_can_remove_account_and_keep_health_record(): void
    {
        $profileId = $this->member->familyMember->id;

        $this->actingAs($this->familyAdmin)
            ->delete(route('family.users.remove', $this->member))
            ->assertSessionHas('success');

        $this->assertNull($this->member->fresh()->family_id);
        $this->assertDatabaseHas('family_members', ['id' => $profileId, 'user_id' => null]);
    }

    public function test_family_admin_can_regenerate_invite_code(): void
    {
        $oldCode = $this->familyAdmin->family->code;

        $this->actingAs($this->familyAdmin)
            ->post(route('family.regenerate-code'))
            ->assertSessionHas('success');

        $newCode = $this->familyAdmin->family->fresh()->code;
        $this->assertNotSame($oldCode, $newCode);
        $this->assertMatchesRegularExpression('/^FAM-[A-Z0-9]{6}$/', $newCode);
    }
}
