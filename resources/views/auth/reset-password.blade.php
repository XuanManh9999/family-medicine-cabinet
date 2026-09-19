@extends('layouts.auth')

@section('title', 'Tạo mật khẩu mới')

@section('content')
<div class="mb-3 text-center pb-2 border-bottom">
    <h5 class="fw-bold mb-1 text-dark">TẠO MẬT KHẨU MỚI</h5>
    <div class="text-muted" style="font-size: 12px;">Đặt lại mật khẩu cho tài khoản: <strong class="text-primary">{{ $email }}</strong></div>
</div>

<form method="POST" action="{{ route('password.update') }}">
    @csrf

    <div class="mb-3">
        <label for="password" class="form-label small fw-semibold">Mật khẩu mới <span class="text-danger">*</span></label>
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                   id="password" name="password" 
                   placeholder="Tối thiểu 6 ký tự" required autofocus>
            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        @error('password')
            <div class="invalid-feedback d-block small">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="password_confirmation" class="form-label small fw-semibold">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-shield-check"></i></span>
            <input type="password" class="form-control" 
                   id="password_confirmation" name="password_confirmation" 
                   placeholder="Nhập lại mật khẩu mới" required>
        </div>
    </div>

    <button type="submit" class="btn btn-auth-primary mb-3">
        <i class="bi bi-check2-circle me-1"></i> Lưu mật khẩu mới
    </button>
</form>

<div class="text-center pt-2 border-top">
    <a href="{{ route('login') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i> Hủy và quay lại Đăng nhập
    </a>
</div>

@push('scripts')
<script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    if (togglePassword) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });
    }
</script>
@endpush
@endsection
