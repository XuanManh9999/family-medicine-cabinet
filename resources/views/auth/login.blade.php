@extends('layouts.auth')

@section('title', 'Đăng nhập')

@section('content')
<div class="mb-3 text-center pb-2 border-bottom">
    <h5 class="fw-bold mb-1 text-dark">Đăng nhập tài khoản</h5>
    <div class="text-muted" style="font-size: 12px;">Truy cập tủ thuốc & đơn điều trị ngoại trú gia đình</div>
</div>

<form method="POST" action="{{ route('login') }}">
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label small fw-semibold">Địa chỉ Email <span class="text-danger">*</span></label>
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                   id="email" name="email" value="{{ old('email') }}" 
                   placeholder="name@example.com" required autofocus>
        </div>
        @error('email')
            <div class="invalid-feedback d-block small">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="password" class="form-label small fw-semibold mb-0">Mật khẩu <span class="text-danger">*</span></label>
            <a href="{{ route('password.request') }}" class="text-decoration-none small fw-semibold" style="font-size: 12px; color: var(--app-main-blue);">
                Quên mật khẩu?
            </a>
        </div>
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                   id="password" name="password" 
                   placeholder="••••••••" required>
            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        @error('password')
            <div class="invalid-feedback d-block small">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label text-muted" for="remember" style="font-size: 12.5px;">
                Ghi nhớ đăng nhập
            </label>
        </div>
    </div>

    <button type="submit" class="btn btn-auth-primary mb-3">
        <i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập vào Tủ thuốc
    </button>

    <div class="text-center pt-2 border-top">
        <span class="text-muted" style="font-size: 12.5px;">Gia đình chưa có tài khoản?</span>
        <a href="{{ route('register') }}" class="fw-bold text-decoration-none ms-1" style="font-size: 12.5px; color: var(--app-main-blue);">
            Đăng ký mới
        </a>
    </div>
</form>

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
