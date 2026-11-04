<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DoctorRoleRequest;
use App\Models\SmartAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Quản trị viên xác minh thông tin hành nghề trước khi cấp vai trò Bác sĩ / Người chăm sóc cho một gia đình
 */
class AdminDoctorRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $requests = DoctorRoleRequest::with(['family', 'user', 'requester', 'reviewer'])
            ->when(in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.doctor-requests.index', [
            'requests' => $requests,
            'status' => $status,
            'pendingCount' => DoctorRoleRequest::pending()->count(),
        ]);
    }

    public function approve(Request $request, DoctorRoleRequest $doctorRoleRequest)
    {
        $validated = $request->validate(['review_note' => ['nullable', 'string', 'max:1000']]);

        $result = DB::transaction(function () use ($doctorRoleRequest, $validated, $request) {
            $locked = DoctorRoleRequest::whereKey($doctorRoleRequest->id)->lockForUpdate()->first();
            if (!$locked || !$locked->isPending()) {
                return 'processed';
            }

            $user = $locked->user;
            // Tài khoản phải vẫn ở đúng gia đình và vẫn là Thành viên
            if (!$user || (int) $user->family_id !== (int) $locked->family_id || !$user->isMember()) {
                $locked->update([
                    'status' => DoctorRoleRequest::STATUS_CANCELLED,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'review_note' => 'Tài khoản không còn thuộc gia đình / không còn là Thành viên.',
                ]);
                return 'invalid';
            }

            $user->update(['role' => 'doctor_caregiver']);
            $locked->update([
                'status' => DoctorRoleRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'] ?? null,
            ]);

            SmartAlert::create([
                'family_id' => $locked->family_id,
                'user_id' => $locked->requested_by,
                'type' => 'doctor_request',
                'level' => 'info',
                'title' => "Đã duyệt Bác sĩ cho gia đình: {$user->name}",
                'content' => "Quản trị viên đã xác minh và cấp vai trò Bác sĩ / Người chăm sóc cho \"{$user->name}\". Từ nay tài khoản này kê đơn, lập lịch và xác nhận thuốc cho gia đình.",
                'is_read' => false,
                'action_url' => route('profile', [], false),
            ]);

            return 'approved';
        });

        return back()->with(...match ($result) {
            'approved' => ['success', 'Đã cấp vai trò Bác sĩ / Người chăm sóc.'],
            'invalid' => ['warning', 'Tài khoản không còn thuộc gia đình hoặc đã đổi vai trò — đề nghị đã được hủy.'],
            default => ['warning', 'Đề nghị này đã được xử lý trước đó.'],
        });
    }

    public function reject(Request $request, DoctorRoleRequest $doctorRoleRequest)
    {
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ], [
            'review_note.required' => 'Vui lòng ghi lý do từ chối (VD: không xác minh được chứng chỉ hành nghề).',
        ]);

        if (!$doctorRoleRequest->isPending()) {
            return back()->with('warning', 'Đề nghị này đã được xử lý trước đó.');
        }

        $doctorRoleRequest->update([
            'status' => DoctorRoleRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'],
        ]);

        SmartAlert::create([
            'family_id' => $doctorRoleRequest->family_id,
            'user_id' => $doctorRoleRequest->requested_by,
            'type' => 'doctor_request',
            'level' => 'warning',
            'title' => "Chưa duyệt Bác sĩ: {$doctorRoleRequest->user?->name}",
            'content' => "Quản trị viên chưa cấp vai trò Bác sĩ. Lý do: {$validated['review_note']}",
            'is_read' => false,
            'action_url' => route('profile', [], false),
        ]);

        return back()->with('success', 'Đã từ chối đề nghị chỉ định Bác sĩ.');
    }
}
