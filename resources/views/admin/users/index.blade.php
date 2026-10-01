@extends('admin.layouts.admin')

@section('title', 'Quản lý Tài khoản Người dùng Toàn sàn')

@section('content')
<!-- HEADER -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-people text-danger fs-4"></i>
            Quản trị Tài khoản Người dùng
        </h5>
        <div class="text-muted small">
            Quản lý vai trò (Super Admin, Chủ hộ, Thành viên), hỗ trợ đặt lại mật khẩu và kiểm soát quyền hạn.
        </div>
    </div>
</div>

<!-- SEARCH & FILTER BAR -->
<div class="admin-card mb-3">
    <div class="admin-card-body p-3">
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-center">
            <div class="col-md-7">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Tìm theo tên, email, số điện thoại..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Tất cả vai trò --</option>
                    <option value="system_admin" {{ $role === 'system_admin' ? 'selected' : '' }}>Quản trị viên Hệ thống (Super Admin)</option>
                    <option value="family_admin" {{ $role === 'family_admin' ? 'selected' : '' }}>Chủ hộ Gia đình</option>
                    <option value="member" {{ $role === 'member' ? 'selected' : '' }}>Thành viên thường</option>
                    <option value="doctor_caregiver" {{ $role === 'doctor_caregiver' ? 'selected' : '' }}>Bác sĩ / Người chăm sóc</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Lọc</button>
                @if ($search || $role)
                    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary" title="Xóa bộ lọc">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- USERS TABLE -->
<div class="admin-card">
    <div class="admin-card-header d-flex justify-content-between align-items-center">
        <div>
            <span class="text-dark"><i class="bi bi-list-ul text-primary me-2"></i>Danh sách Tài khoản</span>
            <span class="badge bg-secondary-subtle text-secondary ms-2">{{ $users->total() }} tài khoản</span>
        </div>
        <div class="text-muted small">
            Trang {{ $users->currentPage() }} / {{ max(1, $users->lastPage()) }}
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-admin mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">STT</th>
                    <th>Họ và tên</th>
                    <th>Email</th>
                    <th>Số điện thoại</th>
                    <th>Vai trò</th>
                    <th>Hộ Gia đình</th>
                    <th>Ngày đăng ký</th>
                    <th class="text-end" style="width: 160px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $idx => $u)
                    <tr>
                        <td>{{ $users->firstItem() + $idx }}</td>
                        <td>
                            <div class="fw-bold text-dark">{{ $u->name }}</div>
                            @if ($u->id === auth()->id())
                                <span class="badge bg-success" style="font-size: 10px;">Tài khoản của bạn</span>
                            @endif
                        </td>
                        <td>
                            <code>{{ $u->email }}</code>
                        </td>
                        <td class="small">{{ $u->phone ?: '—' }}</td>
                        <td>
                            @if ($u->isSystemAdmin())
                                <span class="badge bg-danger">Quản trị viên Hệ thống</span>
                            @elseif ($u->isFamilyAdmin())
                                <span class="badge bg-primary">Chủ hộ Gia đình</span>
                            @elseif ($u->isDoctorOrCaregiver())
                                <span class="badge bg-info">Bác sĩ / Chăm sóc</span>
                            @else
                                <span class="badge bg-secondary">Thành viên</span>
                            @endif
                        </td>
                        <td>
                            @if ($u->family)
                                <a href="{{ route('admin.families.show', $u->family) }}" class="text-decoration-none fw-semibold small">
                                    {{ $u->family->name }}
                                </a>
                                <div class="text-muted" style="font-size: 11px;">Mã: {{ $u->family->code }}</div>
                            @else
                                <span class="text-muted small">Chưa tham gia gia đình</span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            {{ $u->created_at ? $u->created_at->format('d/m/Y') : '—' }}
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" 
                                        onclick="openRoleModal({{ $u->id }}, '{{ addslashes($u->name) }}', '{{ $u->role }}')" 
                                        title="Đổi vai trò">
                                    <i class="bi bi-person-gear"></i> Vai trò
                                </button>
                                <button type="button" class="btn btn-outline-warning" 
                                        onclick="openResetPasswordModal({{ $u->id }}, '{{ addslashes($u->name) }}', '{{ $u->email }}')" 
                                        title="Đặt lại mật khẩu">
                                    <i class="bi bi-key"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">Không tìm thấy tài khoản người dùng nào.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($users->hasPages())
        <div class="card-footer bg-white border-top p-2 d-flex justify-content-between align-items-center">
            <div class="small text-muted">
                Hiển thị {{ $users->firstItem() }} - {{ $users->lastItem() }} / {{ $users->total() }} tài khoản
            </div>
            <div>
                {{ $users->links('pagination::bootstrap-5') }}
            </div>
        </div>
    @endif
</div>

<!-- MODAL ĐỔI VAI TRÒ -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form method="POST" id="roleForm" action="">
                @csrf
                @method('PATCH')
                <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title fw-bold">Phân quyền Vai trò Tài khoản</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Tài khoản:</label>
                        <div class="fw-bold fs-6 text-dark" id="modalUserName">User Name</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Chọn vai trò mới:</label>
                        <select name="role" id="modalUserRole" class="form-select form-select-sm" required>
                            <option value="member">Thành viên thường (Member)</option>
                            <option value="family_admin">Chủ hộ Gia đình (Family Admin)</option>
                            <option value="doctor_caregiver">Bác sĩ / Người chăm sóc (Doctor / Caregiver)</option>
                            <option value="system_admin">Quản trị viên Hệ thống (System Admin)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-sm btn-primary">Xác nhận cập nhật vai trò</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL ĐẶT LẠI MẬT KHẨU -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form method="POST" id="resetPasswordForm" action="">
                @csrf
                @method('PATCH')
                <div class="modal-header bg-warning text-dark py-2">
                    <h6 class="modal-title fw-bold">Đặt lại Mật khẩu Người dùng</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-2">
                        <div class="fw-bold text-dark" id="resetUserName">User Name</div>
                        <div class="text-muted small" id="resetUserEmail">email@example.com</div>
                    </div>
                    <hr class="my-2">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Mật khẩu mới <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control form-control-sm" placeholder="Tối thiểu 6 ký tự" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control form-control-sm" placeholder="Nhập lại mật khẩu mới" required>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-sm btn-warning">Xác nhận đổi mật khẩu</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function openRoleModal(userId, userName, currentRole) {
        document.getElementById('roleForm').action = '/admin/users/' + userId + '/role';
        document.getElementById('modalUserName').innerText = userName;
        document.getElementById('modalUserRole').value = currentRole;
        new bootstrap.Modal(document.getElementById('roleModal')).show();
    }

    function openResetPasswordModal(userId, userName, userEmail) {
        document.getElementById('resetPasswordForm').action = '/admin/users/' + userId + '/reset-password';
        document.getElementById('resetUserName').innerText = userName;
        document.getElementById('resetUserEmail').innerText = userEmail;
        new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
    }
</script>
@endpush
@endsection
