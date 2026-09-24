<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FamilyMemberController extends Controller
{
    /**
     * Danh sách tất cả thành viên trong gia đình kèm chỉ số sức khỏe
     */
    public function index(Request $request)
    {
        $family = $this->ensureFamily($request);

        $query = $family->members()
            ->with(['user'])
            ->withCount([
                'treatmentCourses',
                'medicationSchedules' => fn ($q) => $q->where('is_active', true),
                'expenses',
            ]);

        // Tìm kiếm theo tên / bệnh nền / dị ứng
        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('allergies', 'like', "%{$search}%")
                  ->orWhere('underlying_diseases', 'like', "%{$search}%")
                  ->orWhere('blood_type', 'like', "%{$search}%");
            });
        }

        // Lọc theo mối quan hệ
        if ($rel = $request->query('relationship')) {
            $query->where('relationship', $rel);
        }

        // Lọc theo tình trạng y tế
        $healthFilter = $request->query('health', 'all');
        if ($healthFilter === 'allergies') {
            $query->whereNotNull('allergies')->where('allergies', '!=', '');
        } elseif ($healthFilter === 'diseases') {
            $query->whereNotNull('underlying_diseases')->where('underlying_diseases', '!=', '');
        } elseif ($healthFilter === 'elderly') {
            $query->whereDate('dob', '<=', today()->subYears(60));
        } elseif ($healthFilter === 'children') {
            $query->whereDate('dob', '>', today()->subYears(16));
        }

        // Sắp xếp
        $sort = $request->query('sort', 'default');
        match ($sort) {
            'age_desc' => $query->orderBy('dob', 'asc'), // sinh sớm = lớn tuổi hơn
            'age_asc' => $query->orderByDesc('dob'),
            'name_asc' => $query->orderBy('name', 'asc'),
            default => $query->orderBy('created_at', 'asc'),
        };

        $members = $query->get();

        // Thống kê y tế nhanh toàn gia đình
        $allMembers = $family->members;
        $stats = [
            'total' => $allMembers->count(),
            'elderly' => $allMembers->filter(fn ($m) => $m->is_elderly)->count(),
            'children' => $allMembers->filter(fn ($m) => $m->is_child)->count(),
            'with_allergies' => $allMembers->filter(fn ($m) => $m->has_allergies)->count(),
            'with_diseases' => $allMembers->filter(fn ($m) => $m->has_underlying_diseases)->count(),
        ];

        return view('members.index', compact('members', 'stats', 'healthFilter', 'sort'));
    }

    /**
     * Form thêm hồ sơ thành viên mới
     */
    public function create(Request $request)
    {
        $family = $this->ensureAdmin($request);

        // Danh sách tài khoản người dùng trong nhà chưa liên kết hồ sơ
        $availableUsers = $family->users()->doesntHave('familyMember')->get();

        return view('members.form', [
            'member' => new FamilyMember(),
            'editing' => false,
            'availableUsers' => $availableUsers,
        ]);
    }

    /**
     * Lưu hồ sơ thành viên mới
     */
    public function store(Request $request)
    {
        $family = $this->ensureAdmin($request);

        $data = $this->validatedData($request);
        $data['family_id'] = $family->id;

        if ($request->hasFile('avatar_file')) {
            $path = $request->file('avatar_file')->store('members/avatars', 'public');
            $data['avatar'] = $path;
        }

        $member = FamilyMember::create($data);

        return redirect()->route('members.show', $member)->with('success', "Đã tạo hồ sơ sức khỏe thành công cho: {$member->name}!");
    }

    /**
     * Chi tiết Hồ sơ Bệnh án điện tử cá nhân (Electronic Health Record)
     */
    public function show(Request $request, FamilyMember $member)
    {
        $this->authorizeMember($request, $member);

        // Lấy đợt điều trị gần đây
        $treatmentCourses = $member->treatmentCourses()
            ->with(['prescriptionItems.medicine'])
            ->latest()
            ->take(5)
            ->get();

        // Lịch uống thuốc đang kích hoạt
        $activeSchedules = $member->medicationSchedules()
            ->where('is_active', true)
            ->with(['prescriptionItem.medicine'])
            ->get();

        // Tổng chi phí y tế khám chữa bệnh cho thành viên này
        $totalExpense = $member->expenses()->sum('amount');
        $recentExpenses = $member->expenses()->latest()->take(5)->get();

        return view('members.show', compact('member', 'treatmentCourses', 'activeSchedules', 'totalExpense', 'recentExpenses'));
    }

    /**
     * Form chỉnh sửa hồ sơ sức khỏe
     */
    public function edit(Request $request, FamilyMember $member)
    {
        $this->authorizeMember($request, $member);

        $family = $request->user()->family;
        $availableUsers = $family->users()
            ->where(function ($q) use ($member) {
                $q->doesntHave('familyMember')
                  ->orWhere('id', $member->user_id);
            })
            ->get();

        return view('members.form', [
            'member' => $member,
            'editing' => true,
            'availableUsers' => $availableUsers,
        ]);
    }

    /**
     * Cập nhật thông tin hồ sơ
     */
    public function update(Request $request, FamilyMember $member)
    {
        $this->authorizeMember($request, $member);

        $data = $this->validatedData($request, $member->id);

        if ($request->hasFile('avatar_file')) {
            if ($member->avatar && Storage::disk('public')->exists($member->avatar)) {
                Storage::disk('public')->delete($member->avatar);
            }
            $path = $request->file('avatar_file')->store('members/avatars', 'public');
            $data['avatar'] = $path;
        }

        $member->update($data);

        return redirect()->route('members.show', $member)->with('success', 'Đã cập nhật hồ sơ sức khỏe thành công.');
    }

    /**
     * Xóa hồ sơ thành viên
     */
    public function destroy(Request $request, FamilyMember $member)
    {
        $family = $this->ensureAdmin($request);
        abort_unless($member->family_id === $family->id, 404);

        // Bảo vệ: không được xóa chủ hộ nếu là người tạo gia đình
        if ($member->user_id && $member->user_id === $family->created_by) {
            return back()->with('danger', 'Không thể xóa hồ sơ của Chủ hộ sáng lập gia đình.');
        }

        $name = $member->name;
        if ($member->avatar && Storage::disk('public')->exists($member->avatar)) {
            Storage::disk('public')->delete($member->avatar);
        }

        $member->delete();

        return redirect()->route('members.index')->with('success', "Đã xóa hồ sơ của thành viên '{$name}' khỏi gia đình.");
    }

    private function validatedData(Request $request, ?int $memberId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'in:ong_ba,bo_me,vo_chong,con_cai,ban_than,khac'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'string', 'in:nam,nu,khac'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'allergies' => ['nullable', 'string', 'max:3000'],
            'underlying_diseases' => ['nullable', 'string', 'max:3000'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'avatar_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:3072'],
        ], [
            'name.required' => 'Họ và tên thành viên là bắt buộc.',
            'relationship.required' => 'Vui lòng chọn mối quan hệ trong gia đình.',
            'dob.before_or_equal' => 'Ngày sinh không thể ở tương lai.',
            'avatar_file.image' => 'Ảnh đại diện phải là định dạng hình ảnh hợp lệ (JPG, PNG, WEBP).',
            'avatar_file.max' => 'Dung lượng ảnh đại diện không quá 3MB.',
        ]);
    }

    private function ensureFamily(Request $request): Family
    {
        $family = $request->user()->family;
        abort_unless($family, 403, 'Tài khoản chưa thuộc hộ gia đình.');
        return $family;
    }

    private function ensureAdmin(Request $request): Family
    {
        $family = $this->ensureFamily($request);
        abort_unless($request->user()->isFamilyAdmin(), 403, 'Chỉ Chủ hộ mới có quyền thực hiện thao tác này.');
        return $family;
    }

    private function authorizeMember(Request $request, FamilyMember $member): void
    {
        $family = $this->ensureFamily($request);
        abort_unless($member->family_id === $family->id, 404, 'Không tìm thấy hồ sơ thành viên trong gia đình của bạn.');
    }
}
