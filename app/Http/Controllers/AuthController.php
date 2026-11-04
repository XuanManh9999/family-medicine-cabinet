<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Hiển thị trang đăng nhập
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Xử lý đăng nhập
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            if (Auth::user()->isSystemAdmin()) {
                return redirect()->intended(route('admin.dashboard'))
                    ->with('success', 'Chào mừng Quản trị viên hệ thống, ' . Auth::user()->name . '!');
            }

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Chào mừng bạn quay trở lại, ' . Auth::user()->name . '!');
        }

        return back()->withErrors([
            'email' => 'Thông tin đăng nhập không chính xác hoặc tài khoản không tồn tại.',
        ])->onlyInput('email');
    }

    /**
     * Hiển thị trang đăng ký
     */
    public function showRegisterForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    /**
     * Xử lý đăng ký tài khoản (Tạo gia đình mới HOẶC tham gia qua mã mời)
     */
    public function register(Request $request)
    {
        $registrationType = $request->input('registration_type', 'create_family');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'registration_type' => ['required', 'in:create_family,join_family'],
        ];

        if ($registrationType === 'create_family') {
            $rules['family_name'] = ['required', 'string', 'max:255'];
            $rules['family_address'] = ['nullable', 'string', 'max:255'];
        } else {
            $rules['family_code'] = ['required', 'string', 'exists:families,code'];
        }

        $messages = [
            'name.required' => 'Vui lòng nhập họ và tên của bạn.',
            'email.required' => 'Vui lòng nhập email đăng ký.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email này đã được sử dụng bởi một tài khoản khác.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải chứa ít nhất 6 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'family_name.required' => 'Vui lòng đặt tên cho Hộ gia đình của bạn.',
            'family_code.required' => 'Vui lòng nhập mã mời gia đình.',
            'family_code.exists' => 'Mã mời gia đình không tồn tại trong hệ thống. Vui lòng kiểm tra lại.',
        ];

        $validated = $request->validate($rules, $messages);

        // Tạo gia đình, tài khoản và hồ sơ trong một giao dịch: lỗi giữa chừng không để lại dữ liệu mồ côi
        [$family, $user] = DB::transaction(function () use ($registrationType, $validated) {
            if ($registrationType === 'create_family') {
                // Sinh mã mời độc nhất cho Hộ gia đình mới: FAM-XXXX
                do {
                    $familyCode = 'FAM-' . strtoupper(Str::random(6));
                } while (Family::where('code', $familyCode)->exists());

                $family = Family::create([
                    'name' => $validated['family_name'],
                    'code' => $familyCode,
                    'address' => $validated['family_address'] ?? null,
                ]);

                $user = User::create([
                    'family_id' => $family->id,
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'role' => 'family_admin',
                    'password' => Hash::make($validated['password']),
                ]);

                // Cập nhật người tạo cho gia đình
                $family->update(['created_by' => $user->id]);

                // Tạo luôn hồ sơ thành viên cá nhân cho người đăng ký
                FamilyMember::create([
                    'family_id' => $family->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'relationship' => 'ban_than',
                    'gender' => 'nam',
                ]);
            } else {
                $family = Family::where('code', $validated['family_code'])->firstOrFail();

                $user = User::create([
                    'family_id' => $family->id,
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'role' => 'member',
                    'password' => Hash::make($validated['password']),
                ]);

                FamilyMember::create([
                    'family_id' => $family->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'relationship' => 'ban_than',
                    'gender' => 'nam',
                ]);
            }

            return [$family, $user];
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with(
            'success',
            'Đăng ký tài khoản thành công! Mã hộ gia đình của bạn là: ' . $family->code
        );
    }

    /**
     * Đăng xuất
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Bạn đã đăng xuất khỏi hệ thống an toàn.');
    }

    /**
     * Hiển thị trang Hồ sơ cá nhân
     */
    public function profile()
    {
        $user = Auth::user()->load(['family.members', 'family.users']);
        return view('auth.profile', compact('user'));
    }

    /**
     * Cập nhật thông tin cá nhân & mật khẩu
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:5120'],
            'avatar_base64' => ['nullable', 'string'],
            'current_password' => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password' => ['nullable', 'confirmed', Password::min(6)],
        ], [
            'name.required' => 'Vui lòng nhập họ và tên.',
            'avatar.image' => 'Tệp tải lên phải là hình ảnh hợp lệ.',
            'avatar.mimes' => 'Hình ảnh phải có định dạng: jpeg, png, jpg, webp, gif.',
            'avatar.max' => 'Dung lượng hình ảnh không được vượt quá 5MB.',
            'current_password.current_password' => 'Mật khẩu hiện tại không chính xác.',
            'new_password.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'new_password.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        $user->name = $validated['name'];
        $user->phone = $validated['phone'] ?? null;

        // 1. Xử lý lưu ảnh đại diện: ảnh Base64 (đã nén ở trình duyệt) được giải mã và lưu thành file,
        //    không lưu chuỗi Base64 vào CSDL (tránh vượt max_allowed_packet của MySQL và nhồi HTML mỗi trang)
        if ($request->filled('avatar_base64') && str_starts_with($request->input('avatar_base64'), 'data:image')) {
            $storedPath = $this->storeBase64Avatar((string) $request->input('avatar_base64'));
            if (!$storedPath) {
                return back()->withErrors(['avatar' => 'Ảnh đại diện không hợp lệ (chỉ nhận JPG, PNG, WEBP, GIF tối đa 5MB).'])->withInput();
            }
            if ($user->avatar && !str_starts_with($user->avatar, 'data:image') && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $storedPath;
        } elseif ($request->hasFile('avatar')) {
            // Xóa file cũ nếu có trong Storage
            if ($user->avatar && !str_starts_with($user->avatar, 'data:image') && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        } elseif ($request->boolean('remove_avatar')) {
            if ($user->avatar && !str_starts_with($user->avatar, 'data:image') && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = null;
        }

        // 2. Xử lý đổi mật khẩu nếu có (đăng xuất các thiết bị đang "ghi nhớ đăng nhập")
        if (!empty($validated['new_password'])) {
            $user->password = Hash::make($validated['new_password']);
            $user->setRememberToken(Str::random(60));
        }

        $user->save();

        // Cập nhật tên trong hồ sơ thành viên tương ứng nếu có
        if ($user->familyMember) {
            $user->familyMember->update(['name' => $user->name]);
        }

        return back()->with('success', 'Hồ sơ cá nhân và ảnh đại diện đã được cập nhật thành công.');
    }

    /**
     * Hiển thị trang yêu cầu quên mật khẩu
     */
    public function showForgotPasswordForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.forgot-password');
    }

    /**
     * Gửi mã OTP về email để đặt lại mật khẩu
     */
    public function sendResetOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ], [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không đúng định dạng.',
            'email.exists' => 'Không tìm thấy tài khoản nào khớp với email này.',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        // Yêu cầu mã mới luôn hủy kết quả xác thực OTP cũ trong phiên (tránh dùng OTP của email A để đổi mật khẩu email B)
        session()->forget(['otp_verified', 'otp_verified_email', 'otp_verified_at', 'demo_otp']);

        // Tạo mã OTP 6 chữ số ngẫu nhiên
        $otp = (string) random_int(100000, 999999);

        // Lưu bản băm của OTP vào bảng password_reset_tokens (không lưu mã gốc)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($otp),
                'created_at' => now(),
            ]
        );
        RateLimiter::clear($this->otpAttemptKey($user->email));

        // Gửi email chứa mã OTP
        try {
            Mail::raw("Chào {$user->name},\n\nMã xác thực OTP để đặt lại mật khẩu MedCabinet của bạn là: {$otp}\n\nMã OTP này có hiệu lực trong vòng 15 phút. Vì lý do an toàn bảo mật y tế, tuyệt đối không chia sẻ mã này cho bất kỳ ai.\n\nTrân trọng,\nĐội ngũ Hỗ trợ MedCabinet", function ($message) use ($user) {
                $message->to($user->email)->subject('Mã xác thực OTP đặt lại mật khẩu - MedCabinet');
            });
        } catch (\Exception $e) {
            Log::error('Gửi mail OTP thất bại: ' . $e->getMessage());
        }

        // Lưu email vào session để xác thực ở bước sau
        session(['reset_email' => $user->email]);

        // Chỉ hiển thị OTP trên màn hình khi chạy local và mail chỉ ghi ra log (không gửi thật)
        if ($this->canShowDemoOtp()) {
            session(['demo_otp' => $otp]);
        }

        return redirect()->route('password.otp')
            ->with('success', 'Mã xác thực OTP đã được gửi đến email ' . $user->email . '. Vui lòng kiểm tra hộp thư!');
    }

    /**
     * Hiển thị form nhập mã OTP
     */
    public function showVerifyOtpForm()
    {
        if (!session('reset_email')) {
            return redirect()->route('password.request')
                ->with('warning', 'Vui lòng nhập email để nhận mã xác thực OTP.');
        }

        return view('auth.verify-otp', [
            'email' => session('reset_email'),
            'demoOtp' => $this->canShowDemoOtp() ? session('demo_otp') : null,
        ]);
    }

    /**
     * Kiểm tra mã OTP người dùng nhập vào
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'string', 'digits:6'],
        ], [
            'otp.required' => 'Vui lòng nhập mã OTP 6 chữ số.',
            'otp.digits' => 'Mã OTP phải gồm đúng 6 chữ số.',
        ]);

        $email = session('reset_email');
        if (!$email) {
            return redirect()->route('password.request')
                ->with('warning', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.');
        }

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record || Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            return back()->withErrors(['otp' => 'Mã OTP đã hết hạn hoặc không tồn tại. Vui lòng yêu cầu mã mới.']);
        }

        // Giới hạn 5 lần nhập sai cho mỗi mã OTP, vượt quá thì hủy mã
        $attemptKey = $this->otpAttemptKey($email);
        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return back()->withErrors(['otp' => 'Bạn đã nhập sai quá nhiều lần. Mã OTP đã bị hủy, vui lòng yêu cầu mã mới.']);
        }

        if (!Hash::check($request->otp, $record->token)) {
            RateLimiter::hit($attemptKey, 15 * 60);
            $remaining = max(0, 5 - RateLimiter::attempts($attemptKey));
            return back()->withErrors(['otp' => "Mã OTP không chính xác. Bạn còn {$remaining} lần thử."]);
        }

        RateLimiter::clear($attemptKey);

        // Xác thực thành công -> cho phép đặt lại mật khẩu cho ĐÚNG email này trong 15 phút
        session([
            'otp_verified' => true,
            'otp_verified_email' => $email,
            'otp_verified_at' => now()->timestamp,
        ]);

        return redirect()->route('password.reset')
            ->with('success', 'Xác thực OTP thành công! Vui lòng tạo mật khẩu mới cho tài khoản.');
    }

    /**
     * Hiển thị form tạo mật khẩu mới
     */
    public function showResetPasswordForm()
    {
        if (!$this->hasValidOtpSession()) {
            return redirect()->route('password.request')
                ->with('warning', 'Vui lòng xác thực mã OTP trước khi đặt lại mật khẩu.');
        }

        return view('auth.reset-password', [
            'email' => session('reset_email'),
        ]);
    }

    /**
     * Lưu mật khẩu mới và xóa token OTP
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(6)],
        ], [
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        if (!$this->hasValidOtpSession()) {
            session()->forget(['otp_verified', 'otp_verified_email', 'otp_verified_at']);
            return redirect()->route('password.request')
                ->with('warning', 'Phiên làm việc đã hết hạn. Vui lòng thực hiện lại.');
        }

        $email = session('reset_email');
        $user = User::where('email', $email)->firstOrFail();
        $user->password = Hash::make($request->password);
        $user->setRememberToken(Str::random(60));
        $user->save();

        // Xóa mã OTP đã sử dụng
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        session()->forget(['reset_email', 'otp_verified', 'otp_verified_email', 'otp_verified_at', 'demo_otp']);

        return redirect()->route('login')
            ->with('success', 'Mật khẩu của bạn đã được cập nhật thành công! Hãy đăng nhập bằng mật khẩu mới.');
    }

    /**
     * Phiên đặt lại mật khẩu hợp lệ: OTP đã xác thực cho đúng email, trong 15 phút, mã vẫn còn
     */
    private function hasValidOtpSession(): bool
    {
        $email = session('reset_email');
        $verifiedAt = (int) session('otp_verified_at', 0);

        return $email
            && session('otp_verified') === true
            && session('otp_verified_email') === $email
            && $verifiedAt > 0
            && now()->timestamp - $verifiedAt <= 15 * 60
            && DB::table('password_reset_tokens')->where('email', $email)->exists();
    }

    private function otpAttemptKey(string $email): string
    {
        return 'otp-verify:' . mb_strtolower($email);
    }

    /**
     * Chỉ hiện OTP "demo" trên màn hình khi chạy local và mail chỉ được ghi vào log
     */
    private function canShowDemoOtp(): bool
    {
        return app()->isLocal() && config('mail.default') === 'log';
    }

    /**
     * Giải mã ảnh Base64, kiểm tra định dạng & dung lượng, lưu vào storage/app/public/avatars
     */
    private function storeBase64Avatar(string $dataUri): ?string
    {
        if (!preg_match('/^data:image\/(png|jpe?g|webp|gif);base64,(.+)$/s', $dataUri, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || strlen($binary) === 0 || strlen($binary) > 5 * 1024 * 1024) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($extensions[$mime])) {
            return null;
        }

        $path = 'avatars/' . Str::uuid() . '.' . $extensions[$mime];
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
