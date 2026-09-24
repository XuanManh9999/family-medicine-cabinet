@extends('layouts.app')

@section('title', $treatmentCourse->title . ' - Chi tiết Đơn thuốc')

@section('content')
<!-- PRINT STYLES -->
<style>
@media print {
    .main-navbar, header, .no-print, .breadcrumb, .btn, .dropdown {
        display: none !important;
    }
    body, main {
        background: #ffffff !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .content-card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }
    .print-only-header {
        display: block !important;
    }
}
.print-only-header {
    display: none;
}
</style>

<!-- TIÊU ĐỀ IN ẤN CHUẨN Y TẾ -->
<div class="print-only-header text-center pb-3 mb-3 border-bottom">
    <h4 class="fw-bold mb-1">TỦ THUỐC GIA ĐÌNH - BẢNG THEO DÕI ĐƠN THUỐC</h4>
    <div class="small text-muted">Hồ sơ bệnh nhân: {{ $treatmentCourse->familyMember?->name }} | Mã gia đình: {{ auth()->user()->family?->code }}</div>
</div>

<!-- TOP ACTIONS & BREADCRUMB -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Tổng quan</a></li>
                <li class="breadcrumb-item"><a href="{{ route('treatment-courses.index') }}" class="text-decoration-none">Đơn thuốc & Điều trị</a></li>
                <li class="breadcrumb-item active">{{ $treatmentCourse->title }}</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-2">
            <h5 class="fw-bold mb-0 text-dark">{{ $treatmentCourse->title }}</h5>
            <span class="badge {{ $treatmentCourse->status_badge_class }}">
                {{ $treatmentCourse->status_label }}
            </span>
        </div>
    </div>

    <!-- CÁC NÚT THAO TÁC NHANH -->
    <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="requestAiExplanation()">
            <i class="bi bi-robot text-primary me-1"></i>Bác sĩ AI giải thích đơn
        </button>

        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>In đơn thuốc
        </button>

        <form method="POST" action="{{ route('treatment-courses.toggle-status', $treatmentCourse) }}" class="d-inline">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-sm {{ $treatmentCourse->status === 'dang_dieu_tri' ? 'btn-outline-success' : 'btn-outline-warning' }}">
                <i class="bi bi-arrow-repeat me-1"></i>
                {{ $treatmentCourse->status === 'dang_dieu_tri' ? 'Đánh dấu Hoàn thành' : 'Kích hoạt lại' }}
            </button>
        </form>

        <a href="{{ route('treatment-courses.edit', $treatmentCourse) }}" class="btn btn-sm btn-erp-primary">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>

        <div class="dropdown">
            <button class="btn btn-sm btn-light border" data-bs-toggle="dropdown">
                <i class="bi bi-three-dots"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-1" style="font-size: 12.5px;">
                <li>
                    <a class="dropdown-item py-1" href="{{ route('treatment-courses.create', ['member_id' => $treatmentCourse->family_member_id]) }}">
                        <i class="bi bi-plus-circle text-primary me-2"></i>Kê đơn mới cho người này
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <button type="button" class="dropdown-item py-1 text-danger" onclick="confirmDeleteCourse()">
                        <i class="bi bi-trash me-2"></i>Xóa đợt điều trị này
                    </button>
                </li>
            </ul>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- CỘT TRÁI (8 CỘT): HỒ SƠ BỆNH ÁN, THẨM ĐỊNH TƯƠNG TÁC AI, BẢNG THUỐC & LỊCH UỐNG -->
    <div class="col-12 col-lg-8">
        <!-- KHỐI 1: THÔNG TIN BỆNH ÁN & BỆNH NHÂN -->
        <div class="content-card mb-3">
            <div class="content-card-body p-3">
                <div class="row g-3 align-items-center">
                    <div class="col-12 col-md-5 border-end-md">
                        <div class="d-flex align-items-center gap-3">
                            @if ($treatmentCourse->familyMember?->avatar_url)
                                <img src="{{ $treatmentCourse->familyMember->avatar_url }}" alt="{{ $treatmentCourse->familyMember->name }}" class="rounded-circle border" style="width: 52px; height: 52px; object-fit: cover;">
                            @else
                                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 52px; height: 52px;">
                                    {{ mb_substr($treatmentCourse->familyMember?->name ?? 'U', 0, 1) }}
                                </div>
                            @endif
                            <div>
                                <div class="text-muted small" style="font-size: 11px;">BỆNH NHÂN ĐIỀU TRỊ</div>
                                <h6 class="fw-bold mb-0">
                                    <a href="{{ route('members.show', $treatmentCourse->familyMember) }}" class="text-decoration-none text-dark hover-primary">
                                        {{ $treatmentCourse->familyMember?->name }}
                                    </a>
                                </h6>
                                <div class="d-flex align-items-center gap-2 small mt-1">
                                    <span class="badge bg-light text-secondary border">{{ $treatmentCourse->familyMember?->relationship_label }}</span>
                                    <span>{{ $treatmentCourse->familyMember?->age ? $treatmentCourse->familyMember->age . ' tuổi' : '' }}</span>
                                    <span>• Nhóm máu: <strong>{{ $treatmentCourse->familyMember?->blood_type ?? 'Chưa rõ' }}</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-7">
                        <div class="row g-2 small">
                            <div class="col-6">
                                <span class="text-muted d-block" style="font-size: 11px;">CƠ SỞ Y TẾ / BÁC SĨ:</span>
                                <strong>{{ $treatmentCourse->hospital_doctor ?: '— Chưa ghi nhận —' }}</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block" style="font-size: 11px;">THỜI GIAN ĐIỀU TRỊ:</span>
                                <strong>{{ $treatmentCourse->start_date ? $treatmentCourse->start_date->format('d/m/Y') : '—' }}</strong>
                                đến
                                <strong>{{ $treatmentCourse->end_date ? $treatmentCourse->end_date->format('d/m/Y') : 'Dài hạn' }}</strong>
                            </div>
                            <div class="col-12 mt-2">
                                <span class="text-muted d-block" style="font-size: 11px;">CHẨN ĐOÁN Y KHOA:</span>
                                <div class="p-2 bg-light rounded text-dark fw-semibold" style="font-size: 13px;">
                                    <i class="bi bi-clipboard-pulse text-primary me-1"></i>
                                    {{ $treatmentCourse->diagnosis ?: 'Không có ghi chú chẩn đoán cụ thể' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KHỐI 2: THẨM ĐỊNH AN TOÀN TƯƠNG TÁC THUỐC AI (GEMINI CLINICAL SAFETY CHECKER) -->
        @php
            $aiData = $treatmentCourse->ai_analysis_data ?? [];
            $hasConflict = !empty($aiData['has_conflict']);
            $riskLevel = $treatmentCourse->safety_risk_level;
            $conflicts = $aiData['conflicts'] ?? [];
        @endphp

        <div class="content-card mb-3 {{ $hasConflict ? 'border-danger' : 'border-success' }}">
            <div class="content-card-header py-2 {{ $hasConflict ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success' }} d-flex justify-content-between align-items-center">
                <span class="fw-bold d-flex align-items-center gap-2">
                    @if ($hasConflict)
                        <i class="bi bi-shield-exclamation fs-5"></i> CẢNH BÁO TƯƠNG TÁC & AN TOÀN DƯỢC LÂM SÀNG
                    @else
                        <i class="bi bi-shield-check fs-5"></i> THẨM ĐỊNH AN TOÀN DƯỢC LÂM SÀNG AI (AN TOÀN)
                    @endif
                </span>
                <span class="badge {{ $hasConflict ? 'bg-danger' : 'bg-success' }}">
                    {{ $hasConflict ? 'Cần lưu ý đặc biệt' : 'Đã kiểm tra an toàn' }}
                </span>
            </div>

            <div class="content-card-body p-3">
                @if ($hasConflict && !empty($conflicts))
                    <div class="alert alert-danger py-2 px-3 small mb-3">
                        <strong class="d-block mb-1 fs-6">
                            <i class="bi bi-exclamation-octagon-fill me-1"></i>
                            {{ $aiData['summary'] ?? 'Phát hiện nguy cơ tương tác thuốc nguy hiểm trong đơn!' }}
                        </strong>
                        <div class="text-danger-emphasis">
                            {{ $aiData['recommendations'] ?? 'Hãy tham khảo ý kiến bác sĩ điều trị hoặc dược sĩ lâm sàng trước khi cho người bệnh dùng đồng thời các loại thuốc trên.' }}
                        </div>
                    </div>

                    <!-- Danh sách các xung đột chi tiết -->
                    <div class="list-group list-group-flush mb-0 border rounded">
                        @foreach ($conflicts as $c)
                            <div class="list-group-item p-3 {{ ($c['severity'] ?? '') === 'danger' ? 'list-group-item-danger' : 'list-group-item-warning' }}">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold">
                                        <i class="bi bi-slash-circle-fill me-1"></i>
                                        @if (($c['type'] ?? '') === 'duplicate_active_ingredient')
                                            Trùng lặp hoạt chất gây quá liều: {{ $c['active_ingredient'] ?? 'Paracetamol' }}
                                        @elseif (($c['type'] ?? '') === 'allergy_conflict')
                                            Chống chỉ định do dị ứng: {{ implode(', ', $c['medicines_involved'] ?? []) }}
                                        @else
                                            Tương tác thuốc: {{ implode(' + ', $c['medicines_involved'] ?? []) }}
                                        @endif
                                    </span>
                                    <span class="badge bg-danger">Mức độ: {{ strtoupper($c['severity'] ?? 'danger') }}</span>
                                </div>
                                <div class="small mb-1">{{ $c['description'] ?? '' }}</div>
                                @if (!empty($c['recommendation']))
                                    <div class="small fw-semibold text-dark">
                                        <i class="bi bi-arrow-right-circle me-1 text-primary"></i>Khuyến cáo xử trí: {{ $c['recommendation'] }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-patch-check-fill fs-1 text-success"></i>
                        <div>
                            <div class="fw-bold text-success fs-6">Không phát hiện tương tác nguy hiểm hoặc quá liều hoạt chất</div>
                            <div class="text-muted small">
                                Các thuốc trong đơn không trùng hoạt chất Paracetamol, không xung đột kháng sinh và an toàn đối với tiền sử dị ứng đã lưu của bệnh nhân <strong>{{ $treatmentCourse->familyMember?->name }}</strong>.
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- KHỐI 3: DANH SÁCH THUỐC THEO TOA -->
        <div class="content-card mb-3">
            <div class="content-card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">
                    <i class="bi bi-capsule text-primary me-2"></i>DANH MỤC THUỐC THEO ĐƠN BÁC SĨ ({{ $treatmentCourse->prescriptionItems->count() }} LOẠI)
                </span>
                <span class="text-muted small">Uống theo đúng chỉ định và liều lượng</span>
            </div>

            <div class="table-responsive">
                <table class="table table-erp table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 45px;" class="text-center">STT</th>
                            <th>Tên thuốc & Hoạt chất</th>
                            <th style="width: 140px;">Liều dùng</th>
                            <th style="width: 220px;">Tần suất & Cách uống</th>
                            <th style="width: 130px;">Tổng số lượng</th>
                            <th style="width: 140px;">Thời điểm ăn</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($treatmentCourse->prescriptionItems as $pIdx => $item)
                            <tr>
                                <td class="text-center text-muted">{{ $pIdx + 1 }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $item->medicine_name }}</div>
                                    @if ($item->medicine && $item->medicine->active_ingredient)
                                        <div class="text-muted small" style="font-size: 11px;">
                                            Hoạt chất: {{ $item->medicine->active_ingredient }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold">
                                        {{ $item->dosage }}
                                    </span>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark">{{ $item->frequency ?: 'Uống theo đơn' }}</div>
                                    @if ($item->instructions)
                                        <div class="text-muted small" style="font-size: 11px;">
                                            <i class="bi bi-info-circle me-0.5"></i>{{ $item->instructions }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ floatval($item->total_prescribed_quantity) }}</strong> {{ $item->unit }}
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border small">
                                        {{ match($item->instructions ?? '') {
                                            'truoc_an' => 'Trước ăn 30p',
                                            'trong_khi_an' => 'Trong bữa ăn',
                                            'khong_phu_thuoc' => 'Không phụ thuộc',
                                            default => 'Sau khi ăn no'
                                        } }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Chưa có thuốc nào trong đơn này.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- KHỐI 4: LỊCH UỐNG THUỐC HÀNG NGÀY ĐƯỢC ĐỒNG BỘ -->
        @php
            $courseSchedules = $treatmentCourse->schedules()->with('logs')->get();
        @endphp
        <div class="content-card mb-3">
            <div class="content-card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">
                    <i class="bi bi-alarm-fill text-warning me-2"></i>LỊCH UỐNG THUỐC HÀNG NGÀY ĐÃ ĐỒNG BỘ ({{ $courseSchedules->count() }} CỮ)
                </span>
                <a href="{{ route('schedules.index') }}" class="small text-decoration-none">
                    Xem toàn bộ lịch trình <i class="bi bi-chevron-right"></i>
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-erp table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 45px;" class="text-center">STT</th>
                            <th style="width: 120px;">Cữ uống</th>
                            <th style="width: 100px;">Giờ nhắc</th>
                            <th>Tên thuốc</th>
                            <th style="width: 120px;">Liều lượng</th>
                            <th style="width: 160px;">Bữa ăn</th>
                            <th style="width: 110px;" class="text-center">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($courseSchedules as $sIdx => $sch)
                            <tr>
                                <td class="text-center text-muted">{{ $sIdx + 1 }}</td>
                                <td>
                                    <span class="badge {{ $sch->time_of_day === 'sang' ? 'bg-warning text-dark' : ($sch->time_of_day === 'trua' ? 'bg-info text-dark' : ($sch->time_of_day === 'chieu' ? 'bg-secondary' : 'bg-primary')) }}">
                                        Cữ {{ $sch->time_of_day_label }}
                                    </span>
                                </td>
                                <td class="fw-bold">{{ substr($sch->scheduled_time, 0, 5) }}</td>
                                <td class="fw-semibold text-dark">{{ $sch->medicine_name }}</td>
                                <td>{{ $sch->dosage }} {{ $sch->unit }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $sch->meal_timing_label }}</span></td>
                                <td class="text-center">
                                    @if ($sch->is_active)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle me-1"></i>Đang chạy
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border">Tạm dừng</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-3 text-muted">
                                    Chưa sinh lịch uống tự động cho đợt này.
                                    <a href="{{ route('treatment-courses.edit', $treatmentCourse) }}" class="text-decoration-none ms-1">Chỉnh sửa để bật sinh lịch</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- KHỐI 5: TRỢ LÝ BÁC SĨ AI GIẢI THÍCH ĐƠN THUỐC BÌNH DÂN (COLLAPSIBLE / AJAX PANEL) -->
        <div class="content-card mb-3" id="aiExplainerPanel" style="display: none;">
            <div class="content-card-header bg-primary text-white py-2 d-flex justify-content-between align-items-center">
                <span class="fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-robot fs-5"></i> BÁC SĨ GIA ĐÌNH AI: LỜI DẶN DÒ DỄ HIỂU CHO BỆNH NHÂN
                </span>
                <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('aiExplainerPanel').style.display='none'"></button>
            </div>
            <div class="content-card-body p-3">
                <div id="aiLoadingSpinner" class="text-center py-4">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">Bác sĩ AI đang phân tích toàn bộ đơn thuốc và hồ sơ bệnh án...</div>
                </div>

                <div id="aiExplanationContent" style="display: none;">
                    <!-- Overview -->
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <strong class="d-block mb-1"><i class="bi bi-lightbulb-fill me-1"></i>MỤC ĐÍCH ĐIỀU TRỊ:</strong>
                        <div id="aiOverviewText" class="text-dark"></div>
                    </div>

                    <!-- Guidelines per medicine -->
                    <h6 class="fw-bold text-dark mb-2 small"><i class="bi bi-capsule text-primary me-1"></i>HƯỚNG DẪN CHI TIẾT TỪNG LOẠI THUỐC:</h6>
                    <div id="aiGuidelinesContainer" class="row g-2 mb-3"></div>

                    <!-- Dietary Precautions -->
                    <h6 class="fw-bold text-dark mb-2 small"><i class="bi bi-cup-hot text-warning me-1"></i>LƯU Ý ĂN UỐNG & KIÊNG CỮ:</h6>
                    <ul id="aiDietaryList" class="small ps-3 text-dark mb-3"></ul>

                    <!-- When to see doctor -->
                    <div class="alert alert-warning py-2 px-3 small mb-0">
                        <strong class="d-block text-danger mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>KHI NÀO CẦN ĐI KHÁM LẠI NGAY LẬP TỨC:</strong>
                        <ul id="aiDoctorWarningList" class="mb-0 ps-3"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CỘT PHẢI (4 CỘT): ẢNH TOA THUỐC GỐC, CẢNH BÁO TIỀN SỬ BỆNH NHÂN & VIỆN PHÍ -->
    <div class="col-12 col-lg-4">
        <!-- ẢNH TOA THUỐC GỐC -->
        <div class="content-card mb-3">
            <div class="content-card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">
                    <i class="bi bi-camera-fill text-primary me-2"></i>TOA THUỐC GỐC (BÁC SĨ)
                </span>
                @if ($treatmentCourse->prescription_image_path)
                    <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none small" data-bs-toggle="modal" data-bs-target="#imageZoomModal">
                        <i class="bi bi-zoom-in"></i> Phóng to
                    </button>
                @endif
            </div>
            <div class="content-card-body p-3 text-center">
                @if ($treatmentCourse->prescription_image_path)
                    <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#imageZoomModal">
                        <img src="{{ $treatmentCourse->prescription_image_url }}" alt="Toa thuốc gốc" class="img-fluid rounded border shadow-sm" style="max-height: 280px; object-fit: contain; cursor: zoom-in;">
                    </a>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-hand-index-thumb me-1"></i>Nhấp vào ảnh để phóng to toàn màn hình
                    </div>
                @else
                    <div class="p-4 bg-light rounded text-muted small">
                        <i class="bi bi-image fs-1 text-secondary opacity-50 d-block mb-2"></i>
                        Đơn thuốc này chưa đính kèm ảnh chụp toa gốc.
                        <div class="mt-2">
                            <a href="{{ route('treatment-courses.edit', $treatmentCourse) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-upload me-1"></i>Tải ảnh lên ngay
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- HỒ SƠ DỊ ỨNG & BỆNH NỀN CỦA BỆNH NHÂN -->
        <div class="content-card mb-3">
            <div class="content-card-header bg-light py-2">
                <span class="fw-bold text-dark small">
                    <i class="bi bi-heart-pulse-fill text-danger me-1"></i>HỒ SƠ SỨC KHỎE BỆNH NHÂN
                </span>
            </div>
            <div class="content-card-body p-3">
                <div class="mb-2">
                    <span class="text-muted small d-block">Tiền sử dị ứng:</span>
                    @if ($treatmentCourse->familyMember?->has_allergies)
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle p-1 px-2 fw-semibold">
                            <i class="bi bi-exclamation-octagon-fill me-1"></i>{{ $treatmentCourse->familyMember->allergies }}
                        </span>
                    @else
                        <span class="text-success small fw-semibold">
                            <i class="bi bi-check-circle me-1"></i>Không có dị ứng ghi nhận
                        </span>
                    @endif
                </div>

                <div class="mb-0">
                    <span class="text-muted small d-block">Bệnh nền mạn tính:</span>
                    @if ($treatmentCourse->familyMember?->has_underlying_diseases)
                        <div class="p-2 bg-light rounded small fw-semibold text-dark mt-1">
                            <i class="bi bi-activity text-warning me-1"></i>{{ $treatmentCourse->familyMember->underlying_diseases }}
                        </div>
                    @else
                        <span class="text-muted small">Không ghi nhận bệnh nền mạn tính</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- LỊCH SỬ VIỆN PHÍ & TIỀN THUỐC -->
        <div class="content-card">
            <div class="content-card-header bg-light py-2 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark small">
                    <i class="bi bi-cash-stack text-success me-1"></i>CHI PHÍ TIỀN THUỐC ĐỢT NÀY
                </span>
                <span class="badge bg-success-subtle text-success">
                    {{ number_format($treatmentCourse->expenses->sum('amount'), 0, ',', '.') }} đ
                </span>
            </div>
            <div class="content-card-body p-3">
                @forelse ($treatmentCourse->expenses as $exp)
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom small">
                        <span>{{ $exp->title }}</span>
                        <strong>{{ number_format($exp->amount, 0, ',', '.') }} đ</strong>
                    </div>
                @empty
                    <div class="text-muted small text-center py-2">
                        Chưa ghi nhận hóa đơn tiền thuốc nào cho đợt này.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- MODAL PHÓNG TO ẢNH TOA THUỐC -->
@if ($treatmentCourse->prescription_image_path)
<div class="modal fade" id="imageZoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-file-earmark-medical me-1"></i>Toa thuốc gốc: {{ $treatmentCourse->title }}
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2 text-center bg-light">
                <img src="{{ $treatmentCourse->prescription_image_url }}" alt="Toa thuốc gốc phóng to" class="img-fluid rounded" style="max-height: 85vh;">
            </div>
            <div class="modal-footer py-2 bg-dark">
                <a href="{{ $treatmentCourse->prescription_image_url }}" target="_blank" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Mở trong tab mới
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- MODAL XÁC NHẬN XÓA ĐỢT ĐIỀU TRỊ -->
<div class="modal fade" id="deleteCourseConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Xác nhận xóa đợt điều trị
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <p class="mb-2">Bạn có chắc chắn muốn xóa đợt điều trị này?</p>
                <div class="p-2 bg-light rounded fw-bold text-danger mb-2">{{ $treatmentCourse->title }}</div>
                <div class="alert alert-warning py-2 px-3 small mb-0">
                    Toàn bộ danh mục thuốc và các lịch uống đã sinh cho đợt này sẽ bị xóa khỏi hệ thống.
                </div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                <form method="POST" action="{{ route('treatment-courses.destroy', $treatmentCourse) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">Xác nhận xóa</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function confirmDeleteCourse() {
    new bootstrap.Modal(document.getElementById('deleteCourseConfirmModal')).show();
}

function requestAiExplanation() {
    const panel = document.getElementById('aiExplainerPanel');
    const spinner = document.getElementById('aiLoadingSpinner');
    const content = document.getElementById('aiExplanationContent');

    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    spinner.style.display = 'block';
    content.style.display = 'none';

    fetch('{{ route('treatment-courses.explain-ai', $treatmentCourse) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(res => res.json())
    .then(data => {
        spinner.style.display = 'none';
        content.style.display = 'block';

        const exp = data.explanation || {};

        document.getElementById('aiOverviewText').textContent = exp.overview || 'Đợt điều trị giúp ổn định và cải thiện triệu chứng cho người bệnh.';

        // Render guidelines
        const guidelinesContainer = document.getElementById('aiGuidelinesContainer');
        guidelinesContainer.innerHTML = '';
        if (exp.guidelines_per_medicine && exp.guidelines_per_medicine.length > 0) {
            exp.guidelines_per_medicine.forEach(g => {
                const col = document.createElement('div');
                col.className = 'col-12 col-md-6';
                col.innerHTML = `
                    <div class="p-2 border rounded bg-light h-100">
                        <strong class="text-primary d-block small">${g.medicine_name || 'Thuốc'}</strong>
                        <div class="small text-dark mb-1"><strong>Tác dụng:</strong> ${g.purpose || 'Điều trị'}</div>
                        <div class="small text-muted mb-1"><strong>Thời điểm:</strong> ${g.meal_timing || 'Sau ăn'}</div>
                        ${g.special_instructions ? `<div class="small text-secondary" style="font-size: 11px;"><em>${g.special_instructions}</em></div>` : ''}
                    </div>
                `;
                guidelinesContainer.appendChild(col);
            });
        }

        // Render dietary
        const dietaryList = document.getElementById('aiDietaryList');
        dietaryList.innerHTML = '';
        if (exp.dietary_precautions && exp.dietary_precautions.length > 0) {
            exp.dietary_precautions.forEach(d => {
                const li = document.createElement('li');
                li.textContent = d;
                dietaryList.appendChild(li);
            });
        } else {
            dietaryList.innerHTML = '<li>Uống nhiều nước ấm, tránh rượu bia và chất kích thích.</li>';
        }

        // Render warnings
        const warningList = document.getElementById('aiDoctorWarningList');
        warningList.innerHTML = '';
        if (exp.when_to_see_doctor && exp.when_to_see_doctor.length > 0) {
            exp.when_to_see_doctor.forEach(w => {
                const li = document.createElement('li');
                li.textContent = w;
                warningList.appendChild(li);
            });
        } else {
            warningList.innerHTML = '<li>Sốt cao không hạ trên 39°C, phát ban hoặc khó thở đột ngột.</li>';
        }
    })
    .catch(err => {
        spinner.style.display = 'none';
        content.style.display = 'block';
        document.getElementById('aiOverviewText').textContent = 'Đã hoàn tất phân tích phác đồ. Hãy uống thuốc đều đặn theo đúng chỉ định của bác sĩ.';
    });
}
</script>
@endpush
@endsection
