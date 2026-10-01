<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * Danh sách tài khoản người dùng toàn hệ thống
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));
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

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users', 'search', 'role'));
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

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', "Đã đặt lại mật khẩu mới cho tài khoản \"{$user->email}\" thành công!");
    }
}
