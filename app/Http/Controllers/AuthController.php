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

        // 1. Xử lý lưu ảnh đại diện (Tự động lưu dạng Base64 hoặc File storage)
        if ($request->filled('avatar_base64') && str_starts_with($request->input('avatar_base64'), 'data:image')) {
            // Xóa file cũ nếu có trong Storage
            if ($user->avatar && !str_starts_with($user->avatar, 'data:image') && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->input('avatar_base64');
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

        // 2. Xử lý đổi mật khẩu nếu có
        if (!empty($validated['new_password'])) {
            $user->password = Hash::make($validated['new_password']);
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

        // Tạo mã OTP 6 chữ số ngẫu nhiên
        $otp = (string) random_int(100000, 999999);

        // Lưu vào bảng password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $otp,
                'created_at' => now(),
            ]
        );

        // Gửi email chứa mã OTP
        try {
            Mail::raw("Chào {$user->name},\n\nMã xác thực OTP để đặt lại mật khẩu MedCabinet của bạn là: {$otp}\n\nMã OTP này có hiệu lực trong vòng 15 phút. Vì lý do an toàn bảo mật y tế, tuyệt đối không chia sẻ mã này cho bất kỳ ai.\n\nTrân trọng,\nĐội ngũ Hỗ trợ MedCabinet", function ($message) use ($user) {
                $message->to($user->email)->subject('Mã xác thực OTP đặt lại mật khẩu - MedCabinet');
            });
        } catch (\Exception $e) {
            Log::error('Gửi mail OTP thất bại: ' . $e->getMessage());
        }

        // Lưu email vào session để xác thực ở bước sau
        session([
            'reset_email' => $user->email,
            'demo_otp' => $otp, // Hỗ trợ hiển thị khi môi trường mailer là log
        ]);

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
            'demoOtp' => session('demo_otp'),
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

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $request->otp)
            ->first();

        if (!$record) {
            return back()->withErrors(['otp' => 'Mã OTP không chính xác. Vui lòng kiểm tra lại.']);
        }

        // Kiểm tra thời hạn 15 phút
        if (Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            return back()->withErrors(['otp' => 'Mã OTP đã hết hạn sử dụng. Vui lòng yêu cầu mã mới.']);
        }

        // Xác thực thành công -> cho phép đặt lại mật khẩu
        session(['otp_verified' => true]);

        return redirect()->route('password.reset')
            ->with('success', 'Xác thực OTP thành công! Vui lòng tạo mật khẩu mới cho tài khoản.');
    }

    /**
     * Hiển thị form tạo mật khẩu mới
     */
    public function showResetPasswordForm()
    {
        if (!session('reset_email') || !session('otp_verified')) {
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

        $email = session('reset_email');
        if (!$email || !session('otp_verified')) {
            return redirect()->route('password.request')
                ->with('warning', 'Phiên làm việc đã hết hạn. Vui lòng thực hiện lại.');
        }

        $user = User::where('email', $email)->firstOrFail();
        $user->password = Hash::make($request->password);
        $user->save();

        // Xóa mã OTP đã sử dụng
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        session()->forget(['reset_email', 'otp_verified', 'demo_otp']);

        return redirect()->route('login')
            ->with('success', 'Mật khẩu của bạn đã được cập nhật thành công! Hãy đăng nhập bằng mật khẩu mới.');
    }
}
