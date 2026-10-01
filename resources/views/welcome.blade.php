<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedCabinet - Nền tảng Quản lý Tủ thuốc & Điều trị Ngoại trú Gia đình</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --app-main-blue: #007bb6;
            --app-dark-blue: #005f8d;
            --app-accent: #10b981;
            --font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            font-family: var(--font-family);
            color: #1e293b;
            background-color: #f8fafc;
        }

        .hero-section {
            background: linear-gradient(135deg, #005f8d 0%, #007bb6 50%, #0284c7 100%);
            color: #ffffff;
            padding: 80px 0 60px;
            position: relative;
            overflow: hidden;
        }

        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: 0;
            right: 0;
            height: 100px;
            background: #f8fafc;
            transform: skewY(-2deg);
        }

        .feature-card {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            transition: all 0.2s ease;
            height: 100%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 123, 182, 0.1);
            border-color: #bce2f3;
        }

        .feature-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 16px;
        }

        .btn-hero-primary {
            background-color: #10b981;
            color: #ffffff;
            font-weight: 700;
            padding: 12px 28px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.15s ease;
            border: none;
        }

        .btn-hero-primary:hover {
            background-color: #059669;
            color: #ffffff;
        }

        .btn-hero-outline {
            background-color: rgba(255,255,255,0.12);
            color: #ffffff;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255,255,255,0.3);
            transition: all 0.15s ease;
        }

        .btn-hero-outline:hover {
            background-color: #ffffff;
            color: var(--app-main-blue);
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top py-2">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-primary" href="{{ url('/') }}">
                <i class="bi bi-capsule-pill fs-3"></i>
                <span class="fs-4 fw-extrabold text-dark" style="letter-spacing: -0.5px;">MedCabinet</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-primary px-3 fw-bold">
                        <i class="bi bi-speedometer2 me-1"></i>Vào Tủ thuốc
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-sm btn-outline-primary px-3 fw-semibold">
                        Đăng nhập
                    </a>
                    <a href="{{ route('register') }}" class="btn btn-sm btn-primary px-3 fw-semibold">
                        Đăng ký Gia đình
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero-section text-center text-lg-start">
        <div class="container pb-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <span class="badge bg-white bg-opacity-25 text-white px-3 py-1.5 rounded-pill mb-3 fw-semibold" style="font-size: 13px;">
                        <i class="bi bi-shield-check me-1"></i> Nền tảng Y tế Số cho Gia đình Việt
                    </span>
                    <h1 class="display-5 fw-bold mb-3" style="line-height: 1.25;">
                        Quản lý Tủ thuốc Thông minh & Điều trị Ngoại trú Hộ Gia đình
                    </h1>
                    <p class="lead mb-4 text-white text-opacity-90" style="font-size: 16px; line-height: 1.6;">
                        Chấm dứt nguy cơ ngộ độc do thuốc quá hạn, quên cữ uống thuốc và lãng phí tiền bạc. 
                        Số hóa toàn diện tủ thuốc trong nhà, lập lịch uống tự động và cảnh báo tương tác thuốc thông minh với trợ lý AI.
                    </p>
                    <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                        <a href="{{ route('login') }}" class="btn-hero-primary shadow">
                            <i class="bi bi-box-arrow-in-right"></i> Bắt đầu sử dụng ngay
                        </a>
                        <a href="{{ route('register') }}" class="btn-hero-outline">
                            <i class="bi bi-house-add"></i> Tạo Hộ Gia đình mới
                        </a>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="p-4 bg-white rounded-3 shadow text-dark border">
                        <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                            <i class="bi bi-shield-lock-fill text-primary fs-5"></i>
                            <strong class="text-dark">Tài khoản trải nghiệm mẫu</strong>
                            <span class="badge bg-success ms-auto">Sẵn sàng</span>
                        </div>
                        <div class="small text-muted mb-3">
                            Hệ thống đã chuẩn bị sẵn cơ sở dữ liệu mẫu thực tế tại Việt Nam để trải nghiệm ngay:
                        </div>
                        <div class="p-2 border rounded bg-light mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>Chủ hộ (Admin):</strong>
                                <code>an.nguyen@gmail.com</code>
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-muted mt-1" style="font-size: 11px;">
                                <span>Mật khẩu: <code>password</code></span>
                                <span class="badge bg-primary">Full Quyền</span>
                            </div>
                        </div>
                        <div class="p-2 border rounded bg-light mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>Thành viên:</strong>
                                <code>hoa.tran@gmail.com</code>
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-muted mt-1" style="font-size: 11px;">
                                <span>Mật khẩu: <code>password</code></span>
                                <span class="badge bg-secondary">Điểm danh</span>
                            </div>
                        </div>
                        <div class="p-2 border rounded bg-light mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>Quản trị Trung tâm:</strong>
                                <code>admin@medcabinet.vn</code>
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-muted mt-1" style="font-size: 11px;">
                                <span>Mật khẩu: <code>password</code></span>
                                <span class="badge bg-danger">Super Admin</span>
                            </div>
                        </div>
                        <a href="{{ route('login') }}" class="btn btn-primary w-100 fw-bold">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập ngay
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- KEY FEATURES SECTION -->
    <section class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-primary-subtle text-primary px-3 py-1 rounded-pill fw-semibold">Tính năng Vượt trội</span>
                <h2 class="fw-bold mt-2 text-dark">Giải pháp Toàn diện cho Sức khỏe Gia đình</h2>
                <p class="text-muted">Được thiết kế tối ưu, khoa học và trực quan cho mọi thành viên trong nhà.</p>
            </div>

            <div class="row g-4">
                <!-- Feature 1 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-box bg-primary-subtle text-primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Quản lý Tủ thuốc Thông minh</h5>
                        <p class="text-muted small mb-0">
                            Số hóa từng vỉ thuốc, chai siro theo số lô, vị trí ngăn tủ. Tự động đếm ngược hạn sử dụng (EXP) và cảnh báo thuốc cận hạn trước 30 ngày.
                        </p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-box bg-success-subtle text-success">
                            <i class="bi bi-calendar2-check"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Lịch Uống & Điểm danh Tự động</h5>
                        <p class="text-muted small mb-0">
                            Phân chia 4 cữ uống (Sáng, Trưa, Chiều, Tối), trước hoặc sau ăn. Điểm danh 1 chạm và <strong>tự động trừ tồn kho</strong> trong tủ thuốc.
                        </p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-box bg-danger-subtle text-danger">
                            <i class="bi bi-robot"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Trí tuệ Nhân tạo AI Y tế</h5>
                        <p class="text-muted small mb-0">
                            Tích hợp Google Gemini: Vision OCR quét ảnh chụp đơn thuốc của bác sĩ, phát hiện trùng hoạt chất Paracetamol và cảnh báo dị ứng thuốc.
                        </p>
                    </div>
                </div>

                <!-- Feature 4 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-box bg-info-subtle text-info">
                            <i class="bi bi-journal-medical"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Đơn thuốc & Điều trị Ngoại trú</h5>
                        <p class="text-muted small mb-0">
                            Lưu trữ hồ sơ đợt bệnh, chẩn đoán, bác sĩ/bệnh viện điều trị và lưu ảnh gốc toa thuốc để tiện theo dõi tái khám.
                        </p>
                    </div>
                </div>

                <!-- Feature 5 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-box bg-warning-subtle text-warning-emphasis">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Thống kê Chi phí Y tế</h5>
                        <p class="text-muted small mb-0">
                            Ghi nhận tiền mua thuốc, viện phí và ảnh hóa đơn. Trực quan hóa chi tiêu theo 12 tháng qua biểu đồ Chart.js, xuất file Excel CSV.
                        </p>
                    </div>
                </div>

                <!-- Feature 6 -->
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon-box bg-dark-subtle text-dark">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Quản trị Hệ thống 2 Tầng</h5>
                        <p class="text-muted small mb-0">
                            Phân quyền rõ ràng giữa Chủ hộ gia đình (`family_admin`) và Quản trị viên Trung tâm (`system_admin`) quản lý Dược thư Quốc gia.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-white border-top py-4 text-center text-muted small">
        <div class="container">
            <div class="fw-bold text-dark mb-1">MedCabinet - Hệ thống Quản lý Tủ thuốc & Điều trị Ngoại trú Hộ Gia đình</div>
            <div>Đồ án Tốt nghiệp Chuyên ngành Mạng và Hệ thống thông tin – Đại học Xây dựng Hà Nội (HUCE)</div>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
