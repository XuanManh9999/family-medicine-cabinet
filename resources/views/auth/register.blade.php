@extends('layouts.auth')

@section('title', 'Đăng ký tài khoản')

@section('content')
<div class="mb-3 text-center pb-2 border-bottom">
    <h5 class="fw-bold mb-1 text-dark">ĐĂNG KÝ TÀI KHOẢN MỚI</h5>
    <div class="text-muted" style="font-size: 12px;">Khởi tạo tủ thuốc gia đình hoặc tham gia qua mã mời</div>
</div>

<form method="POST" action="{{ route('register') }}" id="registerForm">
    @csrf

    <!-- Choice between creating or joining family -->
    <div class="mb-3">
        <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 11px;">Mục đích đăng ký</label>
        <div class="row g-2">
            <div class="col-6">
                <input type="radio" class="btn-check" name="registration_type" id="type_create" 
                       value="create_family" {{ old('registration_type', 'create_family') === 'create_family' ? 'checked' : '' }}
                       onchange="toggleRegType()">
                <label class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center rounded-2 h-100 justify-content-center" for="type_create" style="font-size: 12px;">
                    <i class="bi bi-house-add fs-5 mb-1"></i>
                    <span class="fw-bold">Tạo gia đình mới</span>
                    <span class="text-muted" style="font-size: 10.5px;">(Quyền Chủ hộ)</span>
                </label>
            </div>
            <div class="col-6">
                <input type="radio" class="btn-check" name="registration_type" id="type_join" 
                       value="join_family" {{ old('registration_type') === 'join_family' ? 'checked' : '' }}
                       onchange="toggleRegType()">
                <label class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center rounded-2 h-100 justify-content-center" for="type_join" style="font-size: 12px;">
                    <i class="bi bi-person-plus fs-5 mb-1"></i>
                    <span class="fw-bold">Có mã gia đình</span>
                    <span class="text-muted" style="font-size: 10.5px;">(Tham gia theo mã)</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Group 1: New Family Fields -->
    <div id="createFamilyFields" class="p-2 mb-3 rounded border bg-light">
        <div class="small fw-bold text-primary mb-2" style="font-size: 12px;">
            <i class="bi bi-building-check me-1"></i>Thông tin Hộ gia đình mới
        </div>
        <div class="mb-2">
            <label for="family_name" class="form-label small fw-semibold">Tên hộ gia đình <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm @error('family_name') is-invalid @enderror" 
                   id="family_name" name="family_name" value="{{ old('family_name') }}" 
                   placeholder="VD: Gia đình Bác Nguyễn Văn An">
            @error('family_name')
                <div class="invalid-feedback small">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="family_address" class="form-label small fw-semibold">Địa chỉ / Khu vực</label>
            <input type="text" class="form-control form-control-sm @error('family_address') is-invalid @enderror" 
                   id="family_address" name="family_address" value="{{ old('family_address') }}" 
                   placeholder="VD: Hoàng Cầu, Đống Đa, Hà Nội">
        </div>
    </div>

    <!-- Group 2: Join Existing Family Code -->
    <div id="joinFamilyFields" class="p-2 mb-3 rounded border bg-light d-none">
        <div class="small fw-bold text-primary mb-2" style="font-size: 12px;">
            <i class="bi bi-key-fill me-1"></i>Nhập Mã mời hộ gia đình
        </div>
        <div>
            <label for="family_code" class="form-label small fw-semibold">Mã mời hộ gia đình <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm text-uppercase fw-bold text-primary @error('family_code') is-invalid @enderror" 
                   id="family_code" name="family_code" value="{{ old('family_code') }}" 
                   placeholder="VD: FAM-A1B2C3">
            @error('family_code')
                <div class="invalid-feedback small">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <!-- User Account Fields -->
    <div class="mb-2">
        <label for="name" class="form-label small fw-semibold">Họ và tên của bạn <span class="text-danger">*</span></label>
        <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" 
               id="name" name="name" value="{{ old('name') }}" 
               placeholder="Nguyễn Văn A" required>
        @error('name')
            <div class="invalid-feedback small">{{ $message }}</div>
        @enderror
    </div>

    <div class="row g-2 mb-2">
        <div class="col-7">
            <label for="email" class="form-label small fw-semibold">Email <span class="text-danger">*</span></label>
            <input type="email" class="form-control form-control-sm @error('email') is-invalid @enderror" 
                   id="email" name="email" value="{{ old('email') }}" 
                   placeholder="a@gmail.com" required>
            @error('email')
                <div class="invalid-feedback small">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-5">
            <label for="phone" class="form-label small fw-semibold">Số điện thoại</label>
            <input type="text" class="form-control form-control-sm @error('phone') is-invalid @enderror" 
                   id="phone" name="phone" value="{{ old('phone') }}" 
                   placeholder="0912...">
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6">
            <label for="password" class="form-label small fw-semibold">Mật khẩu <span class="text-danger">*</span></label>
            <input type="password" class="form-control form-control-sm @error('password') is-invalid @enderror" 
                   id="password" name="password" placeholder="Tối thiểu 6 ký tự" required>
            @error('password')
                <div class="invalid-feedback small">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-6">
            <label for="password_confirmation" class="form-label small fw-semibold">Nhập lại MK <span class="text-danger">*</span></label>
            <input type="password" class="form-control form-control-sm" 
                   id="password_confirmation" name="password_confirmation" placeholder="Xác nhận lại" required>
        </div>
    </div>

    <button type="submit" class="btn btn-auth-primary mb-3">
        <i class="bi bi-check2-circle me-1"></i> Hoàn tất Đăng ký
    </button>

    <div class="text-center pt-2 border-top">
        <span class="text-muted" style="font-size: 12.5px;">Đã có tài khoản từ trước?</span>
        <a href="{{ route('login') }}" class="fw-bold text-decoration-none ms-1" style="font-size: 12.5px; color: var(--app-main-blue);">
            Đăng nhập ngay
        </a>
    </div>
</form>

@push('scripts')
<script>
    function toggleRegType() {
        const isCreate = document.getElementById('type_create').checked;
        const createBox = document.getElementById('createFamilyFields');
        const joinBox = document.getElementById('joinFamilyFields');

        if (isCreate) {
            createBox.classList.remove('d-none');
            joinBox.classList.add('d-none');
            document.getElementById('family_name').required = true;
            document.getElementById('family_code').required = false;
        } else {
            createBox.classList.add('d-none');
            joinBox.classList.remove('d-none');
            document.getElementById('family_name').required = false;
            document.getElementById('family_code').required = true;
        }
    }

    document.addEventListener('DOMContentLoaded', toggleRegType);
</script>
@endpush
@endsection
