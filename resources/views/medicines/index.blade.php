@extends('layouts.app')

@section('title', 'Tra cứu Danh mục Thuốc chuẩn - MedCabinet')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-capsule-pill text-primary fs-4"></i>
            Tra cứu Danh mục Thuốc chuẩn
        </h5>
        <div class="text-muted small">
            Dữ liệu dược thư chuẩn hóa hơn 25+ loại thuốc thông dụng tại Việt Nam: hoạt chất, hàm lượng, chỉ định, chống chỉ định và tác dụng phụ.
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('cabinet.index') }}" class="btn btn-sm btn-outline-secondary shadow-sm">
            <i class="bi bi-box-seam me-1"></i>Xem tủ thuốc gia đình
        </a>
        <a href="{{ route('cabinet.items.create') }}" class="btn btn-erp-primary d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-circle"></i>
            <span>Thêm thuốc mới vào tủ</span>
        </a>
    </div>
</div>

<!-- CATEGORY PILLS BAR -->
<div class="card mb-3 border shadow-sm">
    <div class="card-body p-2 bg-white">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="fw-semibold small text-muted me-1"><i class="bi bi-funnel me-1"></i>Nhóm thuốc:</span>
            <a href="{{ route('medicines.index', request()->except('category_id', 'page')) }}" 
               class="badge rounded-pill text-decoration-none px-3 py-2 {{ empty($categoryId) ? 'bg-primary text-white' : 'bg-light text-dark border' }}">
                Tất cả ({{ $totalMedicinesCount }})
            </a>
            @foreach ($categories as $cat)
                <a href="{{ route('medicines.index', array_merge(request()->except('page'), ['category_id' => $cat->id])) }}" 
                   class="badge rounded-pill text-decoration-none px-3 py-2 {{ (string)$categoryId === (string)$cat->id ? 'bg-primary text-white' : 'bg-light text-dark border' }}">
                    {{ $cat->name }} ({{ $cat->medicines_count }})
                </a>
            @endforeach
        </div>
    </div>
</div>

<!-- MAIN SEARCH & FILTER FORM -->
<div class="row g-3 mb-3">
    <div class="col-md-8">
        <form method="GET" action="{{ route('medicines.index') }}" class="d-flex gap-2">
            @if ($categoryId)
                <input type="hidden" name="category_id" value="{{ $categoryId }}">
            @endif
            @if ($dosageForm)
                <input type="hidden" name="dosage_form" value="{{ $dosageForm }}">
            @endif
            <div class="input-group input-group-sm shadow-sm flex-grow-1">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="q" class="form-control border-start-0" 
                       placeholder="Tìm kiếm theo tên thuốc (Panadol, Hapacol...), hoạt chất (Paracetamol), công dụng, nhà sản xuất..." 
                       value="{{ $search }}">
                @if ($search)
                    <a href="{{ route('medicines.index', request()->except('q', 'page')) }}" class="btn btn-outline-secondary" title="Xóa tìm kiếm">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
                <button class="btn btn-erp-primary" type="submit">Tìm kiếm</button>
            </div>
        </form>
    </div>
    <div class="col-md-4">
        <form method="GET" action="{{ route('medicines.index') }}" class="d-flex align-items-center justify-content-md-end gap-2">
            @if ($categoryId)
                <input type="hidden" name="category_id" value="{{ $categoryId }}">
            @endif
            @if ($search)
                <input type="hidden" name="q" value="{{ $search }}">
            @endif
            <label class="small text-muted text-nowrap fw-semibold">Dạng bào chế:</label>
            <select name="dosage_form" class="form-select form-select-sm shadow-sm" style="max-width: 200px;" onchange="this.form.submit()">
                <option value="">-- Tất cả dạng --</option>
                @foreach ($dosageForms as $df)
                    <option value="{{ $df }}" {{ $dosageForm === $df ? 'selected' : '' }}>{{ $df }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>

<!-- MEDICINES TABLE / LIST -->
<div class="content-card">
    <div class="content-card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold text-dark"><i class="bi bi-journal-text text-primary me-1"></i>Danh sách thuốc chuẩn</span>
            <span class="badge bg-secondary-subtle text-secondary small">{{ $medicines->total() }} kết quả</span>
        </div>
        <div class="text-muted small">
            Trang {{ $medicines->currentPage() }} / {{ max(1, $medicines->lastPage()) }}
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-erp align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">STT</th>
                    <th style="min-width: 220px;">Tên thuốc & Mã</th>
                    <th style="min-width: 200px;">Hoạt chất & Hàm lượng</th>
                    <th style="min-width: 160px;">Nhóm dược lý</th>
                    <th style="min-width: 140px;">Dạng & Đơn vị</th>
                    <th style="min-width: 160px;">Nhà sản xuất</th>
                    <th style="min-width: 240px;">Hướng dẫn & Lưu ý</th>
                    <th style="width: 140px;" class="text-center">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($medicines as $index => $medicine)
                    @php
                        $inCabinet = in_array($medicine->id, $existingCabinetMedicineIds);
                    @endphp
                    <tr>
                        <td class="text-center text-muted small">
                            {{ ($medicines->currentPage() - 1) * $medicines->perPage() + $index + 1 }}
                        </td>
                        <td>
                            <div class="d-flex align-items-start gap-2">
                                <div class="bg-primary-subtle text-primary p-2 rounded text-center" style="width: 36px; height: 36px;">
                                    <i class="bi bi-capsule fs-6"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark fs-6">{{ $medicine->name }}</div>
                                    <div class="d-flex align-items-center gap-1 mt-1">
                                        @if ($medicine->code)
                                            <span class="badge bg-light text-muted border font-monospace" style="font-size: 10px;">{{ $medicine->code }}</span>
                                        @endif
                                        @if ($inCabinet)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px;">
                                                <i class="bi bi-check-circle me-1"></i>Có trong tủ
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold text-primary-emphasis">{{ $medicine->active_ingredient }}</div>
                            @if ($medicine->concentration)
                                <div class="text-muted small">Hàm lượng: <strong>{{ $medicine->concentration }}</strong></div>
                            @endif
                        </td>
                        <td>
                            @if ($medicine->category)
                                <span class="badge bg-info-subtle text-info border border-info-subtle text-wrap text-start" style="font-size: 11px;">
                                    {{ $medicine->category->name }}
                                </span>
                            @else
                                <span class="text-muted small">Chưa phân nhóm</span>
                            @endif
                        </td>
                        <td>
                            <div class="small fw-semibold">{{ $medicine->dosage_form ?: 'Viên nén' }}</div>
                            <div class="text-muted small">Đơn vị: <strong>{{ $medicine->default_unit ?: 'viên' }}</strong></div>
                        </td>
                        <td>
                            <div class="small text-truncate" style="max-width: 160px;" title="{{ $medicine->manufacturer }}">
                                {{ $medicine->manufacturer ?: 'Đang cập nhật' }}
                            </div>
                        </td>
                        <td>
                            @if ($medicine->usage_instructions)
                                <div class="small text-dark mb-1" style="font-size: 11.5px; line-height: 1.4;">
                                    <i class="bi bi-info-circle text-primary me-1"></i>{{ Str::limit($medicine->usage_instructions, 90) }}
                                </div>
                            @endif
                            @if ($medicine->contraindications)
                                <div class="small text-danger" style="font-size: 11px;">
                                    <i class="bi bi-exclamation-triangle me-1"></i><strong>Chống CĐ:</strong> {{ Str::limit($medicine->contraindications, 60) }}
                                </div>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-info" 
                                        onclick="showMedicineDetail({{ json_encode($medicine) }})" 
                                        title="Xem chi tiết dược thư">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button type="button" class="btn btn-erp-primary" 
                                        onclick="openQuickAddToCabinetModal({{ json_encode($medicine) }})" 
                                        title="Thêm nhanh vào tủ thuốc gia đình">
                                    <i class="bi bi-plus-lg"></i> Thêm
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="bi bi-search text-muted fs-1 mb-2 d-block"></i>
                            <div class="fw-semibold text-muted">Không tìm thấy loại thuốc nào phù hợp với bộ lọc hiện tại.</div>
                            <div class="small text-muted mt-1">Hãy thử tìm theo tên thuốc, hoạt chất hoặc chọn nhóm thuốc khác.</div>
                            <a href="{{ route('medicines.index') }}" class="btn btn-sm btn-outline-primary mt-3">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Xóa bộ lọc
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    @if ($medicines->hasPages())
        <div class="card-footer bg-white border-top p-2 d-flex justify-content-between align-items-center">
            <div class="small text-muted">
                Hiển thị từ {{ $medicines->firstItem() }} đến {{ $medicines->lastItem() }} trong tổng số {{ $medicines->total() }} thuốc
            </div>
            <div>
                {{ $medicines->links('pagination::bootstrap-5') }}
            </div>
        </div>
    @endif
</div>

<!-- MODAL: CHI TIẾT THUỐC DƯỢC THƯ -->
<div class="modal fade" id="medicineDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold text-primary d-flex align-items-center gap-2">
                    <i class="bi bi-journal-medical fs-5"></i>
                    <span>Thông tin Dược thư chi tiết</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex align-items-center gap-3 p-3 bg-light rounded mb-3 border">
                    <div class="bg-primary text-white rounded p-3 text-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-capsule fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0" id="detailMedName">Tên thuốc</h5>
                        <div class="text-muted small mt-1">
                            Mã: <span class="badge bg-secondary font-monospace" id="detailMedCode">CODE</span> | 
                            Nhóm: <span class="badge bg-info text-dark" id="detailMedCategory">Nhóm</span>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-2 bg-white h-100">
                            <div class="fw-bold text-secondary small mb-1"><i class="bi bi-card-text me-1"></i>Thành phần hoạt chất & Hàm lượng</div>
                            <div class="fs-6 fw-semibold text-primary" id="detailMedActive">Hoạt chất</div>
                            <div class="text-muted small mt-1">Hàm lượng: <strong id="detailMedConcentration">--</strong></div>
                            <div class="text-muted small">Dạng bào chế: <strong id="detailMedDosageForm">--</strong> | Đơn vị: <strong id="detailMedUnit">--</strong></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-2 bg-white h-100">
                            <div class="fw-bold text-secondary small mb-1"><i class="bi bi-buildings me-1"></i>Nhà sản xuất & Xuất xứ</div>
                            <div class="fs-6 fw-semibold text-dark" id="detailMedManufacturer">Hãng dược</div>
                            <div class="text-muted small mt-1">Tiêu chuẩn: Dược điển Việt Nam / Quốc tế</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded p-3 bg-white">
                            <div class="fw-bold text-primary small mb-1"><i class="bi bi-clipboard2-pulse me-1"></i>Chỉ định & Hướng dẫn sử dụng</div>
                            <div class="text-dark small" style="line-height: 1.6;" id="detailMedInstructions">--</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 bg-warning-subtle border-warning-subtle h-100">
                            <div class="fw-bold text-warning-emphasis small mb-1"><i class="bi bi-exclamation-circle me-1"></i>Tác dụng phụ thường gặp</div>
                            <div class="text-dark small" id="detailMedSideEffects">--</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 bg-danger-subtle border-danger-subtle h-100">
                            <div class="fw-bold text-danger small mb-1"><i class="bi bi-shield-slash me-1"></i>Chống chỉ định & Cảnh báo an toàn</div>
                            <div class="text-dark small" id="detailMedContraindications">--</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-sm btn-erp-primary" id="btnDetailAddCabinet">
                    <i class="bi bi-plus-circle me-1"></i>Thêm vào tủ thuốc gia đình
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: THÊM NHANH VÀO TỦ THUỐC GIA ĐÌNH -->
<div class="modal fade" id="quickAddCabinetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form id="quickAddForm" method="POST" action="">
                @csrf
                <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title fw-bold d-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-in-down"></i>
                        <span>Thêm vào Tủ thuốc Gia đình</span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        Đang thêm: <strong id="quickAddMedName">Tên thuốc</strong> (<span id="quickAddMedActive">Hoạt chất</span>)
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-7">
                            <label class="form-label small fw-semibold">Số lượng thực nhập <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" min="0.5" name="quantity" class="form-control form-control-sm" value="10" required>
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-semibold">Đơn vị</label>
                            <input type="text" name="unit" id="quickAddUnit" class="form-control form-control-sm" value="viên" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Hạn sử dụng (EXP) <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" class="form-control form-control-sm" 
                                   value="{{ \Carbon\Carbon::today()->addYears(2)->toDateString() }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Ngày sản xuất (NSX)</label>
                            <input type="date" name="manufacture_date" class="form-control form-control-sm" 
                                   value="{{ \Carbon\Carbon::today()->subMonths(3)->toDateString() }}">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Vị trí ngăn tủ</label>
                            <input type="text" name="storage_location" class="form-control form-control-sm" placeholder="VD: Ngăn 1, Tủ phòng khách" value="Ngăn chính">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Số lô sản xuất</label>
                            <input type="text" name="batch_number" class="form-control form-control-sm" placeholder="VD: LOT-2026-A1">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Đơn giá ước tính (VNĐ/đơn vị)</label>
                        <input type="number" step="100" min="0" name="price_per_unit" class="form-control form-control-sm" placeholder="VD: 5000">
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-sm btn-erp-primary">
                        <i class="bi bi-check2-circle me-1"></i>Xác nhận thêm vào tủ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let currentSelectedMedicine = null;

    function showMedicineDetail(med) {
        currentSelectedMedicine = med;
        document.getElementById('detailMedName').innerText = med.name;
        document.getElementById('detailMedCode').innerText = med.code || 'N/A';
        document.getElementById('detailMedCategory').innerText = med.category ? med.category.name : 'Chưa phân nhóm';
        document.getElementById('detailMedActive').innerText = med.active_ingredient || 'Đang cập nhật';
        document.getElementById('detailMedConcentration').innerText = med.concentration || 'Tiêu chuẩn';
        document.getElementById('detailMedDosageForm').innerText = med.dosage_form || 'Viên nén';
        document.getElementById('detailMedUnit').innerText = med.default_unit || 'viên';
        document.getElementById('detailMedManufacturer').innerText = med.manufacturer || 'Đang cập nhật';
        document.getElementById('detailMedInstructions').innerText = med.usage_instructions || 'Chưa có chỉ dẫn cụ thể.';
        document.getElementById('detailMedSideEffects').innerText = med.side_effects || 'Hiếm gặp tác dụng phụ nghiêm trọng khi dùng đúng liều.';
        document.getElementById('detailMedContraindications').innerText = med.contraindications || 'Không có chống chỉ định đặc biệt ngoài mẫn cảm với thành phần.';

        document.getElementById('btnDetailAddCabinet').onclick = function() {
            const detailModal = bootstrap.Modal.getInstance(document.getElementById('medicineDetailModal'));
            if (detailModal) detailModal.hide();
            openQuickAddToCabinetModal(med);
        };

        const modal = new bootstrap.Modal(document.getElementById('medicineDetailModal'));
        modal.show();
    }

    function openQuickAddToCabinetModal(med) {
        currentSelectedMedicine = med;
        document.getElementById('quickAddMedName').innerText = med.name;
        document.getElementById('quickAddMedActive').innerText = med.active_ingredient || '';
        document.getElementById('quickAddUnit').value = med.default_unit || 'viên';

        const form = document.getElementById('quickAddForm');
        form.action = "{{ url('/medicines') }}/" + med.id + "/add-to-cabinet";

        const modal = new bootstrap.Modal(document.getElementById('quickAddCabinetModal'));
        modal.show();
    }
</script>
@endpush
@endsection
