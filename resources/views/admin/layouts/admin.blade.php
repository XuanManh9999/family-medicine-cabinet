<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Quản trị Hệ thống') - MedCabinet Admin</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --admin-dark: #0f172a;
            --admin-nav: #1e293b;
            --admin-accent: #0284c7;
            --admin-bg: #f8fafc;
            --admin-border: #e2e8f0;
            --font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--admin-bg);
            color: #1e293b;
            font-size: 13.5px;
            min-height: 100vh;
        }

        /* Top Header */
        .admin-topbar {
            background: #ffffff;
            border-bottom: 1px solid var(--admin-border);
            height: 48px;
        }

        .admin-brand {
            font-weight: 800;
            font-size: 16px;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        /* Admin Navbar */
        .admin-navbar {
            background: var(--admin-dark);
            min-height: 44px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .admin-nav-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }

        .admin-nav-link {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #94a3b8 !important;
            text-decoration: none;
            padding: 10px 16px;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .admin-nav-link:hover {
            color: #ffffff !important;
            background-color: rgba(255,255,255,0.06);
        }

        .admin-nav-link.active {
            color: #38bdf8 !important;
            background-color: rgba(56,189,248,0.12);
            font-weight: 600;
            border-bottom: 2px solid #38bdf8;
        }

        /* Admin Cards */
        .admin-card {
            background: #ffffff;
            border: 1px solid var(--admin-border);
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            margin-bottom: 1rem;
        }

        .admin-card-header {
            background: #ffffff;
            border-bottom: 1px solid var(--admin-border);
            padding: 12px 18px;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .admin-card-body {
            padding: 18px;
        }

        /* KPI Widget */
        .admin-kpi-card {
            background: #ffffff;
            border: 1px solid var(--admin-border);
            border-radius: 6px;
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            transition: transform 0.15s ease;
        }

        .admin-kpi-card:hover {
            transform: translateY(-2px);
        }

        .admin-kpi-title {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #64748b;
        }

        .admin-kpi-value {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
            margin-top: 4px;
        }

        /* Table ERP */
        .table-admin th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 600;
            border-bottom: 1px solid #cbd5e1;
            padding: 10px 14px;
            font-size: 12.5px;
            white-space: nowrap;
        }

        .table-admin td {
            padding: 10px 14px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .table-admin tbody tr:hover {
            background-color: #f8fafc;
        }
    </style>
</head>
<body>
    <!-- TOP BAR -->
    <header class="admin-topbar d-flex align-items-center">
        <div class="container-fluid px-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('admin.dashboard') }}" class="admin-brand">
                    <i class="bi bi-shield-lock-fill text-danger fs-5"></i>
                    <span>MedCabinet <span class="badge bg-danger text-white fs-6 py-0 px-2 fw-bold" style="font-size: 11px;">SYSTEM ADMIN</span></span>
                </a>
                <span class="badge bg-dark-subtle text-dark small d-none d-md-inline" style="font-size: 11px;">Hệ thống Quản trị Trung tâm</span>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 shadow-sm" style="font-size: 12px;">
                    <i class="bi bi-house-door"></i>
                    <span>Về Giao diện Gia đình</span>
                </a>

                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center gap-2 text-decoration-none text-dark" data-bs-toggle="dropdown">
                        <span class="badge bg-danger rounded-circle p-2 text-white"><i class="bi bi-person-fill"></i></span>
                        <div class="d-none d-sm-block text-start">
                            <div class="fw-bold" style="font-size: 12.5px;">{{ auth()->user()->name }}</div>
                            <div class="text-danger" style="font-size: 10px; font-weight: 600;">Quản trị viên Hệ thống</div>
                        </div>
                        <i class="bi bi-chevron-down text-muted" style="font-size: 10px;"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-1" style="font-size: 12.5px;">
                        <li>
                            <a class="dropdown-item py-1" href="{{ route('profile') }}">
                                <i class="bi bi-person me-2 text-muted"></i>Hồ sơ cá nhân
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

    <!-- ADMIN NAVBAR -->
    <nav class="admin-navbar">
        <div class="container-fluid px-3">
            <ul class="admin-nav-list">
                <li>
                    <a href="{{ route('admin.dashboard') }}" class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2"></i>
                        <span>Bảng Tổng quan</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.medicines.index') }}" class="admin-nav-link {{ request()->routeIs('admin.medicines.*') ? 'active' : '' }}">
                        <i class="bi bi-capsule"></i>
                        <span>Quản trị Dược thư (Master Data)</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.categories.index') }}" class="admin-nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                        <i class="bi bi-tags"></i>
                        <span>Nhóm Dược lý</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.families.index') }}" class="admin-nav-link {{ request()->routeIs('admin.families.*') ? 'active' : '' }}">
                        <i class="bi bi-houses"></i>
                        <span>Hộ Gia đình Toàn sàn</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.users.index') }}" class="admin-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                        <i class="bi bi-people"></i>
                        <span>Tài khoản Người dùng</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- MAIN BODY -->
    <main class="container-fluid px-3 py-3">
        <!-- Flash messages -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show py-2 px-3 mb-3 d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-6"></i>
                <div>{{ session('success') }}</div>
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

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show py-2 px-3 mb-3" role="alert">
                <div class="fw-bold mb-1"><i class="bi bi-x-octagon-fill me-2"></i>Đã có lỗi xảy ra:</div>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
