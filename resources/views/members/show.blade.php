@extends('layouts.app')

@section('title', 'Hồ sơ Sức khỏe: ' . $member->name . ' - Tủ thuốc gia đình')

@section('content')
<!-- Header và Điều hướng -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('members.index') }}" class="text-decoration-none text-muted small d-inline-flex align-items-center mb-1">
            <i class="bi bi-arrow-left me-1"></i> Quay lại danh sách thành viên
        </a>
        <h1 class="h4 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-medical-fill text-primary"></i>
            Hồ sơ Bệnh án điện tử: <span class="text-primary">{{ $member->name }}</span>
        </h1>
        <div class="text-muted small">
            Mã định danh thành viên: <strong>MB-{{ str_pad($member->id, 4, '0', STR_PAD_LEFT) }}</strong> · Hộ gia đình: <strong>{{ $member->family->name }}</strong>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> In hồ sơ bệnh án
        </button>

        @if (auth()->user()->isFamilyAdmin())
            <a href="{{ route('members.edit', $member) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Sửa thông tin sức khỏe
            </a>
        @endif
    </div>
</div>

<!-- KHỐI 1: THÔNG TIN TỔNG QUAN SINH TRẮC & NHÂN KHẨU (HERO CARD) -->
<div class="content-card mb-3 shadow-sm border-top border-top-4 border-primary">
    <div class="content-card-body p-4">
        <div class="row g-3 align-items-center">
            <!-- Avatar & Cơ bản -->
            <div class="col-12 col-md-6 col-lg-4 d-flex align-items-center gap-3 border-end">
                <img src="{{ $member->avatar_url }}" alt="{{ $member->name }}" class="rounded-circle border shadow-sm flex-shrink-0" style="width: 72px; height: 72px; object-fit: cover;">
                <div>
                    <h4 class="fw-bold text-dark mb-1">{{ $member->name }}</h4>
                    <div class="d-flex flex-wrap gap-1 align-items-center">
                        <span class="badge bg-primary-subtle text-primary">{{ $member->relationship_label }}</span>
                        <span class="badge bg-light text-muted border">{{ $member->gender_label }}</span>
                        @if ($member->blood_type)
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                <i class="bi bi-droplet-fill me-1"></i>Nhóm máu {{ $member->blood_type }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Chỉ số tuổi, ngày sinh, tài khoản -->
            <div class="col-6 col-md-3 col-lg-4 border-end">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="text-muted small" style="font-size: 11.5px;">Độ tuổi hiện tại:</div>
                        <div class="fw-bold fs-5 text-dark">{{ $member->age ? $member->age . ' tuổi' : '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small" style="font-size: 11.5px;">Ngày sinh:</div>
                        <div class="fw-semibold text-dark">{{ $member->dob ? $member->dob->format('d/m/Y') : 'Chưa cập nhật' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small" style="font-size: 11.5px;">Tài khoản App kết nối:</div>
                        @if ($member->user)
                            <span class="badge bg-success-subtle text-success">
                                <i class="bi bi-check-circle me-1"></i>{{ $member->user->email }}
                            </span>
                        @else
                            <span class="badge bg-light text-muted border">Chưa liên kết smartphone</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Tổng kết số liệu y tế -->
            <div class="col-6 col-md-3 col-lg-4">
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="text-muted small" style="font-size: 11px;">Đơn điều trị</div>
                        <div class="fs-4 fw-bold text-primary">{{ $treatmentCourses->count() }}</div>
                    </div>
                    <div class="col-4 border-start border-end">
                        <div class="text-muted small" style="font-size: 11px;">Thuốc đang uống</div>
                        <div class="fs-4 fw-bold text-success">{{ $activeSchedules->count() }}</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small" style="font-size: 11px;">Tổng viện phí</div>
                        <div class="fw-bold text-dark" style="font-size: 13px;">{{ number_format($totalExpense, 0, ',', '.') }} đ</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KHỐI 2: CẢNH BÁO AN TOÀN Y TẾ ĐẶC BIỆT (DỊ ỨNG & BỆNH NỀN) -->
<div class="row g-3 mb-3">
    <!-- Cảnh báo Dị ứng thuốc -->
    <div class="col-12 col-md-6">
        <div class="content-card h-100 mb-0 border-danger border-opacity-50">
            <div class="content-card-header bg-danger bg-opacity-10 py-2">
                <span class="fw-bold text-danger d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i> TIỀN SỬ DỊ ỨNG THUỐC ĐẶC BIỆT
                </span>
            </div>
            <div class="content-card-body p-3">
                @if ($member->has_allergies)
                    <div class="alert alert-danger py-2 px-3 small mb-0 border-0">
                        <strong class="d-block mb-1">CẢNH BÁO Y TẾ:</strong>
                        <div class="fs-6 fw-bold text-danger-emphasis">{{ $member->allergies }}</div>
                        <div class="text-muted mt-2" style="font-size: 11.5px;">
                            <i class="bi bi-shield-check text-success me-1"></i>Hệ thống tự động rà soát cảnh báo tương tác khi có đơn thuốc chứa hoạt chất này.
                        </div>
                    </div>
                @else
                    <div class="p-3 bg-light rounded text-center text-muted small">
                        <i class="bi bi-check-circle-fill text-success fs-4 d-block mb-1"></i>
                        Chưa ghi nhận tiền sử dị ứng thuốc nào đối với thành viên này.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Bệnh lý mạn tính -->
    <div class="col-12 col-md-6">
        <div class="content-card h-100 mb-0 border-warning border-opacity-50">
            <div class="content-card-header bg-warning bg-opacity-10 py-2">
                <span class="fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-heart-pulse-fill text-warning fs-5"></i> BỆNH LÝ MẠN TÍNH & BỆNH NỀN
                </span>
            </div>
            <div class="content-card-body p-3">
                @if ($member->has_underlying_diseases)
                    <div class="alert alert-warning py-2 px-3 small mb-0 border-0 bg-warning bg-opacity-10">
                        <strong class="d-block mb-1 text-warning-emphasis">CHẨN ĐOÁN MẠN TÍNH:</strong>
                        <div class="fw-semibold text-dark">{{ $member->underlying_diseases }}</div>
                        <div class="text-muted mt-2" style="font-size: 11.5px;">
                            Cần theo dõi sát sao phác đồ điều trị lâu dài và lịch tái khám định kỳ.
                        </div>
                    </div>
                @else
                    <div class="p-3 bg-light rounded text-center text-muted small">
                        <i class="bi bi-heart-fill text-primary fs-4 d-block mb-1"></i>
                        Hiện không có ghi nhận bệnh lý nền mạn tính nào.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- KHỐI 3: LỊCH UỐNG THUỐC HÀNG NGÀY ĐANG ÁP DỤNG -->
<div class="content-card mb-3">
    <div class="content-card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark">
            <i class="bi bi-alarm-fill text-primary me-2"></i>CÁC THUỐC ĐANG UỐNG HÀNG NGÀY ({{ $activeSchedules->count() }} CỮ)
        </span>
        <a href="{{ route('schedules.index') }}" class="small text-decoration-none">Xem toàn bộ lịch uống <i class="bi bi-chevron-right"></i></a>
    </div>
    <div class="table-responsive">
        <table class="table table-erp table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;" class="text-center">STT</th>
                    <th style="width: 130px;">Cữ uống</th>
                    <th style="width: 100px;">Giờ quy định</th>
                    <th>Tên thuốc</th>
                    <th style="width: 140px;">Liều dùng</th>
                    <th style="width: 180px;">Thời điểm uống</th>
                    <th style="width: 120px;" class="text-center">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($activeSchedules as $sIdx => $sch)
                    <tr>
                        <td class="text-center text-muted">{{ $sIdx + 1 }}</td>
                        <td>
                            <span class="badge {{ $sch->time_of_day === 'sang' ? 'bg-warning text-dark' : ($sch->time_of_day === 'trua' ? 'bg-info text-dark' : 'bg-primary') }}">
                                Cữ {{ $sch->time_of_day_label }}
                            </span>
                        </td>
                        <td class="fw-bold">{{ substr($sch->scheduled_time, 0, 5) }}</td>
                        <td>
                            <div class="fw-bold text-dark">{{ $sch->medicine_name }}</div>
                            @if ($sch->prescriptionItem && $sch->prescriptionItem->treatmentCourse)
                                <div class="text-muted small" style="font-size: 11px;">
                                    Đơn: {{ $sch->prescriptionItem->treatmentCourse->title }}
                                </div>
                            @endif
                        </td>
                        <td><strong>{{ $sch->dosage }}</strong> {{ $sch->unit }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $sch->meal_timing_label }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="bi bi-check-circle me-1"></i>Đang duy trì
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-check fs-2 text-muted d-block mb-1"></i>
                            Thành viên này hiện không có lịch uống thuốc hàng ngày nào.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- KHỐI 4: ĐƠN THUỐC & CÁC ĐỢT ĐIỀU TRỊ NGOẠI TRÚ GẦN ĐÂY -->
<div class="content-card mb-3">
    <div class="content-card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark">
            <i class="bi bi-file-earmark-medical text-primary me-2"></i>LỊCH SỬ CÁC ĐỢT ĐIỀU TRỊ / ĐƠN THUỐC BÁC SĨ ({{ $treatmentCourses->count() }})
        </span>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('treatment-courses.create', ['member_id' => $member->id]) }}" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 12px;">
                <i class="bi bi-plus-circle me-1"></i>Kê đơn mới
            </a>
            <a href="{{ route('treatment-courses.index', ['member_id' => $member->id]) }}" class="small text-decoration-none">Tất cả đơn thuốc <i class="bi bi-chevron-right"></i></a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-erp table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;" class="text-center">STT</th>
                    <th>Chẩn đoán / Đợt bệnh</th>
                    <th style="width: 180px;">Bác sĩ / Nơi khám</th>
                    <th style="width: 140px;">Thời gian</th>
                    <th>Danh sách thuốc chỉ định</th>
                    <th style="width: 130px;" class="text-center">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($treatmentCourses as $cIdx => $tc)
                    <tr>
                        <td class="text-center text-muted">{{ $cIdx + 1 }}</td>
                        <td>
                            <a href="{{ route('treatment-courses.show', $tc) }}" class="fw-bold text-primary text-decoration-none">
                                {{ $tc->title }}
                            </a>
                            <div class="text-muted small" style="font-size: 11.5px;">{{ $tc->diagnosis }}</div>
                        </td>
                        <td>
                            <div>{{ $tc->doctor_name ?: '—' }}</div>
                            <div class="text-muted small" style="font-size: 11px;">{{ $tc->hospital_name }}</div>
                        </td>
                        <td class="small">
                            <div>Từ: {{ $tc->start_date ? $tc->start_date->format('d/m/Y') : '—' }}</div>
                            <div>Đến: {{ $tc->end_date ? $tc->end_date->format('d/m/Y') : 'Dài hạn' }}</div>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                @forelse ($tc->prescriptionItems as $pItem)
                                    <span class="badge bg-light text-dark border">
                                        {{ $pItem->medicine?->name ?? 'Thuốc' }} ({{ $pItem->dosage_morning ?? 0 }}-{{ $pItem->dosage_noon ?? 0 }}-{{ $pItem->dosage_evening ?? 0 }})
                                    </span>
                                @empty
                                    <span class="text-muted small">Chưa nhập thuốc</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="text-center">
                            @if ($tc->status === 'dang_dieu_tri')
                                <span class="badge bg-success">Đang điều trị</span>
                            @else
                                <span class="badge bg-secondary">Đã hoàn thành</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-journal-medical fs-2 text-muted d-block mb-1"></i>
                            Chưa có đợt điều trị ngoại trú hoặc đơn thuốc nào được lưu cho thành viên này.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
