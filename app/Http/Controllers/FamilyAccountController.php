<?php

namespace App\Http\Controllers;

use App\Models\DoctorRoleRequest;
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
     * Chủ hộ chỉ được chuyển một tài khoản về Thành viên (thôi vai trò Bác sĩ).
     * Muốn có Bác sĩ phải gửi đề nghị để Quản trị viên hệ thống xác minh — chủ hộ không tự gán quyền chuyên môn.
     */
    public function updateRole(Request $request, User $user)
    {
        $family = $this->ensureFamilyAdmin($request);
        $this->authorizeTarget($request, $family, $user);

        $request->validate([
            'role' => ['required', Rule::in(['member'])],
        ], [
            'role.in' => 'Chỉ định Bác sĩ cần Quản trị viên hệ thống xác minh. Hãy dùng nút "Đề nghị làm Bác sĩ".',
        ]);

        $user->update(['role' => 'member']);

        return back()->with('success', "Đã chuyển \"{$user->name}\" về vai trò Thành viên gia đình.");
    }

    /**
     * Gửi đề nghị chỉ định một thành viên làm Bác sĩ / Người chăm sóc (kèm thông tin hành nghề để Admin xác minh)
     */
    public function requestDoctor(Request $request, User $user)
    {
        $family = $this->ensureFamilyAdmin($request);
        $this->authorizeTarget($request, $family, $user);
        abort_unless($user->isMember(), 422, 'Tài khoản này đã là Bác sĩ / Người chăm sóc.');

        $validated = $request->validate([
            'credentials' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'credentials.required' => 'Vui lòng ghi nơi công tác / chuyên khoa / số chứng chỉ hành nghề để Quản trị viên xác minh.',
            'credentials.min' => 'Thông tin hành nghề quá ngắn, vui lòng ghi rõ hơn.',
        ]);

        if (DoctorRoleRequest::pending()->where('user_id', $user->id)->exists()) {
            return back()->with('info', "\"{$user->name}\" đã có đề nghị đang chờ Quản trị viên duyệt.");
        }

        DoctorRoleRequest::create([
            'family_id' => $family->id,
            'user_id' => $user->id,
            'requested_by' => $request->user()->id,
            'credentials' => $validated['credentials'],
            'status' => DoctorRoleRequest::STATUS_PENDING,
        ]);

        return back()->with('success', "Đã gửi đề nghị chỉ định \"{$user->name}\" làm Bác sĩ. Quản trị viên hệ thống sẽ xác minh trước khi cấp quyền.");
    }

    public function cancelDoctorRequest(Request $request, DoctorRoleRequest $doctorRoleRequest)
    {
        $family = $this->ensureFamilyAdmin($request);
        abort_unless((int) $doctorRoleRequest->family_id === (int) $family->id, 404);

        if ($doctorRoleRequest->isPending()) {
            $doctorRoleRequest->update(['status' => DoctorRoleRequest::STATUS_CANCELLED]);
        }

        return back()->with('success', 'Đã hủy đề nghị chỉ định Bác sĩ.');
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
