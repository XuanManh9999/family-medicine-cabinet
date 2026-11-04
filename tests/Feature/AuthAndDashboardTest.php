<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_is_redirected_to_login_when_visiting_home_or_dashboard(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');

        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Đăng nhập tài khoản');
    }

    public function test_user_can_login_with_seeded_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'an.nguyen@gmail.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'an.nguyen@gmail.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_register_creates_new_family_and_admin_user(): void
    {
        $response = $this->post('/register', [
            'registration_type' => 'create_family',
            'family_name' => 'Gia đình Bác Minh',
            'family_address' => 'Hà Nội',
            'name' => 'Nguyễn Văn Minh',
            'email' => 'nguyenvanminh@example.com',
            'phone' => '0988888888',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'nguyenvanminh@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('family_admin', $user->role);
        $this->assertNotNull($user->family);
        $this->assertEquals('Gia đình Bác Minh', $user->family->name);
        $this->assertStringStartsWith('FAM-', $user->family->code);
    }

    public function test_register_with_existing_family_code_creates_member_user(): void
    {
        $family = Family::where('code', 'FAM-VN2026')->first();
        $this->assertNotNull($family);

        $response = $this->post('/register', [
            'registration_type' => 'join_family',
            'family_code' => 'FAM-VN2026',
            'name' => 'Thành viên mới',
            'email' => 'newmember@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'newmember@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('member', $user->role);
        $this->assertEquals($family->id, $user->family_id);
    }

    public function test_authenticated_user_can_view_dashboard_with_family_data(): void
    {
        $user = User::where('email', 'an.nguyen@gmail.com')->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Gia đình Bác Nguyễn Văn An');
        $response->assertSee('FAM-VN2026');
        $response->assertSee('Tổng thuốc trong tủ');
        $response->assertSee('Thuốc đã hết hạn');
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::where('email', 'an.nguyen@gmail.com')->first();

        $response = $this->actingAs($user)->post('/logout');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_all_user_roles_can_view_profile_page(): void
    {
        $family = Family::where('code', 'FAM-VN2026')->first();

        // 1. System Admin
        $systemAdmin = User::where('email', 'admin@medcabinet.vn')->first();
        $this->actingAs($systemAdmin)->get(route('profile'))->assertStatus(200)->assertSee('Quản trị viên');

        // 2. Family Admin
        $familyAdmin = User::where('email', 'an.nguyen@gmail.com')->first();
        $this->actingAs($familyAdmin)->get(route('profile'))->assertStatus(200)->assertSee('Chủ hộ');

        // 3. Member
        $member = User::where('email', 'hoa.tran@gmail.com')->first();
        $this->actingAs($member)->get(route('profile'))->assertStatus(200)->assertSee('Thành viên');

        // 4. Doctor / Caregiver
        $doctor = User::create([
            'family_id' => $family->id,
            'name' => 'Bác sĩ Minh',
            'email' => 'bacsi.minh@gmail.com',
            'role' => 'doctor_caregiver',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($doctor)->get(route('profile'))->assertStatus(200)->assertSee('Bác sĩ / Chăm sóc');
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::where('email', 'an.nguyen@gmail.com')->first();

        $response = $this->actingAs($user)->post('/profile', [
            'name' => 'Nguyễn Văn An Cập Nhật',
            'phone' => '0999999999',
        ]);

        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertEquals('Nguyễn Văn An Cập Nhật', $user->name);
        $this->assertEquals('0999999999', $user->phone);
    }

    public function test_user_can_request_password_reset_otp(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => 'an.nguyen@gmail.com',
        ]);

        $response->assertRedirect('/verify-otp');
        $response->assertSessionHas('reset_email', 'an.nguyen@gmail.com');

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'an.nguyen@gmail.com',
        ]);
    }

    public function test_user_cannot_request_otp_for_unregistered_email(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => 'khongtontai@example.com',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_verify_valid_otp_and_reset_password(): void
    {
        // 1. Gửi yêu cầu OTP (mã gốc không còn được lưu trong CSDL)
        $this->post('/forgot-password', [
            'email' => 'an.nguyen@gmail.com',
        ]);

        $tokenRecord = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', 'an.nguyen@gmail.com')
            ->first();
        $this->assertNotNull($tokenRecord);
        $this->assertDoesNotMatchRegularExpression('/^\d{6}$/', $tokenRecord->token);

        // Gán một mã OTP đã biết để kiểm thử luồng xác thực
        $otp = '246810';
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', 'an.nguyen@gmail.com')
            ->update(['token' => \Illuminate\Support\Facades\Hash::make($otp)]);

        // 2. Xác thực OTP thành công
        $verifyResponse = $this->post('/verify-otp', [
            'otp' => $otp,
        ]);
        $verifyResponse->assertRedirect('/reset-password');
        $verifyResponse->assertSessionHas('otp_verified', true);
        $verifyResponse->assertSessionHas('otp_verified_email', 'an.nguyen@gmail.com');

        // 3. Đặt lại mật khẩu mới
        $resetResponse = $this->post('/reset-password', [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $resetResponse->assertRedirect('/login');
        $resetResponse->assertSessionHas('success');

        // 4. Thử đăng nhập lại bằng mật khẩu mới
        $loginResponse = $this->post('/login', [
            'email' => 'an.nguyen@gmail.com',
            'password' => 'newpassword123',
        ]);
        $loginResponse->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_user_can_upload_avatar_file(): void
    {
        Storage::fake('public');
        $user = User::where('email', 'an.nguyen@gmail.com')->first();

        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)->post('/profile', [
            'name' => 'Nguyễn Văn An',
            'avatar' => $file,
        ]);

        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertStringStartsWith('avatars/', $user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_base64_avatar_is_decoded_and_stored_as_file(): void
    {
        Storage::fake('public');
        $user = User::where('email', 'an.nguyen@gmail.com')->first();
        $base64String = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->actingAs($user)->post('/profile', [
            'name' => 'Nguyễn Văn An',
            'avatar_base64' => $base64String,
        ]);

        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertStringStartsWith('avatars/', $user->avatar);
        $this->assertStringEndsWith('.png', $user->avatar);
        Storage::disk('public')->assertExists($user->avatar);

        // Dữ liệu giả mạo định dạng ảnh bị từ chối
        $this->actingAs($user)->post('/profile', [
            'name' => 'Nguyễn Văn An',
            'avatar_base64' => 'data:image/png;base64,' . base64_encode('<?php echo 1; ?>'),
        ])->assertSessionHasErrors('avatar');
    }

    public function test_verified_otp_cannot_be_reused_to_reset_another_email(): void
    {
        $knownOtp = '135790';

        // Kẻ tấn công xác thực OTP cho email của chính mình
        $this->post('/forgot-password', ['email' => 'hoa.tran@gmail.com']);
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', 'hoa.tran@gmail.com')
            ->update(['token' => \Illuminate\Support\Facades\Hash::make($knownOtp)]);
        $this->post('/verify-otp', ['otp' => $knownOtp])->assertRedirect('/reset-password');

        // ...rồi đổi sang email nạn nhân: cờ xác thực cũ phải bị hủy
        $this->post('/forgot-password', ['email' => 'an.nguyen@gmail.com']);
        $this->post('/reset-password', [
            'password' => 'hacked12345',
            'password_confirmation' => 'hacked12345',
        ])->assertRedirect(route('password.request'));

        $victim = User::where('email', 'an.nguyen@gmail.com')->first();
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('hacked12345', $victim->password));
    }

    public function test_otp_is_invalidated_after_too_many_wrong_attempts(): void
    {
        $this->post('/forgot-password', ['email' => 'an.nguyen@gmail.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/verify-otp', ['otp' => '000000'])->assertSessionHasErrors('otp');
        }
        $this->post('/verify-otp', ['otp' => '000000'])->assertSessionHasErrors('otp');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'an.nguyen@gmail.com']);
    }

    public function test_otp_and_demo_accounts_are_not_shown_outside_local(): void
    {
        // Môi trường kiểm thử (testing) không phải local: không lộ OTP và tài khoản demo
        $this->post('/forgot-password', ['email' => 'an.nguyen@gmail.com'])
            ->assertSessionMissing('demo_otp');
        $this->get('/verify-otp')->assertOk()->assertDontSee('Điền nhanh');
        $this->get('/login')->assertOk()->assertDontSee('admin@medcabinet.vn');
    }
}
