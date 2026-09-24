@extends('layouts.app')

@section('title', 'Tổng quan hệ thống')

@section('content')
<!-- KPI Summary Cards (Thanh chỉ số nhanh chuẩn ERP) -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-box">
            <div>
                <div class="kpi-title">Tổng thuốc trong tủ</div>
                <div class="kpi-number text-primary">{{ $totalItems }} <small class="text-muted fw-normal" style="font-size: 12px;">loại</small></div>
            </div>
            <div class="text-primary fs-3 opacity-50">
                <i class="bi bi-capsule"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-box {{ $nearExpiryItemsCount > 0 ? 'border-warning' : '' }}">
            <div>
                <div class="kpi-title">Thuốc cận hạn (&lt;30 ngày)</div>
                <div class="kpi-number text-warning">{{ $nearExpiryItemsCount }} <small class="text-muted fw-normal" style="font-size: 12px;">loại</small></div>
            </div>
            <div class="text-warning fs-3 opacity-50">
                <i class="bi bi-hourglass-split"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-box {{ $expiredItemsCount > 0 ? 'border-danger' : '' }}">
            <div>
                <div class="kpi-title">Thuốc đã hết hạn</div>
                <div class="kpi-number text-danger">{{ $expiredItemsCount }} <small class="text-muted fw-normal" style="font-size: 12px;">loại</small></div>
            </div>
            <div class="text-danger fs-3 opacity-50">
                <i class="bi bi-exclamation-octagon"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-box">
            <div>
                <div class="kpi-title">Tồn kho ít (≤5)</div>
                <div class="kpi-number text-secondary">{{ $lowStockItemsCount }} <small class="text-muted fw-normal" style="font-size: 12px;">loại</small></div>
            </div>
            <div class="text-secondary fs-3 opacity-50">
                <i class="bi bi-box-seam"></i>
            </div>
        </div>
    </div>
</div>

<!-- Main Work Area (Layout chuẩn phần mềm Quản lý nhà thuốc: Cột lọc bên trái + Bảng dữ liệu bên phải) -->
<div class="row g-3">
    <!-- CỘT LỌC & TÌM KIẾM BÊN TRÁI (Giống layout The Light / KiotViet) -->
    <div class="col-12 col-lg-3 col-xl-2">
        <div class="filter-panel mb-3">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                <span class="fw-bold text-dark"><i class="bi bi-funnel me-1"></i>Bộ lọc nhanh</span>
                <span class="badge bg-light text-muted border">F3</span>
            </div>

            <!-- Button Tìm kiếm -->
            <button type="button" class="btn btn-erp-success w-100 mb-3" onclick="alert('Đang lọc danh mục thuốc...')">
                <i class="bi bi-search me-1"></i> Tìm kiếm
            </button>

            <!-- Nhập từ khóa -->
            <div class="mb-3">
                <label class="form-label">Tên thuốc / Hoạt chất</label>
                <input type="text" class="form-control form-control-sm" placeholder="Nhập tên thuốc..." id="filterKeyword">
            </div>

            <!-- Chọn người dùng -->
            <div class="mb-3">
                <label class="form-label">Thành viên điều trị</label>
                <select class="form-select form-select-sm" id="filterMember">
                    <option value="">-- Tất cả thành viên --</option>
                    @foreach ($members as $m)
                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->relationship_text }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Checkbox trạng thái -->
            <div class="mb-3">
                <label class="form-label d-block">Trạng thái tủ thuốc</label>
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" id="checkNearExpiry" checked>
                    <label class="form-check-label" for="checkNearExpiry">Thuốc cận hạn (&lt;30d)</label>
                </div>
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" id="checkExpired" checked>
                    <label class="form-check-label text-danger" for="checkExpired">Thuốc đã hết hạn</label>
                </div>
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" id="checkLowStock">
                    <label class="form-check-label" for="checkLowStock">Tồn kho ít (≤ 5)</label>
                </div>
            </div>

            <hr class="my-2">

            <!-- Thông tin nhanh Hộ gia đình -->
            <div class="pt-2">
                <div class="small fw-bold text-muted mb-1">HỘ GIA ĐÌNH</div>
                <div class="fw-bold text-primary">{{ $family->name }}</div>
                <div class="text-muted small" style="font-size: 11.5px;">Mã hộ: <strong>{{ $family->code }}</strong></div>
                <div class="text-muted small" style="font-size: 11.5px;">Chi tiêu tháng: <strong>{{ number_format($monthlyExpense, 0, ',', '.') }} đ</strong></div>
            </div>
        </div>
    </div>

    <!-- KHU VỰC DỮ LIỆU CHÍNH BÊN PHẢI (Bảng dữ liệu chuẩn quản lý) -->
    <div class="col-12 col-lg-9 col-xl-10">
        <!-- Action Toolbar -->
        <div class="content-card mb-3">
            <div class="d-flex align-items-center justify-content-between p-2 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('cabinet.index') }}" class="btn btn-erp-success">
                        <i class="bi bi-plus-lg me-1"></i> Thêm mới thuốc
                    </a>
                    <a href="{{ route('treatment-courses.index') }}" class="btn btn-erp-primary">
                        <i class="bi bi-file-earmark-medical me-1"></i> Kê đơn thuốc mới
                    </a>
                    <a href="{{ route('schedules.index') }}" class="btn btn-outline-secondary btn-sm" style="font-size: 13px;">
                        <i class="bi bi-calendar-check me-1"></i> Điểm danh uống thuốc
                    </a>
                </div>

                <div class="text-end">
                    <span class="text-muted small me-2">Ngày: <strong>{{ now()->format('d/m/Y') }}</strong></span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        Tuân thủ hôm nay: {{ $complianceRate }}% ({{ $takenCount }}/{{ $totalSchedulesToday }} cữ)
                    </span>
                </div>
            </div>
        </div>

        <!-- BẢNG 1: LỊCH UỐNG THUỐC HÔM NAY CỦA CÁC THÀNH VIÊN -->
        <div class="content-card mb-3">
            <div class="content-card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-alarm text-primary"></i>
                    <span>LỊCH UỐNG THUỐC CẦN UỐNG HÔM NAY ({{ $todaySchedules->count() }} CỮ)</span>
                </div>
                <a href="{{ route('schedules.index') }}" class="text-decoration-none small text-primary fw-semibold">
                    Xem đầy đủ lịch tuần <i class="bi bi-chevron-right"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-erp table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 50px;" class="text-center">STT</th>
                            <th style="width: 110px;">Cữ uống</th>
                            <th style="width: 90px;">Giờ quy định</th>
                            <th style="width: 170px;">Thành viên</th>
                            <th>Tên thuốc điều trị</th>
                            <th style="width: 130px;">Liều dùng</th>
                            <th style="width: 180px;">Thời điểm uống</th>
                            <th style="width: 120px;" class="text-center">Trạng thái</th>
                            <th style="width: 100px;" class="text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($todaySchedules as $index => $sch)
                            @php
                                $isTaken = $sch->logs->contains('status', 'da_uong');
                            @endphp
                            <tr class="{{ $isTaken ? 'table-success bg-opacity-10' : '' }}">
                                <td class="text-center text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <span class="badge {{ $sch->time_of_day === 'sang' ? 'bg-warning text-dark' : ($sch->time_of_day === 'trua' ? 'bg-info text-dark' : 'bg-primary') }}">
                                        Cữ {{ $sch->time_of_day_label }}
                                    </span>
                                </td>
                                <td class="fw-bold">{{ substr($sch->scheduled_time, 0, 5) }}</td>
                                <td class="fw-semibold">
                                    <i class="bi bi-person text-secondary me-1"></i>{{ $sch->familyMember->name }}
                                </td>
                                <td>
                                    <span class="fw-bold text-dark">{{ $sch->medicine_name }}</span>
                                    @if ($sch->prescriptionItem && $sch->prescriptionItem->treatmentCourse)
                                        <div class="text-muted" style="font-size: 11px;">
                                            Đơn: {{ $sch->prescriptionItem->treatmentCourse->title }}
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $sch->dosage }} {{ $sch->unit }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $sch->meal_timing_label }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($isTaken)
                                        <span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Đã uống</span>
                                    @else
                                        <span class="badge bg-secondary">Chờ uống</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($isTaken)
                                        <span class="text-success small fw-semibold">Hoàn thành</span>
                                    @else
                                        <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 12px;">
                                            Điểm danh
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-3 text-muted">
                                    Hôm nay không có lịch uống thuốc nào.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- BẢNG 2: DANH MỤC TỒN KHO TỦ THUỐC GIA ĐÌNH -->
        <div class="content-card mb-3">
            <div class="content-card-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-boxes text-primary"></i>
                    <span>TỒN KHO TỦ THUỐC GIA ĐÌNH ({{ $totalItems }} LOẠI)</span>
                </div>
                <a href="{{ route('cabinet.index') }}" class="text-decoration-none small text-primary fw-semibold">
                    Xem toàn bộ tủ thuốc <i class="bi bi-chevron-right"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-erp table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 50px;" class="text-center">STT</th>
                            <th>Tên thuốc trong tủ</th>
                            <th style="width: 180px;">Nhóm dược lý</th>
                            <th style="width: 140px;">Vị trí ngăn tủ</th>
                            <th style="width: 100px;" class="text-end">Tồn kho</th>
                            <th style="width: 80px;">Đơn vị</th>
                            <th style="width: 120px;">Hạn sử dụng</th>
                            <th style="width: 130px;" class="text-center">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentCabinetItems as $idx => $item)
                            <tr>
                                <td class="text-center text-muted">{{ $idx + 1 }}</td>
                                <td>
                                    <span class="fw-bold">{{ $item->display_name }}</span>
                                    @if ($item->batch_number)
                                        <span class="text-muted small" style="font-size: 11px;">(Lô: {{ $item->batch_number }})</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $item->medicine && $item->medicine->category ? $item->medicine->category->name : 'Dược phẩm dùng ngoài' }}
                                </td>
                                <td>
                                    <i class="bi bi-geo-alt text-muted me-1"></i>{{ $item->storage_location ?? 'Ngăn chính' }}
                                </td>
                                <td class="text-end fw-bold {{ $item->quantity <= 5 ? 'text-danger' : 'text-dark' }}">
                                    {{ $item->quantity }}
                                </td>
                                <td>{{ $item->unit }}</td>
                                <td>
                                    {{ $item->expiry_date ? \Carbon\Carbon::parse($item->expiry_date)->format('d/m/Y') : '—' }}
                                </td>
                                <td class="text-center">
                                    @if ($item->is_expired)
                                        <span class="badge bg-danger">Hết hạn</span>
                                    @elseif ($item->is_near_expiry)
                                        <span class="badge bg-warning text-dark">Cận date (&lt;30d)</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Còn hạn</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-3 text-muted">
                                    Chưa có mục thuốc nào trong tủ thuốc gia đình.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- HÀNG PHỤ: CẢNH BÁO AN TOÀN & HỒ SƠ SỨC KHỎE NGƯỜI THÂN -->
        <div class="row g-3">
            <!-- Cảnh báo an toàn -->
            <div class="col-12 col-md-6">
                <div class="content-card h-100 mb-0">
                    <div class="content-card-header">
                        <span class="text-danger fw-bold"><i class="bi bi-shield-exclamation me-1"></i>CẢNH BÁO CẦN LƯU Ý ({{ $urgentAlerts->count() }})</span>
                        <a href="{{ route('alerts.index') }}" class="small text-decoration-none">Tất cả</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-erp align-middle">
                            <tbody>
                                @forelse ($urgentAlerts as $alert)
                                    <tr>
                                        <td style="width: 30px;" class="text-center">
                                            @if ($alert->severity === 'danger')
                                                <i class="bi bi-x-circle-fill text-danger fs-6"></i>
                                            @else
                                                <i class="bi bi-exclamation-triangle-fill text-warning fs-6"></i>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $alert->title }}</div>
                                            <div class="text-muted" style="font-size: 11.5px;">{{ $alert->message }}</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center text-muted py-3">Không có cảnh báo nguy cấp nào.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Hồ sơ thành viên -->
            <div class="col-12 col-md-6">
                <div class="content-card h-100 mb-0">
                    <div class="content-card-header">
                        <span class="text-primary fw-bold"><i class="bi bi-people me-1"></i>HỒ SƠ SỨC KHỎE NGƯỜI THÂN ({{ $members->count() }})</span>
                        <a href="{{ route('members.index') }}" class="small text-decoration-none">Quản lý</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-erp align-middle">
                            <thead>
                                <tr>
                                    <th>Họ tên</th>
                                    <th>Quan hệ</th>
                                    <th>Tuổi</th>
                                    <th>Nhóm máu</th>
                                    <th>Lưu ý bệnh / Dị ứng</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $m)
                                    <tr>
                                        <td class="fw-bold">{{ $m->name }}</td>
                                        <td>{{ $m->relationship_text }}</td>
                                        <td>{{ $m->age ?? '—' }}</td>
                                        <td><span class="badge bg-danger-subtle text-danger">{{ $m->blood_group ?? '—' }}</span></td>
                                        <td class="small">
                                            @if ($m->underlying_diseases)
                                                <span class="text-danger"><i class="bi bi-heart-pulse me-1"></i>{{ $m->underlying_diseases }}</span><br>
                                            @endif
                                            @if ($m->allergies)
                                                <span class="text-warning"><i class="bi bi-shield-slash me-1"></i>{{ $m->allergies }}</span>
                                            @endif
                                            @if (!$m->underlying_diseases && !$m->allergies)
                                                <span class="text-muted">Bình thường</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
