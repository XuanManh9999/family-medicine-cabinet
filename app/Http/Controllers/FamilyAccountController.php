<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Chủ hộ quản lý các tài khoản đăng nhập trong hộ gia đình của mình:
 * chỉ định Bác sĩ / Người chăm sóc, gỡ tài khoản khỏi gia đình, tạo lại mã mời
 */
class FamilyAccountController extends Controller
{
    /**
     * Đổi vai trò một tài khoản trong gia đình giữa Thành viên và Bác sĩ / Người chăm sóc
     */
    public function updateRole(Request $request, User $user)
    {
        $family = $this->ensureFamilyAdmin($request);
        $this->authorizeTarget($request, $family, $user);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['member', 'doctor_caregiver'])],
        ], [
            'role.in' => 'Chủ hộ chỉ có thể chỉ định vai trò Thành viên hoặc Bác sĩ / Người chăm sóc.',
        ]);

        $user->update(['role' => $validated['role']]);

        return back()->with('success', "Đã cập nhật vai trò của \"{$user->name}\" thành {$user->fresh()->role_name}.");
    }

    /**
     * Gỡ một tài khoản ra khỏi hộ gia đình (giữ nguyên hồ sơ sức khỏe, chỉ bỏ liên kết đăng nhập)
     */
    public function remove(Request $request, User $user)
    {
        $family = $this->ensureFamilyAdmin($request);
        $this->authorizeTarget($request, $family, $user);

        DB::transaction(function () use ($user, $family) {
            FamilyMember::where('family_id', $family->id)
                ->where('user_id', $user->id)
                ->update(['user_id' => null]);

            $user->forceFill([
                'family_id' => null,
                'role' => 'member',
                'remember_token' => Str::random(60),
            ])->save();
        });

        return back()->with('success', "Đã gỡ tài khoản \"{$user->name}\" khỏi hộ gia đình. Hồ sơ sức khỏe của người này vẫn được giữ lại.");
    }

    /**
     * Tạo lại mã mời gia đình (mã cũ hết hiệu lực ngay lập tức)
     */
    public function regenerateCode(Request $request)
    {
        $family = $this->ensureFamilyAdmin($request);

        do {
            $code = 'FAM-' . strtoupper(Str::random(6));
        } while (Family::where('code', $code)->exists());

        $family->update(['code' => $code]);

        return back()->with('success', "Đã tạo mã mời mới: {$code}. Mã cũ không còn sử dụng được.");
    }

    private function ensureFamilyAdmin(Request $request): Family
    {
        $family = $request->user()->family;
        abort_unless($family, 403, 'Tài khoản chưa thuộc hộ gia đình.');
        abort_unless($request->user()->isFamilyAdmin(), 403, 'Chỉ Chủ hộ mới có quyền quản lý tài khoản trong gia đình.');

        return $family;
    }

    /**
     * Chỉ thao tác trên tài khoản cùng gia đình, không phải chính mình, không phải Chủ hộ / Quản trị viên khác
     */
    private function authorizeTarget(Request $request, Family $family, User $user): void
    {
        abort_unless((int) $user->family_id === (int) $family->id, 404, 'Không tìm thấy tài khoản trong gia đình của bạn.');
        abort_if($user->id === $request->user()->id, 403, 'Bạn không thể tự thay đổi tài khoản của chính mình tại đây.');
        abort_if($user->isFamilyAdmin() || $user->isSystemAdmin(), 403, 'Không thể thay đổi tài khoản Chủ hộ hoặc Quản trị viên hệ thống.');
    }
}
