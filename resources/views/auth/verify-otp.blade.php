@extends('layouts.auth')

@section('title', 'Xác thực mã OTP')

@section('content')
<div class="mb-3 text-center pb-2 border-bottom">
    <h5 class="fw-bold mb-1 text-dark">XÁC THỰC MÃ OTP</h5>
    <div class="text-muted" style="font-size: 12px;">Mã 6 chữ số đã được gửi đến: <strong class="text-primary">{{ $email }}</strong></div>
</div>

@if (!empty($demoOtp))
    <div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center justify-content-between" style="font-size: 12px;">
        <div>
            <i class="bi bi-info-circle-fill me-1"></i>
            <span>Mã OTP của bạn: <strong class="font-monospace fs-6">{{ $demoOtp }}</strong></span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px;" onclick="document.getElementById('otp').value='{{ $demoOtp }}'">
            Điền nhanh
        </button>
    </div>
@endif

<form method="POST" action="{{ route('password.otp.verify') }}">
    @csrf

    <div class="mb-3">
        <label for="otp" class="form-label small fw-semibold text-center d-block">Nhập mã OTP 6 chữ số <span class="text-danger">*</span></label>
        <input type="text" class="form-control text-center font-monospace fw-bold @error('otp') is-invalid @enderror" 
               id="otp" name="otp" maxlength="6" 
               placeholder="______" 
               style="font-size: 24px; letter-spacing: 6px;" required autofocus>
        @error('otp')
            <div class="invalid-feedback d-block text-center small">{{ $message }}</div>
        @enderror
        <div class="form-text text-center small text-muted mt-1">
            Mã OTP có hiệu lực trong 15 phút.
        </div>
    </div>

    <button type="submit" class="btn btn-auth-primary mb-3">
        <i class="bi bi-shield-check me-1"></i> Xác nhận mã OTP
    </button>
</form>

<div class="d-flex justify-content-between align-items-center pt-2 border-top">
    <form method="POST" action="{{ route('password.email') }}" class="d-inline">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        <button type="submit" class="btn btn-link text-decoration-none p-0 text-primary small" style="font-size: 12px;">
            <i class="bi bi-arrow-clockwise me-1"></i> Gửi lại mã OTP
        </button>
    </form>

    <a href="{{ route('password.request') }}" class="text-muted text-decoration-none small" style="font-size: 12px;">
        Đổi email khác
    </a>
</div>
@endsection
