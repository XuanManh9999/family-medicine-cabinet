@extends('layouts.app')

@section('title', 'Trung tâm Cảnh báo Thông minh & An toàn Tủ thuốc')

@section('content')
<!-- HEADER VÀ NÚT TÁC VỤ -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-shield-exclamation text-danger fs-4"></i>
            Trung tâm Cảnh báo Thông minh & An toàn Tủ thuốc
        </h5>
        <div class="text-muted small">
            Giám sát thời gian thực hạn sử dụng, mức tồn kho dự phòng, nguy cơ quá liều Paracetamol và tuân thủ lịch uống thuốc.
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <form method="POST" action="{{ route('alerts.scan') }}" class="d-inline" id="scanForm">
            @csrf
            <button type="submit" class="btn btn-sm btn-erp-primary shadow-sm" id="btnScan" onclick="this.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span>Đang quét...';">
                <i class="bi bi-arrow-repeat me-1"></i>Quét an toàn tủ thuốc ngay
            </button>
        </form>

        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('alerts.mark-all-read') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary shadow-sm">
                    <i class="bi bi-check2-all me-1"></i>Đánh dấu tất cả đã đọc
                </button>
            </form>
        @endif
    </div>
</div>

<!-- KPI SUMMARY STATS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-danger">
            <div>
                <div class="kpi-title text-danger">Nguy hiểm khẩn cấp</div>
                <div class="kpi-number text-danger">{{ $dangerCount }}</div>
                <div class="text-muted" style="font-size: 11px;">Thuốc hết hạn & Quá liều</div>
            </div>
            <i class="bi bi-exclamation-octagon-fill fs-2 text-danger opacity-75"></i>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-warning">
            <div>
                <div class="kpi-title text-warning-emphasis">Cảnh báo cần chú ý</div>
                <div class="kpi-number text-warning-emphasis">{{ $warningCount }}</div>
                <div class="text-muted" style="font-size: 11px;">Cận date & Sắp hết hàng</div>
            </div>
            <i class="bi bi-exclamation-triangle-fill fs-2 text-warning opacity-75"></i>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-primary">
            <div>
                <div class="kpi-title text-primary">Chưa xử lý</div>
                <div class="kpi-number text-primary">{{ $unreadCount }}</div>
                <div class="text-muted" style="font-size: 11px;">Cần người nhà kiểm tra</div>
            </div>
            <i class="bi bi-bell-fill fs-2 text-primary opacity-75"></i>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-secondary">
            <div>
                <div class="kpi-title text-secondary">Tổng số cảnh báo</div>
                <div class="kpi-number text-dark">{{ $totalAlerts }}</div>
                <div class="text-muted" style="font-size: 11px;">Lịch sử cảnh báo an toàn</div>
            </div>
            <i class="bi bi-clipboard2-pulse fs-2 text-secondary opacity-75"></i>
        </div>
    </div>
</div>

<!-- THANH CÔNG CỤ LỌC & TÌM KIẾM -->
<div class="content-card mb-3">
    <div class="content-card-body p-2">
        <form method="GET" action="{{ route('alerts.index') }}" class="row g-2 align-items-center">
            <!-- Tìm kiếm từ khóa -->
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm border-start-0" 
                           placeholder="Tìm kiếm nội dung cảnh báo...">
                    @if ($search)
                        <a href="{{ route('alerts.index') }}" class="btn btn-sm btn-outline-secondary" title="Xóa tìm kiếm">
                            <i class="bi bi-x"></i>
                        </a>
                    @endif
                </div>
            </div>

            <!-- Lọc theo mức độ -->
            <div class="col-6 col-md-2">
                <select name="level" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Tất cả mức độ --</option>
                    <option value="danger" @selected($level === 'danger')>🔴 Nguy hiểm</option>
                    <option value="warning" @selected($level === 'warning')>🟡 Cảnh báo</option>
                    <option value="info" @selected($level === 'info')>🔵 Thông tin</option>
                </select>
            </div>

            <!-- Lọc theo loại cảnh báo -->
            <div class="col-6 col-md-3">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Tất cả loại sự cố --</option>
                    <option value="expired" @selected($type === 'expired')>Thuốc hết hạn sử dụng</option>
                    <option value="near_expiry" @selected($type === 'near_expiry')>Thuốc cận date (< 30 ngày)</option>
                    <option value="low_stock" @selected($type === 'low_stock')>Thuốc/Vật tư sắp hết hàng</option>
                    <option value="drug_interaction" @selected($type === 'drug_interaction')>Tương tác thuốc & Trùng hoạt chất</option>
                    <option value="schedule_reminder" @selected($type === 'schedule_reminder')>Nhắc nhở tuân thủ lịch uống</option>
                </select>
            </div>

            <!-- Lọc theo trạng thái đã đọc -->
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" @selected($status === 'all')>Tất cả trạng thái</option>
                    <option value="unread" @selected($status === 'unread')>Chưa đọc</option>
                    <option value="read" @selected($status === 'read')>Đã đọc</option>
                </select>
            </div>

            <!-- Nút lọc -->
            <div class="col-6 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-erp-primary w-100">
                    Lọc
                </button>
                @if ($level || $type || $status !== 'all' || $search)
                    <a href="{{ route('alerts.index') }}" class="btn btn-sm btn-outline-secondary" title="Đặt lại bộ lọc">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- DANH SÁCH CẢNH BÁO -->
<div class="content-card">
    <div class="content-card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark">
            <i class="bi bi-list-stars text-primary me-2"></i>DANH SÁCH CẢNH BÁO AN TOÀN ({{ $alerts->total() }})
        </span>
        <span class="text-muted small">
            Nhấn vào "Xử lý ngay" để dẫn trực tiếp đến vị trí thuốc trong tủ hoặc lịch uống.
        </span>
    </div>

    <div class="content-card-body p-0">
        @if ($alerts->count() > 0)
            <div class="list-group list-group-flush">
                @foreach ($alerts as $alert)
                    @php
                        $borderClass = match($alert->level) {
                            'danger' => 'border-danger',
                            'warning' => 'border-warning',
                            'info' => 'border-info',
                            default => 'border-secondary',
                        };
                        $bgClass = $alert->is_read ? 'bg-white' : 'bg-light';
                    @endphp
                    <div class="list-group-item p-3 border-start border-4 {{ $borderClass }} {{ $bgClass }} transition-hover">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <!-- NỘI DUNG CẢNH BÁO -->
                            <div class="d-flex align-items-start gap-3 flex-grow-1">
                                <!-- ICON THEO LOẠI -->
                                <div class="mt-1">
                                    <i class="bi {{ $alert->type_icon }} fs-3"></i>
                                </div>

                                <div class="flex-grow-1">
                                    <!-- BADGES & TIÊU ĐỀ -->
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <span class="{{ $alert->level_subtle_badge_class }}">
                                            {{ $alert->level_label }}
                                        </span>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                            {{ $alert->type_label }}
                                        </span>

                                        @if (! $alert->is_read)
                                            <span class="badge bg-primary text-white" style="font-size: 10px;">
                                                MỚI
                                            </span>
                                        @endif

                                        <span class="text-muted small ms-auto" style="font-size: 11px;">
                                            <i class="bi bi-clock me-1"></i>{{ $alert->created_at ? $alert->created_at->diffForHumans() : '' }}
                                            ({{ $alert->created_at ? $alert->created_at->format('d/m/Y H:i') : '' }})
                                        </span>
                                    </div>

                                    <!-- TIÊU ĐỀ -->
                                    <h6 class="fw-bold mb-1 {{ $alert->level === 'danger' ? 'text-danger' : 'text-dark' }}">
                                        {{ $alert->title }}
                                    </h6>

                                    <!-- NỘI DUNG CHI TIẾT -->
                                    <div class="text-secondary small mb-2" style="line-height: 1.5;">
                                        {{ $alert->content }}
                                    </div>

                                    <!-- ACTIONS -->
                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                        @if ($alert->action_url)
                                            <a href="{{ $alert->action_url }}" class="btn btn-xs btn-outline-primary py-1 px-2 d-inline-flex align-items-center gap-1 shadow-sm">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                                <span>Xử lý ngay</span>
                                            </a>
                                        @endif

                                        @if (! $alert->is_read)
                                            <form method="POST" action="{{ route('alerts.read', $alert) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-xs btn-outline-success py-1 px-2 d-inline-flex align-items-center gap-1" title="Đánh dấu đã đọc">
                                                    <i class="bi bi-check-lg"></i>
                                                    <span>Đã xem</span>
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('alerts.destroy', $alert) }}" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa cảnh báo này không?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-danger py-1 px-2 d-inline-flex align-items-center gap-1" title="Xóa cảnh báo">
                                                <i class="bi bi-trash"></i>
                                                <span>Xóa</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- PHÂN TRANG -->
            @if ($alerts->hasPages())
                <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="text-muted small">
                        Hiển thị {{ $alerts->firstItem() }} - {{ $alerts->lastItem() }} trên tổng số {{ $alerts->total() }} cảnh báo
                    </span>
                    <div>
                        {{ $alerts->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            @endif
        @else
            <!-- TRẠNG THÁI KHÔNG CÓ CẢNH BÁO NÀO (AN TOÀN HOÀN TOÀN) -->
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-shield-check text-success" style="font-size: 4rem;"></i>
                </div>
                <h5 class="fw-bold text-dark">Tủ thuốc gia đình đang ở trạng thái an toàn tuyệt đối!</h5>
                <p class="text-muted small mb-3" style="max-width: 500px; margin: 0 auto;">
                    Không phát hiện thuốc nào hết hạn sử dụng, thuốc cận date hay nguy cơ quá liều hoạt chất. 
                    Bạn có thể nhấn nút quét để kiểm tra lại bất cứ lúc nào.
                </p>
                <form method="POST" action="{{ route('alerts.scan') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-erp-primary shadow-sm">
                        <i class="bi bi-arrow-repeat me-1"></i>Quét kiểm tra tủ thuốc ngay
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
