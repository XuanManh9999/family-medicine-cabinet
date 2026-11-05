<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\DateRangeFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * Danh sách tài khoản người dùng toàn hệ thống
     */
    public function index(Request $request)
    {
        $search = $request->textQuery('q');
        $role = $request->query('role');

        $query = User::with('family');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role && in_array($role, ['system_admin', 'family_admin', 'member', 'doctor_caregiver'])) {
            $query->where('role', $role);
        }

        $dateFilter = DateRangeFilter::fromRequest($request);
        $dateFilter->apply($query);

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users', 'search', 'role', 'dateFilter'));
    }

    /**
     * Cập nhật vai trò người dùng (Đổi role)
     */
    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['system_admin', 'family_admin', 'member', 'doctor_caregiver'])],
        ]);

        // Tránh tự hạ quyền của chính mình nếu là system_admin duy nhất
        if ($user->id === auth()->id() && $validated['role'] !== 'system_admin') {
            return back()->with('warning', 'Bạn không thể tự hạ quyền Quản trị viên hệ thống của chính mình.');
        }

        // Vai trò trong gia đình bắt buộc tài khoản phải thuộc một hộ gia đình
        if ($validated['role'] !== 'system_admin' && !$user->family_id) {
            return back()->with('warning', "Tài khoản \"{$user->name}\" chưa thuộc hộ gia đình nào nên không thể gán vai trò trong gia đình.");
        }

        // Không để hộ gia đình mất Chủ hộ cuối cùng
        if ($user->role === 'family_admin' && $validated['role'] !== 'family_admin') {
            $otherAdmins = User::where('family_id', $user->family_id)
                ->where('role', 'family_admin')
                ->where('id', '!=', $user->id)
                ->exists();
            if (!$otherAdmins) {
                return back()->with('warning', "\"{$user->name}\" là Chủ hộ duy nhất của gia đình. Hãy chỉ định Chủ hộ khác trước khi đổi vai trò.");
            }
        }

        // Quản trị viên hệ thống là tài khoản trung tâm: rời khỏi hộ gia đình (hồ sơ sức khỏe vẫn được giữ)
        if ($validated['role'] === 'system_admin' && $user->family_id) {
            DB::transaction(function () use ($user) {
                FamilyMember::where('user_id', $user->id)->update(['user_id' => null]);
                $user->update(['role' => 'system_admin', 'family_id' => null]);
            });

            return back()->with('success', "Đã nâng \"{$user->name}\" thành Quản trị viên Hệ thống và gỡ tài khoản này khỏi hộ gia đình cũ.");
        }

        $user->update(['role' => $validated['role']]);

        return back()->with('success', "Đã cập nhật vai trò của \"{$user->name}\" thành " . match ($validated['role']) {
            'system_admin' => 'Quản trị viên Hệ thống',
            'family_admin' => 'Chủ hộ Gia đình',
            'member' => 'Thành viên',
            'doctor_caregiver' => 'Bác sĩ / Người chăm sóc',
            default => $validated['role'],
        } . "!");
    }

    /**
     * Đặt lại mật khẩu tài khoản
     */
    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không trùng khớp.',
        ]);

        // Đổi mật khẩu đồng thời vô hiệu hóa cookie "ghi nhớ đăng nhập" cũ
        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();
        // Đăng xuất tài khoản khỏi mọi thiết bị đang đăng nhập
        $user->invalidateSessions($request->user()->is($user) ? $request->session()->getId() : null);

        return back()->with('success', "Đã đặt lại mật khẩu mới cho tài khoản \"{$user->email}\" thành công!");
    }
}
