@extends('layouts.app')

@section('title', $editing ? 'Cập nhật thuốc - Tủ thuốc gia đình' : 'Thêm thuốc vào tủ - Tủ thuốc gia đình')

@section('content')
<!-- Header và Điều hướng -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('cabinet.index') }}" class="text-decoration-none text-muted small d-inline-flex align-items-center mb-1">
            <i class="bi bi-arrow-left me-1"></i> Quay lại danh sách tủ thuốc
        </a>
        <h1 class="h4 fw-bold text-dark mb-0">
            <i class="bi {{ $editing ? 'bi-pencil-square text-primary' : 'bi-plus-circle text-success' }} me-1"></i>
            {{ $editing ? 'Cập nhật lô thuốc: ' . $item->display_name : 'Thêm thuốc mới vào tủ thuốc' }}
        </h1>
        <div class="text-muted small">
            Quản lý chi tiết từng lô thuốc, kiểm soát chặt chẽ hạn sử dụng và số lượng tồn kho an toàn cho cả gia đình.
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- CỘT TRÁI: FORM NHẬP LIỆU CHUẨN ERP Y TẾ (8 CỘT) -->
    <div class="col-12 col-lg-8">
        <form method="POST" action="{{ $editing ? route('cabinet.items.update', $item) : route('cabinet.items.store') }}" id="cabinetForm">
            @csrf
            @if ($editing) @method('PUT') @endif

            <!-- 1. ĐỊNH DANH THUỐC & DƯỢC THƯ -->
            <div class="content-card mb-3">
                <div class="content-card-header bg-light d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-capsule text-primary me-2"></i>1. Định danh thuốc & Dược thư
                    </span>
                    <span class="badge bg-primary-subtle text-primary">Thông tin cơ bản</span>
                </div>
                <div class="content-card-body p-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="medicine_id" class="form-label fw-semibold text-dark mb-1">
                                Chọn thuốc có sẵn từ danh mục dược phẩm <span class="text-muted fw-normal">(Khuyên dùng)</span>
                            </label>
                            <select id="medicine_id" name="medicine_id" class="form-select @error('medicine_id') is-invalid @enderror">
                                <option value="">-- Thuốc tự nhập / Không có trong danh mục có sẵn --</option>
                                @foreach ($medicines as $med)
                                    <option value="{{ $med->id }}"
                                            data-name="{{ $med->name }}"
                                            data-ingredient="{{ $med->active_ingredient }}"
                                            data-concentration="{{ $med->concentration }}"
                                            data-unit="{{ $med->default_unit }}"
                                            data-category="{{ $med->category?->name ?? 'Dược phẩm dùng ngoài' }}"
                                            @selected((string) old('medicine_id', $item->medicine_id) === (string) $med->id)>
                                        {{ $med->name }} @if($med->concentration) — {{ $med->concentration }} @endif · [{{ $med->active_ingredient }}]
                                    </option>
                                @endforeach
                            </select>
                            @error('medicine_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text text-muted" style="font-size: 11.5px;">
                                <i class="bi bi-lightning-charge text-warning me-1"></i>Khi chọn thuốc có sẵn, hệ thống sẽ tự động điền Tên thuốc, Hoạt chất, Đơn vị tính và liên kết cảnh báo dị ứng.
                            </div>
                        </div>

                        <div class="col-12 col-md-8">
                            <label for="custom_name" class="form-label fw-semibold text-dark mb-1">
                                Tên thuốc trên bao bì <span class="text-danger">*</span>
                            </label>
                            <input id="custom_name" name="custom_name" required maxlength="255"
                                   value="{{ old('custom_name', $item->custom_name) }}"
                                   class="form-control @error('custom_name') is-invalid @enderror"
                                   placeholder="Ví dụ: Panadol Extra Đỏ Giảm Đau">
                            @error('custom_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="batch_number" class="form-label fw-semibold text-dark mb-1">Số lô sản xuất</label>
                            <input id="batch_number" name="batch_number" maxlength="255"
                                   value="{{ old('batch_number', $item->batch_number) }}"
                                   class="form-control @error('batch_number') is-invalid @enderror"
                                   placeholder="Ví dụ: LOT-2026-08">
                            @error('batch_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. HẠN SỬ DỤNG & VỊ TRÍ BẢO QUẢN -->
            <div class="content-card mb-3">
                <div class="content-card-header bg-light d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-calendar-event text-danger me-2"></i>2. Hạn sử dụng & Vị trí bảo quản
                    </span>
                    <span class="badge bg-danger-subtle text-danger">An toàn sử dụng</span>
                </div>
                <div class="content-card-body p-3">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="manufacturing_date" class="form-label fw-semibold text-dark mb-1">Ngày sản xuất</label>
                            <input id="manufacturing_date" name="manufacturing_date" type="date" max="{{ today()->format('Y-m-d') }}"
                                   value="{{ old('manufacturing_date', $item->manufacturing_date?->format('Y-m-d')) }}"
                                   class="form-control @error('manufacturing_date') is-invalid @enderror">
                            @error('manufacturing_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="expiry_date" class="form-label fw-semibold text-dark mb-1">
                                Hạn sử dụng <span class="text-danger">*</span>
                            </label>
                            <input id="expiry_date" name="expiry_date" type="date" required
                                   value="{{ old('expiry_date', $item->expiry_date?->format('Y-m-d')) }}"
                                   class="form-control @error('expiry_date') is-invalid @enderror">
                            @error('expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div id="expiryNotice" class="small mt-1 fw-semibold"></div>
                        </div>

                        <div class="col-12">
                            <label for="storage_location" class="form-label fw-semibold text-dark mb-1">
                                Vị trí cất giữ trong nhà
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-geo-alt text-primary"></i></span>
                                <input id="storage_location" name="storage_location" maxlength="255"
                                       value="{{ old('storage_location', $item->storage_location) }}"
                                       class="form-control @error('storage_location') is-invalid @enderror"
                                       placeholder="Ví dụ: Ngăn 1 - Tủ thuốc phòng khách">
                            </div>
                            @error('storage_location')<div class="invalid-feedback">{{ $message }}</div>@enderror

                            <!-- Gợi ý vị trí nhanh -->
                            <div class="d-flex flex-wrap gap-1 align-items-center mt-2">
                                <span class="text-muted small me-1">Gợi ý nhanh:</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-loc-suggestion" style="font-size: 11.5px;">Ngăn 1 - Tủ phòng khách</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-loc-suggestion" style="font-size: 11.5px;">Ngăn 2 - Tủ phòng khách</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-loc-suggestion" style="font-size: 11.5px;">Ngăn mát tủ lạnh</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-loc-suggestion" style="font-size: 11.5px;">Kệ nhà tắm tầng 2</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-loc-suggestion" style="font-size: 11.5px;">Tủ đầu giường phòng ngủ</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-loc-suggestion" style="font-size: 11.5px;">Hộp sơ cứu mini</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. TỒN KHO & ĐƠN GIÁ MUA -->
            <div class="content-card mb-3">
                <div class="content-card-header bg-light d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-box-seam text-success me-2"></i>3. Số lượng tồn kho & Đơn giá
                    </span>
                    <span class="badge bg-success-subtle text-success">Kiểm kê</span>
                </div>
                <div class="content-card-body p-3">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="quantity" class="form-label fw-semibold text-dark mb-1">
                                Số lượng tồn kho <span class="text-danger">*</span>
                            </label>
                            <input id="quantity" name="quantity" type="number" min="0" max="999999.99" step="0.01" required
                                   value="{{ old('quantity', $item->exists ? rtrim(rtrim((string) $item->quantity, '0'), '.') : '1') }}"
                                   class="form-control @error('quantity') is-invalid @enderror">
                            @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div id="stockNotice" class="small mt-1"></div>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="unit" class="form-label fw-semibold text-dark mb-1">
                                Đơn vị tính <span class="text-danger">*</span>
                            </label>
                            <input id="unit" name="unit" maxlength="30" required
                                   value="{{ old('unit', $item->unit ?: 'viên') }}"
                                   class="form-control @error('unit') is-invalid @enderror"
                                   placeholder="viên, hộp, vỉ, chai...">
                            @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror

                            <!-- Nút chọn đơn vị nhanh -->
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                @foreach (['viên', 'vỉ', 'hộp', 'chai', 'tuýp', 'gói', 'ống'] as $u)
                                    <button type="button" class="btn btn-sm btn-light border py-0 px-2 btn-unit-suggestion" style="font-size: 11px;">{{ $u }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="price_per_unit" class="form-label fw-semibold text-dark mb-1">Đơn giá / đơn vị (VNĐ)</label>
                            <input id="price_per_unit" name="price_per_unit" type="number" min="0" max="999999999.99" step="100"
                                   value="{{ old('price_per_unit', $item->price_per_unit ? rtrim(rtrim((string)$item->price_per_unit, '0'), '.') : '') }}"
                                   class="form-control @error('price_per_unit') is-invalid @enderror"
                                   placeholder="Ví dụ: 3500">
                            @error('price_per_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="text-muted small mt-1" id="totalPriceCalc" style="font-size: 11.5px;">
                                Thành tiền lô: <strong>0 đ</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. GHI CHÚ BẢO QUẢN & HƯỚNG DẪN -->
            <div class="content-card mb-3">
                <div class="content-card-header bg-light">
                    <span class="fw-bold text-dark"><i class="bi bi-card-text text-secondary me-2"></i>4. Ghi chú bảo quản & Dặn dò sử dụng</span>
                </div>
                <div class="content-card-body p-3">
                    <textarea id="notes" name="notes" rows="3" maxlength="5000"
                              class="form-control @error('notes') is-invalid @enderror"
                              placeholder="Ví dụ: Uống sau bữa ăn sáng khi đau rát thượng vị. Bảo quản nhiệt độ mát...">{!! old('notes', $item->notes) !!}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <!-- Thanh nút bấm hành động -->
            <div class="content-card">
                <div class="content-card-body p-3 d-flex justify-content-between align-items-center">
                    <a href="{{ route('cabinet.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i>Hủy bỏ
                    </a>
                    <button type="submit" class="btn btn-erp-primary px-4">
                        <i class="bi bi-check2-circle me-1"></i>{{ $editing ? 'Lưu thay đổi thuốc' : 'Lưu vào tủ thuốc gia đình' }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- CỘT PHẢI: LIVE PREVIEW NHÃN THUỐC & CẨM NĂNG BẢO QUẢN (4 CỘT) -->
    <div class="col-12 col-lg-4">
        <!-- CARD LIVE PREVIEW NHÃN THUỐC (MÔ PHỎNG TEM THUỐC BỆNH VIỆN) -->
        <div class="content-card mb-3 sticky-top" style="top: 15px; z-index: 10;">
            <div class="content-card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold small"><i class="bi bi-tag-fill me-1"></i>XEM TRƯỚC NHÃN THUỐC Y TẾ</span>
                <span class="badge bg-white text-primary" style="font-size: 10px;">Live Preview</span>
            </div>
            <div class="content-card-body p-3">
                <div class="border rounded p-3 bg-light shadow-sm position-relative">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary-subtle text-primary" id="previewCategory">Dược phẩm dùng chung</span>
                        <span class="small text-muted" id="previewBatch">Lô: —</span>
                    </div>

                    <h5 class="fw-bold text-dark mb-1" id="previewName">Tên thuốc chưa nhập</h5>
                    <div class="text-muted small mb-2" id="previewIngredient" style="font-size: 12px;">
                        Hoạt chất: Chưa xác định
                    </div>

                    <hr class="my-2">

                    <div class="row g-2 small">
                        <div class="col-6">
                            <span class="text-muted d-block" style="font-size: 11px;">Hạn sử dụng:</span>
                            <strong class="text-dark" id="previewExpiry">—</strong>
                            <div id="previewExpiryBadge" class="mt-1"></div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block" style="font-size: 11px;">Số lượng trong tủ:</span>
                            <strong class="fs-6 text-dark" id="previewStock">1 viên</strong>
                            <div id="previewStockBadge" class="mt-1"></div>
                        </div>
                        <div class="col-12">
                            <span class="text-muted d-block" style="font-size: 11px;">Vị trí cất:</span>
                            <span class="text-dark" id="previewLocation"><i class="bi bi-geo-alt text-primary me-1"></i>Ngăn tủ chính</span>
                        </div>
                    </div>

                    <div class="mt-2 pt-2 border-top">
                        <div class="text-muted small" style="font-size: 11px;">Dặn dò:</div>
                        <div class="text-secondary small fst-italic text-truncate" id="previewNotes">Không có ghi chú thêm.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CẨM NĂNG AN TOÀN TỦ THUỐC GIA ĐÌNH -->
        <div class="content-card">
            <div class="content-card-header bg-warning bg-opacity-10 py-2 border-bottom border-warning-subtle">
                <span class="fw-bold text-dark small"><i class="bi bi-shield-exclamation text-warning me-1"></i>3 LƯU Ý VÀNG KHI BẢO QUẢN</span>
            </div>
            <div class="content-card-body p-3 small text-muted">
                <div class="d-flex gap-2 mb-2">
                    <i class="bi bi-thermometer-half text-danger fs-5"></i>
                    <div>
                        <strong class="text-dark">Nhiệt độ & Độ ẩm:</strong>
                        <div>Bảo quản nơi khô ráo, nhiệt độ dưới 30°C. Thuốc đặc thù như men vi sinh, siro mở nắp cần để ngăn mát (2-8°C).</div>
                    </div>
                </div>
                <div class="d-flex gap-2 mb-2">
                    <i class="bi bi-emoji-smile-upside-down text-warning fs-5"></i>
                    <div>
                        <strong class="text-dark">Tránh xa trẻ nhỏ:</strong>
                        <div>Đặt tủ thuốc ở độ cao tối thiểu 1.5m ngoài tầm với của trẻ nhỏ để tránh nuốt nhầm thuốc.</div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <i class="bi bi-trash text-secondary fs-5"></i>
                    <div>
                        <strong class="text-dark">Hủy thuốc quá hạn:</strong>
                        <div>Tuyệt đối không uống thuốc đã đổi màu, chảy nước hoặc quá hạn in trên bao bì.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const nameInput = document.getElementById('custom_name');
    const medSelect = document.getElementById('medicine_id');
    const batchInput = document.getElementById('batch_number');
    const expiryInput = document.getElementById('expiry_date');
    const qtyInput = document.getElementById('quantity');
    const unitInput = document.getElementById('unit');
    const priceInput = document.getElementById('price_per_unit');
    const locInput = document.getElementById('storage_location');
    const notesInput = document.getElementById('notes');

    // Live preview elements
    const pName = document.getElementById('previewName');
    const pCategory = document.getElementById('previewCategory');
    const pBatch = document.getElementById('previewBatch');
    const pIngredient = document.getElementById('previewIngredient');
    const pExpiry = document.getElementById('previewExpiry');
    const pExpiryBadge = document.getElementById('previewExpiryBadge');
    const pStock = document.getElementById('previewStock');
    const pStockBadge = document.getElementById('previewStockBadge');
    const pLoc = document.getElementById('previewLocation');
    const pNotes = document.getElementById('previewNotes');
    const totalPriceCalc = document.getElementById('totalPriceCalc');
    const expiryNotice = document.getElementById('expiryNotice');

    function updatePreview() {
        // Tên thuốc
        const nameVal = nameInput.value.trim();
        pName.innerText = nameVal || 'Tên thuốc chưa nhập';

        // Số lô
        const batchVal = batchInput.value.trim();
        pBatch.innerText = batchVal ? 'Lô: ' + batchVal : 'Lô: —';

        // Vị trí
        const locVal = locInput.value.trim();
        pLoc.innerHTML = '<i class="bi bi-geo-alt text-primary me-1"></i>' + (locVal || 'Ngăn tủ chính');

        // Số lượng & Đơn vị
        const qtyVal = parseFloat(qtyInput.value) || 0;
        const unitVal = unitInput.value.trim() || 'viên';
        pStock.innerText = qtyVal + ' ' + unitVal;

        if (qtyVal <= 0) {
            pStockBadge.innerHTML = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Hết hàng</span>';
        } else if (qtyVal <= 5) {
            pStockBadge.innerHTML = '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Tồn ít (≤ 5)</span>';
        } else {
            pStockBadge.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle">Đủ dùng</span>';
        }

        // Hạn sử dụng
        const expVal = expiryInput.value;
        if (expVal) {
            const expDate = new Date(expVal);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const diffTime = expDate - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            pExpiry.innerText = expDate.toLocaleDateString('vi-VN');

            if (diffDays < 0) {
                pExpiryBadge.innerHTML = '<span class="badge bg-danger text-white">Đã quá hạn ' + Math.abs(diffDays) + ' ngày</span>';
                expiryNotice.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Cảnh báo: Ngày này đã quá hạn ' + Math.abs(diffDays) + ' ngày!</span>';
            } else if (diffDays <= 30) {
                pExpiryBadge.innerHTML = '<span class="badge bg-warning text-dark">Cận hạn: còn ' + diffDays + ' ngày</span>';
                expiryNotice.innerHTML = '<span class="text-warning-emphasis"><i class="bi bi-clock-history me-1"></i>Thuốc sắp hết hạn trong ' + diffDays + ' ngày tới.</span>';
            } else {
                let remText = '';
                if (diffDays < 365) {
                    remText = 'Còn ~' + Math.round(diffDays / 30.4) + ' tháng';
                } else {
                    remText = 'Còn ~' + (diffDays / 365.25).toFixed(1) + ' năm';
                }
                pExpiryBadge.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle">Còn hạn (' + remText + ')</span>';
                expiryNotice.innerHTML = '<span class="text-success"><i class="bi bi-shield-check me-1"></i>' + remText + ' (' + diffDays + ' ngày an toàn)</span>';
            }
        } else {
            pExpiry.innerText = '—';
            pExpiryBadge.innerHTML = '';
            expiryNotice.innerHTML = '';
        }

        // Thành tiền
        const priceVal = parseFloat(priceInput.value) || 0;
        const totalVal = qtyVal * priceVal;
        totalPriceCalc.innerHTML = 'Thành tiền lô: <strong class="text-primary">' + new Intl.NumberFormat('vi-VN').format(totalVal) + ' đ</strong>';

        // Ghi chú
        const notesVal = notesInput.value.trim();
        pNotes.innerText = notesVal || 'Không có ghi chú thêm.';
    }

    // Khi chọn thuốc từ Danh mục
    medSelect.addEventListener('change', function () {
        const option = this.selectedOptions[0];
        if (!this.value) {
            pCategory.innerText = 'Dược phẩm dùng chung';
            pIngredient.innerText = 'Hoạt chất: Chưa xác định';
            return;
        }

        const medName = option.dataset.name || '';
        const medUnit = option.dataset.unit || 'viên';
        const medIng = option.dataset.ingredient || '';
        const medConc = option.dataset.concentration || '';
        const medCat = option.dataset.category || 'Dược phẩm dùng chung';

        if (!nameInput.value.trim() || nameInput.dataset.autofilled === 'true') {
            nameInput.value = medName;
            nameInput.dataset.autofilled = 'true';
        }
        if (!unitInput.value.trim() || unitInput.dataset.autofilled === 'true') {
            unitInput.value = medUnit;
            unitInput.dataset.autofilled = 'true';
        }

        pCategory.innerText = medCat;
        pIngredient.innerHTML = '<strong>Hoạt chất:</strong> ' + medIng + (medConc ? ' · ' + medConc : '');
        updatePreview();
    });

    nameInput.addEventListener('input', function () { this.dataset.autofilled = 'false'; updatePreview(); });
    unitInput.addEventListener('input', function () { this.dataset.autofilled = 'false'; updatePreview(); });
    batchInput.addEventListener('input', updatePreview);
    expiryInput.addEventListener('change', updatePreview);
    qtyInput.addEventListener('input', updatePreview);
    priceInput.addEventListener('input', updatePreview);
    locInput.addEventListener('input', updatePreview);
    notesInput.addEventListener('input', updatePreview);

    // Gợi ý vị trí nhanh
    document.querySelectorAll('.btn-loc-suggestion').forEach(btn => {
        btn.addEventListener('click', function () {
            locInput.value = this.innerText;
            updatePreview();
        });
    });

    // Gợi ý đơn vị nhanh
    document.querySelectorAll('.btn-unit-suggestion').forEach(btn => {
        btn.addEventListener('click', function () {
            unitInput.value = this.innerText;
            unitInput.dataset.autofilled = 'false';
            updatePreview();
        });
    });

    // Chạy khởi tạo lúc load
    document.addEventListener('DOMContentLoaded', function () {
        if (medSelect.value) {
            const opt = medSelect.selectedOptions[0];
            pCategory.innerText = opt.dataset.category || 'Dược phẩm dùng chung';
            const ing = opt.dataset.ingredient || '';
            const conc = opt.dataset.concentration || '';
            pIngredient.innerHTML = ing ? '<strong>Hoạt chất:</strong> ' + ing + (conc ? ' · ' + conc : '') : 'Hoạt chất: Chưa xác định';
        }
        updatePreview();
    });
</script>
@endpush
