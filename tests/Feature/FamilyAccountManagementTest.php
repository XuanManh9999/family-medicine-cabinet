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

    public function test_doctor_role_requires_system_admin_verification(): void
    {
        // Chủ hộ không tự gán được vai trò Bác sĩ (hay Quản trị viên)
        $this->actingAs($this->familyAdmin)
            ->patch(route('family.users.role', $this->member), ['role' => 'doctor_caregiver'])
            ->assertSessionHasErrors('role');
        $this->actingAs($this->familyAdmin)
            ->patch(route('family.users.role', $this->member), ['role' => 'system_admin'])
            ->assertSessionHasErrors('role');
        $this->assertTrue($this->member->fresh()->isMember());

        // Gửi đề nghị kèm thông tin hành nghề (bắt buộc), gửi trùng không tạo thêm
        $this->actingAs($this->familyAdmin)
            ->post(route('family.users.doctor-request', $this->member), [])
            ->assertSessionHasErrors('credentials');
        $this->actingAs($this->familyAdmin)
            ->post(route('family.users.doctor-request', $this->member), ['credentials' => 'BS nội khoa, BV Bạch Mai, CCHN 012345/HNO'])
            ->assertSessionHas('success');
        $this->actingAs($this->familyAdmin)
            ->post(route('family.users.doctor-request', $this->member), ['credentials' => 'BS nội khoa, BV Bạch Mai, CCHN 012345/HNO'])
            ->assertSessionHas('info');
        $this->assertSame(1, \App\Models\DoctorRoleRequest::count());
        $this->assertTrue($this->member->fresh()->isMember());
        $this->actingAs($this->familyAdmin)->get(route('profile'))->assertOk()->assertSee('Chờ Admin duyệt Bác sĩ');

        // Chỉ Quản trị viên hệ thống được duyệt
        $request = \App\Models\DoctorRoleRequest::firstOrFail();
        $this->actingAs($this->familyAdmin)->post(route('admin.doctor-requests.approve', $request))->assertForbidden();

        $admin = User::where('email', 'admin@medcabinet.vn')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.doctor-requests.index'))->assertOk()->assertSee('CCHN 012345/HNO');
        $this->actingAs($admin)->post(route('admin.doctor-requests.approve', $request), ['review_note' => 'Đã tra cứu CCHN'])->assertRedirect();

        $this->assertTrue($this->member->fresh()->isDoctorOrCaregiver());
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertDatabaseHas('smart_alerts', ['type' => 'doctor_request', 'family_id' => $this->familyAdmin->family_id]);

        // Chủ hộ vẫn cho thôi vai trò Bác sĩ được
        $this->actingAs($this->familyAdmin)
            ->patch(route('family.users.role', $this->member), ['role' => 'member'])
            ->assertSessionHas('success');
        $this->assertTrue($this->member->fresh()->isMember());
    }

    public function test_system_admin_can_reject_doctor_request_with_reason(): void
    {
        $this->actingAs($this->familyAdmin)
            ->post(route('family.users.doctor-request', $this->member), ['credentials' => 'Điều dưỡng tại nhà, chưa có chứng chỉ']);
        $request = \App\Models\DoctorRoleRequest::firstOrFail();
        $admin = User::where('email', 'admin@medcabinet.vn')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.doctor-requests.reject', $request), [])->assertSessionHasErrors('review_note');
        $this->actingAs($admin)->post(route('admin.doctor-requests.reject', $request), ['review_note' => 'Không xác minh được CCHN'])
            ->assertRedirect();

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertTrue($this->member->fresh()->isMember());
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
