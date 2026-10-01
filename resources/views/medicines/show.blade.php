@extends('layouts.app')

@section('title', $medicine->name . ' - Chi tiết Dược thư Tra cứu')

@section('content')
<!-- BREADCRUMB & HEADER -->
<div class="mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1 small">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Tổng quan</a></li>
            <li class="breadcrumb-item"><a href="{{ route('medicines.index') }}" class="text-decoration-none">Tra cứu thuốc</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $medicine->name }}</li>
        </ol>
    </nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-capsule-pill text-primary fs-4"></i>
                <span>{{ $medicine->name }}</span>
                <span class="badge bg-secondary font-monospace fs-6">{{ $medicine->code }}</span>
            </h5>
            <div class="text-muted small mt-1">
                Nhóm: 
                @if ($medicine->category)
                    <span class="badge bg-info-subtle text-info border border-info-subtle">{{ $medicine->category->name }}</span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary">Chưa phân nhóm</span>
                @endif
                | Nhà sản xuất: <strong>{{ $medicine->manufacturer ?: 'Đang cập nhật' }}</strong>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('medicines.index') }}" class="btn btn-sm btn-outline-secondary shadow-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại danh mục
            </a>
            <button type="button" class="btn btn-erp-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#quickAddCabinetModal">
                <i class="bi bi-plus-circle me-1"></i>Thêm vào tủ thuốc gia đình
            </button>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- CỘT TRÁI: DƯỢC THƯ & THÔNG TIN Y KHOA -->
    <div class="col-lg-8">
        <!-- Card 1: Thành phần & Bào chế -->
        <div class="content-card mb-3">
            <div class="content-card-header bg-light">
                <span class="fw-bold text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Thông tin Hoạt chất và Bào chế</span>
            </div>
            <div class="content-card-body p-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-2 border rounded bg-light">
                            <span class="text-muted small d-block">Hoạt chất chính:</span>
                            <span class="fw-bold text-primary fs-6">{{ $medicine->active_ingredient ?: 'Đang cập nhật' }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border rounded bg-light">
                            <span class="text-muted small d-block">Hàm lượng / Nồng độ:</span>
                            <span class="fw-bold text-dark fs-6">{{ $medicine->concentration ?: '—' }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border rounded bg-light">
                            <span class="text-muted small d-block">Dạng bào chế:</span>
                            <span class="fw-semibold text-dark">{{ $medicine->dosage_form ?: 'Viên nén' }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 border rounded bg-light">
                            <span class="text-muted small d-block">Đơn vị chuẩn:</span>
                            <span class="fw-semibold text-dark">{{ $medicine->default_unit ?: 'viên' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Hướng dẫn sử dụng & Liều dùng -->
        <div class="content-card mb-3">
            <div class="content-card-header bg-light">
                <span class="fw-bold text-success"><i class="bi bi-journal-check text-success me-2"></i>Chỉ định & Liều lượng dùng chuẩn</span>
            </div>
            <div class="content-card-body p-3">
                @if ($medicine->usage_instructions)
                    <div class="p-3 bg-success-subtle text-success-emphasis border border-success-subtle rounded mb-0" style="line-height: 1.6;">
                        <i class="bi bi-check2-circle me-1"></i>{!! nl2br(e($medicine->usage_instructions)) !!}
                    </div>
                @else
                    <div class="text-muted small fst-italic">Chưa có thông tin hướng dẫn sử dụng chi tiết trong dược thư. Vui lòng tham khảo ý kiến bác sĩ hoặc dược sĩ.</div>
                @endif
            </div>
        </div>

        <!-- Card 3: Chống chỉ định & Cảnh báo an toàn -->
        <div class="content-card mb-3">
            <div class="content-card-header bg-light">
                <span class="fw-bold text-danger"><i class="bi bi-shield-slash text-danger me-2"></i>Chống chỉ định & Cảnh báo an toàn</span>
            </div>
            <div class="content-card-body p-3">
                @if ($medicine->contraindications)
                    <div class="p-3 bg-danger-subtle text-danger-emphasis border border-danger-subtle rounded mb-0" style="line-height: 1.6;">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i><strong>CẢNH BÁO QUAN TRỌNG:</strong> {!! nl2br(e($medicine->contraindications)) !!}
                    </div>
                @else
                    <div class="text-muted small">Không có ghi nhận chống chỉ định đặc biệt. Người có bệnh lý nền nặng cần tham vấn y tế.</div>
                @endif
            </div>
        </div>

        <!-- Card 4: Tác dụng phụ không mong muốn -->
        <div class="content-card">
            <div class="content-card-header bg-light">
                <span class="fw-bold text-warning-emphasis"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Tác dụng phụ có thể gặp</span>
            </div>
            <div class="content-card-body p-3">
                @if ($medicine->side_effects)
                    <div class="p-3 bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded mb-0" style="line-height: 1.6;">
                        <i class="bi bi-info-square me-1"></i>{!! nl2br(e($medicine->side_effects)) !!}
                    </div>
                @else
                    <div class="text-muted small">Chưa ghi nhận tác dụng phụ thường gặp ở liều điều trị thông thường.</div>
                @endif
            </div>
        </div>
    </div>

    <!-- CỘT PHẢI: TRẠNG THÁI TRONG TỦ THUỐC GIA ĐÌNH -->
    <div class="col-lg-4">
        <!-- Tình trạng tủ thuốc -->
        <div class="content-card mb-3">
            <div class="content-card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark"><i class="bi bi-box-seam text-primary me-2"></i>Tồn kho trong tủ gia đình</span>
                <span class="badge {{ $cabinetItems->isNotEmpty() ? 'bg-success' : 'bg-secondary' }}">
                    {{ $cabinetItems->isNotEmpty() ? 'Đang có trong tủ' : 'Chưa có' }}
                </span>
            </div>
            <div class="content-card-body p-3">
                @if ($cabinetItems->isNotEmpty())
                    <div class="alert alert-success py-2 px-3 mb-3 small d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <div>
                            Tủ thuốc gia đình đang lưu trữ <strong>{{ $cabinetItems->count() }} lô</strong> thuốc này với tổng số lượng: 
                            <strong>{{ $cabinetItems->sum('quantity') }} {{ $cabinetItems->first()->unit }}</strong>.
                        </div>
                    </div>

                    <div class="list-group list-group-flush border rounded">
                        @foreach ($cabinetItems as $item)
                            <div class="list-group-item p-2">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="fw-bold small">{{ $item->custom_name }}</span>
                                    <span class="badge {{ $item->status_badge_class }}" style="font-size: 10px;">{{ $item->status_label }}</span>
                                </div>
                                <div class="text-muted" style="font-size: 11px;">
                                    <div><i class="bi bi-tag me-1"></i>Số lô: <strong>{{ $item->batch_number ?: 'N/A' }}</strong></div>
                                    <div><i class="bi bi-calendar-event me-1"></i>HSD: <strong>{{ $item->expiry_date?->format('d/m/Y') }}</strong> ({{ $item->expiry_remaining_text }})</div>
                                    <div><i class="bi bi-box me-1"></i>Số lượng tồn: <strong>{{ $item->quantity }} {{ $item->unit }}</strong></div>
                                    <div><i class="bi bi-geo-alt me-1"></i>Vị trí: {{ $item->storage_location ?: 'Ngăn chính' }}</div>
                                </div>
                                <div class="mt-2 text-end">
                                    <a href="{{ route('cabinet.items.edit', $item) }}" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 11px;">
                                        <i class="bi bi-pencil me-1"></i>Chỉnh sửa lô này
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 mb-2 opacity-50 d-block"></i>
                        <div class="fw-semibold">Tủ thuốc chưa có loại thuốc này.</div>
                        <div class="small mt-1">Bạn có thể bấm nút bên dưới để bổ sung thuốc này vào tủ.</div>
                        <button type="button" class="btn btn-erp-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#quickAddCabinetModal">
                            <i class="bi bi-plus-circle me-1"></i>Thêm vào tủ thuốc ngay
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <!-- Lưu ý y khoa chung -->
        <div class="content-card">
            <div class="content-card-header bg-light">
                <span class="fw-bold text-dark"><i class="bi bi-lightbulb text-warning me-2"></i>Lưu ý Dược sĩ Gia đình</span>
            </div>
            <div class="content-card-body p-3 small text-muted">
                <ul class="ps-3 mb-0" style="line-height: 1.6;">
                    <li>Kiểm tra kỹ hạn sử dụng (EXP) in trên bao bì trước khi dùng.</li>
                    <li>Bảo quản thuốc ở nơi khô ráo, nhiệt độ dưới 30°C, tránh ánh nắng trực tiếp.</li>
                    <li>Để xa tầm tay trẻ em để tránh uống nhầm.</li>
                    <li>Nếu có phản ứng bất thường như nổi mề đay, khó thở, sưng môi mắt, hãy ngừng dùng ngay và tới cơ sở y tế gần nhất.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: THÊM NHANH VÀO TỦ THUỐC -->
<div class="modal fade" id="quickAddCabinetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form method="POST" action="{{ route('medicines.add-to-cabinet', $medicine) }}">
                @csrf
                <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title fw-bold d-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-in-down"></i>
                        <span>Thêm "{{ $medicine->name }}" vào Tủ thuốc</span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Số lượng nhập <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.5" min="0.5" name="quantity" class="form-control" value="10" required>
                            <input type="text" name="unit" class="form-control" value="{{ $medicine->default_unit ?: 'viên' }}" placeholder="Đơn vị tính">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Ngày sản xuất (NSX)</label>
                            <input type="date" name="manufacture_date" class="form-control form-control-sm" max="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Hạn sử dụng (HSD) <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" class="form-control form-control-sm" min="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Số lô sản xuất</label>
                            <input type="text" name="batch_number" class="form-control form-control-sm" placeholder="VD: L0826A">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Đơn giá (VNĐ)</label>
                            <input type="number" step="500" min="0" name="price_per_unit" class="form-control form-control-sm" placeholder="VD: 5000">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark">Vị trí trong tủ thuốc</label>
                        <input type="text" name="storage_location" class="form-control form-control-sm" placeholder="VD: Ngăn trên cùng, Hộp thuốc cảm..." value="Ngăn chính">
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-sm btn-erp-primary">
                        <i class="bi bi-check-lg me-1"></i>Xác nhận thêm vào tủ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
