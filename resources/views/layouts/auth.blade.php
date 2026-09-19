<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Đăng nhập') - MedCabinet</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --app-main-blue: #007bb6;
            --app-dark-blue: #005f8d;
            --app-bg: #edf3f8;
            --app-border: #cbd7e2;
            --font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--app-bg);
            background-image: radial-gradient(#d5e3ef 1px, transparent 1px);
            background-size: 20px 20px;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13.5px;
        }

        .auth-container {
            width: 100%;
            max-width: 440px;
            margin: 20px auto;
        }

        .auth-box {
            background: #ffffff;
            border: 1px solid var(--app-border);
            border-top: 4px solid var(--app-main-blue);
            border-radius: 6px;
            box-shadow: 0 4px 16px rgba(0, 102, 153, 0.08);
            padding: 30px 28px;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 22px;
        }

        .brand-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--app-main-blue);
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .brand-subtitle {
            font-size: 12px;
            color: #64748b;
            margin-top: 3px;
        }

        .btn-auth-primary {
            background-color: var(--app-main-blue);
            border: 1px solid var(--app-dark-blue);
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            border-radius: 4px;
            padding: 9px 16px;
            width: 100%;
            transition: background-color 0.15s ease;
        }

        .btn-auth-primary:hover {
            background-color: var(--app-dark-blue);
            color: #ffffff;
        }

        .form-control, .form-select {
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            font-size: 13.5px;
            padding: 7px 11px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--app-main-blue);
            box-shadow: 0 0 0 3px rgba(0, 123, 182, 0.15);
        }

        /* Fix chữ hiển thị rõ ràng khi chọn nút */
        .btn-check:checked + .btn-outline-primary {
            background-color: var(--app-main-blue) !important;
            border-color: var(--app-dark-blue) !important;
            color: #ffffff !important;
        }
        .btn-check:checked + .btn-outline-primary * {
            color: #ffffff !important;
        }
        .btn-check:checked + .btn-outline-primary .text-muted,
        .btn-check:checked + .btn-outline-primary small {
            color: rgba(255, 255, 255, 0.9) !important;
        }
        .btn-check:not(:checked) + .btn-outline-primary {
            background-color: #ffffff;
            border-color: #cbd5e1;
            color: #334155;
        }
        .btn-check:not(:checked) + .btn-outline-primary:hover {
            background-color: #f1f5f9;
            border-color: var(--app-main-blue);
            color: var(--app-main-blue);
        }

        .demo-panel {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 4px;
            padding: 10px 12px;
            margin-bottom: 18px;
        }

        .btn-demo {
            font-size: 12px;
            font-weight: 600;
            border-radius: 3px;
            padding: 4px 10px;
            color: var(--app-main-blue);
            border: 1px solid #bce2f3;
            background: #eaf5fb;
            transition: all 0.15s ease;
        }

        .btn-demo:hover {
            background: #d4ecf8;
            border-color: var(--app-main-blue);
            color: var(--app-dark-blue);
        }

        .auth-footer {
            text-align: center;
            margin-top: 18px;
            font-size: 12px;
            color: #64748b;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="container px-3">
        <div class="auth-container">
            <!-- Brand Header đồng bộ với Dashboard -->
            <div class="brand-header">
                <div class="brand-title">
                    <i class="bi bi-capsule-pill"></i>
                    <span>MedCabinet</span>
                </div>
                <div class="brand-subtitle">Hệ thống Quản lý Tủ thuốc & Điều trị Ngoại trú Hộ gia đình</div>
            </div>

            <!-- Flash Alerts -->
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show py-2 px-3 mb-3 d-flex align-items-center" style="font-size: 12.5px;" role="alert">
                    <i class="bi bi-check-circle-fill me-2 fs-6"></i>
                    <div>{{ session('success') }}</div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('info'))
                <div class="alert alert-info alert-dismissible fade show py-2 px-3 mb-3 d-flex align-items-center" style="font-size: 12.5px;" role="alert">
                    <i class="bi bi-info-circle-fill me-2 fs-6"></i>
                    <div>{{ session('info') }}</div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('warning'))
                <div class="alert alert-warning alert-dismissible fade show py-2 px-3 mb-3 d-flex align-items-center" style="font-size: 12.5px;" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-6"></i>
                    <div>{{ session('warning') }}</div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show py-2 px-3 mb-3" style="font-size: 12.5px;" role="alert">
                    <div class="fw-bold mb-1"><i class="bi bi-exclamation-circle-fill me-1"></i>Lỗi thao tác:</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Auth Box Card -->
            <div class="auth-box">
                @yield('content')
            </div>

            <div class="auth-footer">
                <div>© {{ date('Y') }} MedCabinet System. Toàn quyền bảo lưu.</div>
                <div class="text-muted mt-1" style="font-size: 11px;">Nền tảng số hóa tủ thuốc & điều trị ngoại trú cho gia đình</div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
