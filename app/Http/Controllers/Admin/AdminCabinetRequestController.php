<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CabinetRequest;
use App\Models\RefillRequest;
use App\Models\User;
use Illuminate\Http\Request;

class AdminCabinetRequestController extends Controller
{
    /**
     * Đề nghị nhập thuốc của mọi gia đình — ưu tiên các hộ chưa có Bác sĩ (Quản trị viên duyệt thay)
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $scope = $request->query('scope', 'no_doctor');

        $familiesWithDoctor = User::where('role', 'doctor_caregiver')
            ->whereNotNull('family_id')
            ->distinct()
            ->pluck('family_id');

        $query = CabinetRequest::with(['family', 'requester', 'reviewer'])->latest();

        if (in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $query->where('status', $status);
        }
        if ($scope === 'no_doctor') {
            $query->whereNotIn('family_id', $familiesWithDoctor);
        }

        $requests = $query->paginate(20)->withQueryString();

        $pendingNoDoctor = CabinetRequest::pending()->whereNotIn('family_id', $familiesWithDoctor)->count();
        $pendingAll = CabinetRequest::pending()->count();

        // Yêu cầu bổ sung thuốc của các hộ chưa có Bác sĩ → Quản trị viên kê tiếp / cho ngưng thay
        $pendingRefills = RefillRequest::pending()
            ->whereNotIn('family_id', $familiesWithDoctor)
            ->with(['family', 'requester', 'prescriptionItem.treatmentCourse'])
            ->latest()
            ->get();

        return view('admin.cabinet-requests.index', compact(
            'pendingRefills',
            'requests',
            'status',
            'scope',
            'familiesWithDoctor',
            'pendingNoDoctor',
            'pendingAll'
        ));
    }
}
