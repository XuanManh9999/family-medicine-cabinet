<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Quản lý Tủ thuốc Gia đình') - MedCabinet</title>
    
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
            --app-nav-active: #004d73;
            --app-bg: #f4f6f9;
            --app-border: #dee2e6;
            --font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--app-bg);
            color: #212529;
            font-size: 13.5px;
            min-height: 100vh;
        }

        /* Top Subbar */
        .top-subbar {
            background: #ffffff;
            border-bottom: 1px solid var(--app-border);
            height: 42px;
            font-size: 13px;
        }

        .brand-text {
            font-weight: 700;
            font-size: 16px;
            color: var(--app-main-blue);
            letter-spacing: -0.3px;
        }

        .family-chip {
            background-color: #e6f4fa;
            color: #006699;
            border: 1px solid #bce2f3;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .family-chip:hover {
            background-color: #d6eff9;
        }

        /* Main Navigation Bar (Phần mềm phong cách The Light / KiotViet) */
        .main-navbar {
            background: var(--app-main-blue);
            min-height: 44px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .main-nav-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }

        .main-nav-item {
            position: relative;
        }

        .main-nav-link {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #ffffff !important;
            text-decoration: none;
            padding: 10px 14px;
            font-size: 13.5px;
            font-weight: 500;
            transition: background 0.15s ease;
            white-space: nowrap;
        }

        .main-nav-link:hover {
            background-color: var(--app-dark-blue);
        }

        .main-nav-link.active {
            background-color: var(--app-nav-active);
            font-weight: 600;
        }

        .main-nav-link i {
            font-size: 15px;
            opacity: 0.9;
        }

        /* Business UI Components */
        .content-card {
            background: #ffffff;
            border: 1px solid var(--app-border);
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            margin-bottom: 1rem;
        }

        .content-card-header {
            background: #fafbfc;
            border-bottom: 1px solid var(--app-border);
            padding: 10px 16px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .content-card-body {
            padding: 16px;
        }

        /* ERP Data Tables */
        .table-erp {
            font-size: 13px;
            margin-bottom: 0;
        }

        .table-erp th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 600;
            border-bottom: 1px solid #cbd5e1;
            padding: 8px 12px;
            vertical-align: middle;
            white-space: nowrap;
        }

        .table-erp td {
            padding: 8px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-erp tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Filter Panel (Cột lọc bên trái chuẩn phần mềm) */
        .filter-panel {
            background: #ffffff;
            border: 1px solid var(--app-border);
            border-radius: 4px;
            padding: 14px;
            font-size: 13px;
        }

        .filter-panel .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }

        /* Standard Buttons */
        .btn-erp-primary {
            background-color: #007bb6;
            color: #ffffff;
            border: 1px solid #006a9d;
            font-size: 13px;
            font-weight: 500;
            border-radius: 3px;
            padding: 5px 12px;
        }

        .btn-erp-primary:hover {
            background-color: #006a9d;
            color: #ffffff;
        }

        .btn-erp-success {
            background-color: #2e7d32;
            color: #ffffff;
            border: 1px solid #236327;
            font-size: 13px;
            font-weight: 500;
            border-radius: 3px;
            padding: 5px 12px;
        }

        .btn-erp-success:hover {
            background-color: #236327;
            color: #ffffff;
        }

        /* KPI Widget Box */
        .kpi-box {
            background: #ffffff;
            border: 1px solid var(--app-border);
            border-radius: 4px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .kpi-number {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.2;
            color: #0f172a;
        }

        .kpi-title {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 2px;
        }
    </style>
    @stack('styles')
</head>
<body>
    @php
        $authUser = Auth::user();
        $authFamily = $authUser ? $authUser->family : null;
        $unreadAlertsCount = $authFamily ? $authFamily->smartAlerts()->unread()->count() : 0;
        $globalUnreadAlerts = $authFamily ? $authFamily->smartAlerts()->unread()->latest()->take(5)->get() : collect();
    @endphp

    @if ($authUser && $authUser->isSystemAdmin())
        <!-- SUPER ADMIN BANNER TRÊN ĐẦU GIAO DIỆN GIA ĐÌNH -->
        <div class="py-1 px-3 d-flex justify-content-between align-items-center text-white" style="background: linear-gradient(90deg, #b91c1c 0%, #0f172a 100%); font-size: 12px; z-index: 1050; position: relative;">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-white text-danger fw-bold text-uppercase" style="font-size: 10px;">
                    <i class="bi bi-shield-lock-fill me-1"></i>SUPER ADMIN
                </span>
                <span>Bạn đang truy cập giao diện Hộ gia đình với tư cách <strong>Quản trị viên Hệ thống</strong>. Bạn có toàn quyền tra cứu & xử lý kỹ thuật.</span>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-light py-0 px-2 fw-bold text-danger d-inline-flex align-items-center gap-1 shadow-sm" style="font-size: 11.5px; height: 24px;">
                <i class="bi bi-speedometer2"></i>
                <span>Đến Bảng Quản trị Hệ thống &rarr;</span>
            </a>
        </div>
    @endif

    <!-- TOP SUBBAR: Logo, Hộ gia đình, Hotline, User info -->
    <header class="top-subbar d-flex align-items-center">
        <div class="container-fluid px-3 d-flex align-items-center justify-content-between">
            <!-- Left: Logo & Family chip -->
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                    <i class="bi bi-capsule-pill fs-5 text-primary"></i>
                    <span class="brand-text">MedCabinet</span>
                    <span class="text-muted small d-none d-md-inline" style="font-size: 12px;">| Hệ thống quản lý tủ thuốc & điều trị gia đình</span>
                </a>

                @if ($authFamily)
                    @if ($authUser->isFamilyAdmin())
                        <div class="family-chip ms-2" onclick="copyFamilyCode('{{ $authFamily->code }}')" title="Chủ hộ: Bấm để sao chép mã mời thành viên vào gia đình">
                            <i class="bi bi-house-door-fill text-primary"></i>
                            <span>{{ $authFamily->name }}</span>
                            <span class="badge bg-primary text-white" style="font-size: 10px;">{{ $authFamily->code }}</span>
                            <i class="bi bi-copy text-primary" style="font-size: 11px;"></i>
                        </div>
                    @else
                        <div class="family-chip ms-2" style="cursor: default;" title="Hộ gia đình của bạn">
                            <i class="bi bi-house-door text-secondary"></i>
                            <span>{{ $authFamily->name }}</span>
                        </div>
                    @endif
                @endif
            </div>

            <!-- Right: Support, Alerts, User Profile -->
            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-lg-flex align-items-center gap-2 text-muted" style="font-size: 12px;">
                    <i class="bi bi-headset text-primary"></i>
                    <span>Tổng đài CSKH: <strong>1900 6868</strong></span>
                </div>

                <!-- Notifications dropdown -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-link text-dark p-0 position-relative text-decoration-none" data-bs-toggle="dropdown" title="Cảnh báo tủ thuốc">
                        <i class="bi bi-bell fs-6"></i>
                        @if ($unreadAlertsCount > 0)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 9px; padding: 2px 5px;">
                                {{ $unreadAlertsCount }}
                            </span>
                        @endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm border p-0" style="width: 320px; font-size: 12.5px;">
                        <div class="p-2 border-bottom bg-light d-flex justify-content-between align-items-center fw-bold">
                            <span>Cảnh báo an toàn tủ thuốc</span>
                            <span class="badge bg-danger">{{ $unreadAlertsCount }} chưa đọc</span>
                        </div>
                        <div style="max-height: 260px; overflow-y: auto;">
                            @forelse ($globalUnreadAlerts as $alert)
                                <div class="p-2 border-bottom">
                                    <div class="fw-semibold text-danger">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $alert->title }}
                                    </div>
                                    <div class="text-muted" style="font-size: 11.5px;">{{ $alert->message }}</div>
                                </div>
                            @empty
                                <div class="p-3 text-center text-muted">Không có cảnh báo nguy hiểm nào.</div>
                            @endforelse
                        </div>
                        <div class="p-2 text-center bg-light border-top">
                            <a href="{{ route('alerts.index') }}" class="text-decoration-none fw-semibold small">Xem tất cả cảnh báo</a>
                        </div>
                    </div>
                </div>

                @if ($authUser->isSystemAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-danger py-0 px-2 d-inline-flex align-items-center gap-1 shadow-sm fw-bold" style="font-size: 11.5px; height: 26px;">
                        <i class="bi bi-shield-lock-fill"></i>
                        <span>Quản trị Hệ thống</span>
                    </a>
                @endif

                <!-- User Dropdown -->
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center gap-1 text-decoration-none text-dark" data-bs-toggle="dropdown">
                        @if ($authUser->avatar_url)
                            <img src="{{ $authUser->avatar_url }}" alt="{{ $authUser->name }}" class="rounded-circle border me-1" style="width: 24px; height: 24px; object-fit: cover;">
                        @else
                            <i class="bi bi-person-circle fs-6 text-primary me-1"></i>
                        @endif
                        <span class="fw-semibold" style="font-size: 12.5px;">{{ $authUser->name }}</span>
                        @if ($authUser->isSystemAdmin())
                            <span class="badge bg-danger text-white" style="font-size: 10px;">Quản trị viên</span>
                        @elseif ($authUser->isFamilyAdmin())
                            <span class="badge bg-primary text-white" style="font-size: 10px;">Chủ hộ</span>
                        @elseif ($authUser->isDoctorOrCaregiver())
                            <span class="badge bg-success text-white" style="font-size: 10px;">Bác sĩ / Chăm sóc</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size: 10px;">Thành viên</span>
                        @endif
                        <i class="bi bi-chevron-down" style="font-size: 10px;"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-1" style="font-size: 12.5px;">
                        <li class="px-3 py-1 bg-light border-bottom mb-1">
                            <div class="fw-bold text-dark">{{ $authUser->name }}</div>
                            <div class="small text-muted">{{ $authUser->role_name }}</div>
                        </li>
                        @if ($authUser->isSystemAdmin())
                            <li>
                                <a class="dropdown-item py-1 fw-bold text-danger" href="{{ route('admin.dashboard') }}">
                                    <i class="bi bi-shield-lock-fill me-2"></i>Khu vực Quản trị Hệ thống
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                        @endif
                        <li>
                            <a class="dropdown-item py-1" href="{{ route('profile') }}">
                                <i class="bi bi-person me-2 text-muted"></i>Hồ sơ cá nhân & Gia đình
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item py-1 text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN HORIZONTAL NAVBAR (Chuẩn phong cách phần mềm The Light / KiotViet) -->
    <nav class="main-navbar">
        <div class="container-fluid px-3">
            <ul class="main-nav-list">
                <li class="main-nav-item">
                    <a href="{{ route('dashboard') }}" class="main-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2"></i>
                        <span>Tổng quan</span>
                    </a>
                </li>

                <li class="main-nav-item">
                    <a href="{{ route('cabinet.index') }}" class="main-nav-link {{ request()->routeIs('cabinet.*') ? 'active' : '' }}">
                        <i class="bi bi-prescription2"></i>
                        <span>Tủ thuốc</span>
                    </a>
                </li>

                <li class="main-nav-item">
                    <a href="{{ route('members.index') }}" class="main-nav-link {{ request()->routeIs('members.*') ? 'active' : '' }}">
                        <i class="bi bi-people"></i>
                        <span>Thành viên & Sức khỏe</span>
                    </a>
                </li>

                <li class="main-nav-item">
                    <a href="{{ route('treatment-courses.index') }}" class="main-nav-link {{ request()->routeIs('treatment-courses.*') ? 'active' : '' }}">
                        <i class="bi bi-journal-medical"></i>
                        <span>Đơn thuốc & Điều trị</span>
                    </a>
                </li>

                <li class="main-nav-item">
                    <a href="{{ route('schedules.index') }}" class="main-nav-link {{ request()->routeIs('schedules.*') ? 'active' : '' }}">
                        <i class="bi bi-alarm"></i>
                        <span>Lịch uống thuốc</span>
                    </a>
                </li>

                <li class="main-nav-item">
                    <a href="{{ route('expenses.index') }}" class="main-nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                        <i class="bi bi-cash-stack"></i>
                        <span>Chi phí & Viện phí</span>
                    </a>
                </li>

                <li class="main-nav-item">
                    <a href="{{ route('medicines.index') }}" class="main-nav-link {{ request()->routeIs('medicines.*') ? 'active' : '' }}">
                        <i class="bi bi-capsule-pill"></i>
                        <span>Tra cứu thuốc</span>
                    </a>
                </li>

                <li class="main-nav-item">
                    <a href="{{ route('ai-assistant.index') }}" class="main-nav-link {{ request()->routeIs('ai-assistant.*') ? 'active' : '' }}">
                        <i class="bi bi-robot"></i>
                        <span>Trợ lý Y tế AI</span>
                        <span class="badge bg-warning text-dark ms-1" style="font-size: 9px; padding: 1px 4px;">AI</span>
                    </a>
                </li>

                <li class="main-nav-item">
                    <a href="{{ route('alerts.index') }}" class="main-nav-link {{ request()->routeIs('alerts.*') ? 'active' : '' }}">
                        <i class="bi bi-shield-exclamation"></i>
                        <span>Cảnh báo an toàn</span>
                        @if ($unreadAlertsCount > 0)
                            <span class="badge bg-danger rounded-pill ms-1" style="font-size: 10px; padding: 2px 6px;">{{ $unreadAlertsCount }}</span>
                        @endif
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="container-fluid px-3 py-3">
        <!-- Flash Alerts -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show py-2 px-3 mb-3 d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-6"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('info'))
            <div class="alert alert-info alert-dismissible fade show py-2 px-3 mb-3 d-flex align-items-center" role="alert">
                <i class="bi bi-info-circle-fill me-2 fs-6"></i>
                <div>{{ session('info') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show py-2 px-3 mb-3 d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-6"></i>
                <div>{{ session('warning') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('danger'))
            <div class="alert alert-danger alert-dismissible fade show py-2 px-3 mb-3 d-flex align-items-center" role="alert">
                <i class="bi bi-x-circle-fill me-2 fs-6"></i>
                <div>{{ session('danger') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- THANH NHẬN BIẾT VAI TRÒ & QUYỀN HẠN TÀI KHOẢN -->
        @if ($authUser && $authUser->isMember())
            <div class="alert alert-light border py-2 px-3 mb-3 d-flex align-items-center justify-content-between shadow-sm flex-wrap gap-2" style="background-color: #f8fafc; font-size: 12.5px; border-left: 4px solid #64748b !important;">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary text-white py-1 px-2" style="font-size: 11px;">
                        <i class="bi bi-person-check-fill me-1"></i>Thành viên gia đình
                    </span>
                    <span class="text-secondary">
                        Bạn đang sử dụng hệ thống với vai trò <strong>Thành viên</strong>. Bạn có quyền tra cứu tủ thuốc, điểm danh cữ uống thuốc và cập nhật hồ sơ cá nhân.
                    </span>
                </div>
                <span class="text-muted small" style="font-size: 11.5px;">
                    <i class="bi bi-shield-check text-primary me-1"></i>Quản trị kho & sổ hộ do <strong>Chủ hộ</strong> phụ trách
                </span>
            </div>
        @elseif ($authUser && $authUser->isDoctorOrCaregiver())
            <div class="alert alert-success bg-success bg-opacity-10 border-success border-opacity-25 py-2 px-3 mb-3 d-flex align-items-center justify-content-between shadow-sm flex-wrap gap-2" style="font-size: 12.5px; border-left: 4px solid #16a34a !important;">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success text-white py-1 px-2" style="font-size: 11px;">
                        <i class="bi bi-heart-pulse-fill me-1"></i>Bác sĩ / Chăm sóc y tế
                    </span>
                    <span class="text-success-emphasis">
                        Bạn có thẩm quyền chuyên môn: <strong>Kê đơn đợt điều trị</strong>, <strong>thiết lập lịch uống thuốc</strong> và theo dõi y tế của các thành viên.
                    </span>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function copyFamilyCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                alert('Đã sao chép mã hộ gia đình: ' + code + '\nGửi mã này cho người thân để tham gia cùng quản lý tủ thuốc!');
            }).catch(err => {
                prompt('Mã hộ gia đình của bạn là:', code);
            });
        }
    </script>
    @stack('scripts')
</body>
</html>
