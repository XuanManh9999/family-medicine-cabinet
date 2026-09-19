@extends('layouts.auth')

@section('title', 'Quên mật khẩu')

@section('content')
<div class="mb-3 text-center pb-2 border-bottom">
    <h5 class="fw-bold mb-1 text-dark">QUÊN MẬT KHẨU</h5>
    <div class="text-muted" style="font-size: 12px;">Nhập email tài khoản để nhận mã OTP xác thực đặt lại mật khẩu</div>
</div>

<form method="POST" action="{{ route('password.email') }}">
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label small fw-semibold">Địa chỉ Email tài khoản <span class="text-danger">*</span></label>
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

    <button type="submit" class="btn btn-auth-primary mb-3">
        <i class="bi bi-send me-1"></i> Gửi mã OTP qua Email
    </button>

    <div class="text-center pt-2 border-top">
        <a href="{{ route('login') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i> Quay lại trang Đăng nhập
        </a>
    </div>
</form>
@endsection
