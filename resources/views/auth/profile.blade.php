@extends('layouts.app')

@section('title', 'Hồ sơ cá nhân & Hộ gia đình')

@section('content')
<div class="row g-3">
    <!-- Cột trái: Cập nhật thông tin tài khoản -->
    <div class="col-12 col-lg-5">
        <div class="content-card mb-3">
            <div class="content-card-header">
                <span><i class="bi bi-person-gear me-1 text-primary"></i>THÔNG TIN TÀI KHOẢN CỦA BẠN</span>
                <span class="badge {{ $user->isFamilyAdmin() ? 'bg-primary' : 'bg-secondary' }}">
                    {{ $user->isFamilyAdmin() ? 'Chủ hộ (Admin)' : 'Thành viên' }}
                </span>
            </div>
            <div class="content-card-body">
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" id="profileForm">
                    @csrf

                    <!-- Khu vực Ảnh đại diện tài khoản -->
                    <div class="p-3 mb-3 rounded border bg-light">
                        <label class="form-label small fw-bold text-secondary text-uppercase d-block mb-2" style="font-size: 11.5px; letter-spacing: 0.5px;">
                            <i class="bi bi-person-badge me-1 text-primary"></i>Ảnh đại diện tài khoản
                        </label>
                        <div class="d-flex align-items-center gap-3">
                            <div class="position-relative" style="width: 72px; height: 72px; flex-shrink: 0;">
                                @if ($user->avatar_url)
                                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" 
                                         id="avatarPreview" class="rounded-circle border border-2 border-primary-subtle shadow-sm w-100 h-100" 
                                         style="object-fit: cover;">
                                    <div id="avatarFallback" class="d-none rounded-circle bg-white border border-2 border-secondary-subtle d-flex align-items-center justify-content-center w-100 h-100 shadow-sm">
                                        <i class="bi bi-person-fill" style="font-size: 38px; color: #94a3b8;"></i>
                                    </div>
                                @else
                                    <div id="avatarFallback" class="rounded-circle bg-white border border-2 border-secondary-subtle d-flex align-items-center justify-content-center w-100 h-100 shadow-sm">
                                        <i class="bi bi-person-fill" style="font-size: 38px; color: #94a3b8;"></i>
                                    </div>
                                    <img src="" alt="{{ $user->name }}" id="avatarPreview" class="rounded-circle border border-2 border-primary-subtle shadow-sm w-100 h-100 d-none" 
                                         style="object-fit: cover;">
                                @endif
                                <label for="avatar" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" 
                                       style="width: 24px; height: 24px; cursor: pointer; border: 2px solid #fff;" title="Chọn ảnh mới">
                                    <i class="bi bi-camera-fill" style="font-size: 11px;"></i>
                                </label>
                            </div>

                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <label for="avatar" class="btn btn-sm btn-outline-primary fw-medium px-3" style="font-size: 12.5px; cursor: pointer;">
                                        <i class="bi bi-camera me-1"></i> Chọn ảnh mới...
                                    </label>
                                    @if ($user->avatar)
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="remove_avatar" id="remove_avatar" value="1">
                                            <label class="form-check-label text-danger small" for="remove_avatar" style="font-size: 12px; cursor: pointer;">
                                                <i class="bi bi-trash3 me-1"></i> Gỡ ảnh hiện tại
                                            </label>
                                        </div>
                                    @endif
                                </div>
                                <input type="file" class="d-none @error('avatar') is-invalid @enderror" 
                                       id="avatar" name="avatar" accept="image/jpeg,image/png,image/jpg,image/webp">
                                
                                <!-- Tự động lưu ảnh dạng Base64 -->
                                <input type="hidden" name="avatar_base64" id="avatarBase64">

                                <div class="text-muted" style="font-size: 11.5px;">
                                    <i class="bi bi-info-circle me-1"></i>Định dạng: JPG, PNG, WEBP (Tối đa 5MB)
                                </div>
                            </div>
                        </div>

                        @error('avatar')
                            <div class="text-danger small mt-2" style="font-size: 11.5px;">
                                <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" 
                               id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')
                            <div class="invalid-feedback small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold">Email đăng nhập</label>
                        <input type="email" class="form-control form-control-sm bg-light" id="email" value="{{ $user->email }}" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label small fw-semibold">Số điện thoại</label>
                        <input type="text" class="form-control form-control-sm @error('phone') is-invalid @enderror" 
                               id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="0912...">
                        @error('phone')
                            <div class="invalid-feedback small">{{ $message }}</div>
                        @enderror
                    </div>

                    <hr class="my-3">
                    <div class="fw-bold small text-muted mb-2 text-uppercase">Đổi mật khẩu (Nếu cần)</div>

                    <div class="mb-2">
                        <label for="current_password" class="form-label small">Mật khẩu hiện tại</label>
                        <input type="password" class="form-control form-control-sm @error('current_password') is-invalid @enderror" 
                               id="current_password" name="current_password" placeholder="Nhập mật khẩu hiện tại">
                        @error('current_password')
                            <div class="invalid-feedback small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="new_password" class="form-label small">Mật khẩu mới</label>
                            <input type="password" class="form-control form-control-sm @error('new_password') is-invalid @enderror" 
                                   id="new_password" name="new_password" placeholder="Tối thiểu 6 ký tự">
                            @error('new_password')
                                <div class="invalid-feedback small">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-6">
                            <label for="new_password_confirmation" class="form-label small">Nhập lại MK</label>
                            <input type="password" class="form-control form-control-sm" 
                                   id="new_password_confirmation" name="new_password_confirmation" placeholder="Xác nhận">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-erp-primary w-100">
                        <i class="bi bi-check-lg me-1"></i> Cập nhật thông tin & Ảnh
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Cột phải: Thông tin hộ gia đình & Tài khoản -->
    <div class="col-12 col-lg-7">
        <!-- Box Mã gia đình -->
        <div class="content-card mb-3">
            <div class="content-card-header">
                <span><i class="bi bi-house-door me-1 text-primary"></i>THÔNG TIN HỘ GIA ĐÌNH</span>
                <span class="badge bg-light text-dark border">ID #{{ $user->family->id }}</span>
            </div>
            <div class="content-card-body">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <h5 class="fw-bold text-primary mb-1">{{ $user->family->name }}</h5>
                        <div class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i>{{ $user->family->address ?? 'Chưa khai báo địa chỉ' }}</div>
                        <div class="text-muted small">Ngày đăng ký hệ thống: {{ $user->family->created_at->format('d/m/Y') }}</div>
                    </div>
                    <div class="col-md-5 text-md-end mt-2 mt-md-0">
                        <div class="p-2 border rounded bg-light d-inline-block text-center w-100">
                            <div class="text-muted" style="font-size: 11px;">MÃ MỜI GIA ĐÌNH</div>
                            <div class="fs-4 fw-bold text-primary font-monospace">{{ $user->family->code }}</div>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 mt-1" style="font-size: 11.5px;" onclick="copyFamilyCode('{{ $user->family->code }}')">
                                <i class="bi bi-copy me-1"></i> Sao chép mã
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bảng danh sách tài khoản -->
        <div class="content-card mb-3">
            <div class="content-card-header">
                <span><i class="bi bi-people me-1 text-primary"></i>TÀI KHOẢN TRUY CẬP HỘ GIA ĐÌNH ({{ $user->family->users->count() }})</span>
            </div>
            <div class="table-responsive">
                <table class="table table-erp table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Họ tên</th>
                            <th>Email đăng nhập</th>
                            <th>Vai trò</th>
                            <th>Số điện thoại</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($user->family->users as $u)
                            <tr>
                                <td class="fw-bold">
                                    <div class="d-flex align-items-center">
                                        @if ($u->avatar_url)
                                            <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}" class="rounded-circle me-2 border" style="width: 24px; height: 24px; object-fit: cover;">
                                        @else
                                            <span class="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center me-2 text-secondary" style="width: 24px; height: 24px;">
                                                <i class="bi bi-person-fill" style="font-size: 13px; color: #94a3b8;"></i>
                                            </span>
                                        @endif
                                        <span>{{ $u->name }}</span>
                                        @if ($u->id === $user->id)
                                            <span class="badge bg-light text-muted border ms-1" style="font-size: 10px;">Bạn</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $u->email }}</td>
                                <td>
                                    <span class="badge {{ $u->isFamilyAdmin() ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }}">
                                        {{ $u->isFamilyAdmin() ? 'Chủ hộ' : 'Thành viên' }}
                                    </span>
                                </td>
                                <td>{{ $u->phone ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const avatarInput = document.getElementById('avatar');
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarFallback = document.getElementById('avatarFallback');
    const avatarBase64 = document.getElementById('avatarBase64');

    if (avatarInput) {
        avatarInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                // Kiểm tra dung lượng tối đa 5MB
                if (file.size > 5 * 1024 * 1024) {
                    alert('Dung lượng tệp vượt quá 5MB. Vui lòng chọn ảnh nhỏ hơn.');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    const result = e.target.result;
                    if (avatarPreview) {
                        avatarPreview.src = result;
                        avatarPreview.classList.remove('d-none');
                    }
                    if (avatarFallback) {
                        avatarFallback.classList.add('d-none');
                    }
                    // Tự động chuyển đổi và gán Base64 để lưu vào database
                    if (avatarBase64) {
                        avatarBase64.value = result;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
</script>
@endpush
@endsection
