@extends('layouts.app')

@section('title', 'Đề nghị nhập thuốc - Tủ thuốc gia đình')

@section('content')
@php
    $val = fn ($key, $default = '') => old($key, $prefill[$key] ?? $default);
    $units = ['viên', 'vỉ', 'hộp', 'gói', 'chai', 'ống', 'tuýp', 'lọ'];
    $locations = ['Ngăn 1 - Tủ phòng khách', 'Ngăn 2 - Tủ phòng khách', 'Ngăn mát tủ lạnh', 'Tủ đầu giường', 'Hộp sơ cứu'];
@endphp

<div class="mb-3">
    <a href="{{ route('cabinet.requests.index') }}" class="text-decoration-none text-muted small d-inline-flex align-items-center mb-1">
        <i class="bi bi-arrow-left me-1"></i> Quay lại danh sách đề nghị
    </a>
    <h1 class="h4 fw-bold text-dark mb-1"><i class="bi bi-bag-plus text-success me-1"></i>Thuốc tự mua ngoài đơn</h1>
    <div class="text-muted small">
        Dành cho thuốc gia đình <strong>tự mua không theo đơn</strong> (hạ sốt, vitamin, băng gạc...). Bạn chỉ cần <strong>chụp ảnh vỏ hộp</strong> và nhập vài thông tin in trên vỏ, Bác sĩ sẽ kiểm tra trước khi cho vào tủ.
    </div>
    <div class="alert alert-info py-2 px-3 small mt-2 mb-0">
        <i class="bi bi-file-earmark-medical me-1"></i>Mua thuốc <strong>theo đơn Bác sĩ đã kê</strong>? Không cần đề nghị — vào
        <a href="{{ route('cabinet.supply') }}" class="alert-link">Cần mua & bổ sung</a> và bấm <strong>"Đã mua"</strong> ở thuốc đó.
    </div>
</div>

@unless ($hasDoctor)
    <div class="alert alert-warning py-2 px-3 small d-flex gap-2">
        <i class="bi bi-person-exclamation fs-5"></i>
        <div>
            <strong>Gia đình chưa có Bác sĩ / Người chăm sóc.</strong>
            Đề nghị vẫn gửi được và sẽ do <strong>Quản trị viên hệ thống</strong> duyệt thay.
            @if (auth()->user()->isFamilyAdmin())
                Để duyệt nhanh hơn, hãy chỉ định một tài khoản trong nhà làm Bác sĩ tại
                <a href="{{ route('profile') }}" class="alert-link">Hồ sơ → Tài khoản truy cập hộ gia đình</a>.
            @endif
        </div>
    </div>
@endunless

<form method="POST" action="{{ route('cabinet.requests.store') }}" enctype="multipart/form-data" id="requestForm" class="row g-3">
    @csrf
    <input type="hidden" name="active_ingredient" id="active_ingredient" value="{{ $val('active_ingredient') }}">

    <div class="col-12 col-lg-7">
        <!-- BƯỚC 1: ẢNH VỎ HỘP -->
        <div class="content-card mb-3">
            <div class="content-card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark"><i class="bi bi-camera text-primary me-2"></i>Bước 1. Chụp ảnh vỏ hộp thuốc</span>
                <span class="badge bg-primary-subtle text-primary">Khuyên dùng</span>
            </div>
            <div class="content-card-body p-3">
                <div class="small text-muted mb-2">
                    Chụp <strong>mặt trước</strong> (thấy tên thuốc) và <strong>chỗ in HSD / số lô</strong>. Tối đa 3 ảnh.
                    Ảnh giúp Bác sĩ đối chiếu đúng thuốc — đề nghị không có ảnh dễ bị từ chối.
                </div>
                <input type="file" name="box_photos[]" id="box_photos" accept="image/*" capture="environment" multiple
                       class="form-control @error('box_photos') is-invalid @enderror @error('box_photos.*') is-invalid @enderror">
                @error('box_photos')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('box_photos.*')<div class="invalid-feedback">{{ $message }}</div>@enderror

                <div id="photoPreview" class="d-flex flex-wrap gap-2 mt-2"></div>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnScanBox" disabled>
                        <i class="bi bi-magic me-1"></i><span>Đọc thông tin từ ảnh bằng AI</span>
                    </button>
                    @unless ($aiConfigured)
                        <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Trợ lý AI chưa được cấu hình, bạn tự nhập ở bước 2.</span>
                    @endunless
                </div>
                <div id="scanResult" class="small mt-2 d-none"></div>
            </div>
        </div>

        <!-- BƯỚC 2: THÔNG TIN IN TRÊN VỎ HỘP -->
        <div class="content-card mb-3">
            <div class="content-card-header bg-light">
                <span class="fw-bold text-dark"><i class="bi bi-card-text text-success me-2"></i>Bước 2. Thông tin in trên vỏ hộp</span>
            </div>
            <div class="content-card-body p-3">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="custom_name" class="form-label fw-semibold mb-1">Tên thuốc <span class="text-danger">*</span></label>
                        <input id="custom_name" name="custom_name" maxlength="255" required value="{{ $val('custom_name') }}"
                               placeholder="Chữ in to nhất trên vỏ hộp, VD: Panadol Extra"
                               class="form-control @error('custom_name') is-invalid @enderror">
                        @error('custom_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" id="ingredientHint">{{ $val('active_ingredient') ? 'Hoạt chất: ' . $val('active_ingredient') : '' }}</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="expiry_date" class="form-label fw-semibold mb-1">Hạn sử dụng <span class="text-danger">*</span></label>
                        <input id="expiry_date" name="expiry_date" type="date" required min="{{ today()->format('Y-m-d') }}"
                               value="{{ $val('expiry_date') }}"
                               class="form-control @error('expiry_date') is-invalid @enderror">
                        @error('expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Dòng <strong>HSD</strong> hoặc <strong>EXP</strong> trên vỏ. Chỉ có tháng/năm thì chọn ngày cuối tháng.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="quantity" class="form-label fw-semibold mb-1">Số lượng mua <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input id="quantity" name="quantity" type="number" min="0.5" max="999999.99" step="0.5" required
                                   value="{{ $val('quantity', '1') }}"
                                   class="form-control @error('quantity') is-invalid @enderror">
                            <input id="unit" name="unit" maxlength="30" required value="{{ $val('unit', 'hộp') }}" style="max-width: 110px;"
                                   class="form-control @error('unit') is-invalid @enderror" aria-label="Đơn vị">
                        </div>
                        @error('quantity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('unit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach ($units as $u)
                                <button type="button" class="btn btn-sm btn-light border py-0 px-2 btn-pick" data-target="unit" data-value="{{ $u }}" style="font-size: 11.5px;">{{ $u }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="storage_location" class="form-label fw-semibold mb-1">Cất ở đâu trong nhà</label>
                        <input id="storage_location" name="storage_location" maxlength="255" value="{{ $val('storage_location') }}"
                               placeholder="VD: Ngăn 1 - Tủ phòng khách"
                               class="form-control @error('storage_location') is-invalid @enderror">
                        @error('storage_location')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach ($locations as $loc)
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-pick" data-target="storage_location" data-value="{{ $loc }}" style="font-size: 11.5px;">{{ $loc }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <!-- THÔNG TIN THÊM (KHÔNG BẮT BUỘC) -->
        <div class="content-card mb-3">
            <div class="content-card-header bg-light">
                <span class="fw-bold text-dark"><i class="bi bi-three-dots text-secondary me-2"></i>Thông tin thêm <span class="text-muted fw-normal small">(không bắt buộc)</span></span>
            </div>
            <div class="content-card-body p-3">
                <div class="mb-3">
                    <label for="family_member_id" class="form-label fw-semibold mb-1 small">Mua cho ai dùng?</label>
                    <select id="family_member_id" name="family_member_id" class="form-select form-select-sm @error('family_member_id') is-invalid @enderror">
                        <option value="">Dùng chung cả nhà</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected((string) old('family_member_id') === (string) $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                    @error('family_member_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Bác sĩ sẽ kiểm tra dị ứng / bệnh nền của người này.</div>
                </div>

                <div class="mb-3">
                    <label for="price_per_unit" class="form-label fw-semibold mb-1 small">Giá mua mỗi đơn vị (đ)</label>
                    <input id="price_per_unit" name="price_per_unit" type="number" min="0" max="999999999.99" step="any"
                           value="{{ $val('price_per_unit') }}" placeholder="VD: 45000"
                           class="form-control form-control-sm @error('price_per_unit') is-invalid @enderror">
                    @error('price_per_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if (auth()->user()->isFamilyAdmin())
                        <div class="form-check mt-1">
                            <input type="hidden" name="record_expense" value="0">
                            <input class="form-check-input" type="checkbox" name="record_expense" value="1" id="record_expense" @checked(old('record_expense', true))>
                            <label class="form-check-label small" for="record_expense">Tự ghi vào sổ chi phí khi được duyệt</label>
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="batch_number" class="form-label fw-semibold mb-1 small">Số lô (nếu thấy chữ "Lô", "Lot", "SL" trên vỏ)</label>
                    <input id="batch_number" name="batch_number" maxlength="255" value="{{ $val('batch_number') }}"
                           class="form-control form-control-sm @error('batch_number') is-invalid @enderror">
                    @error('batch_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="requester_note" class="form-label fw-semibold mb-1 small">Nhắn Bác sĩ</label>
                    <textarea id="requester_note" name="requester_note" rows="2" maxlength="1000"
                              placeholder="VD: Mua ở hiệu thuốc theo lời dược sĩ, để dùng khi bé sốt"
                              class="form-control form-control-sm @error('requester_note') is-invalid @enderror">{{ old('requester_note') }}</textarea>
                    @error('requester_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="content-card-body p-3">
                <button type="submit" class="btn btn-erp-primary w-100 mb-2">
                    <i class="bi bi-send-check me-1"></i>Gửi cho {{ $hasDoctor ? 'Bác sĩ' : 'Quản trị viên' }} kiểm tra
                </button>
                <a href="{{ route('cabinet.requests.index') }}" class="btn btn-outline-secondary w-100">Hủy</a>
                <div class="text-muted small mt-2">
                    <i class="bi bi-shield-check me-1"></i>Thuốc chỉ vào tủ sau khi được duyệt. Bạn theo dõi trạng thái ở trang Đề nghị nhập thuốc.
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const photoInput = document.getElementById('box_photos');
    const preview = document.getElementById('photoPreview');
    const btnScan = document.getElementById('btnScanBox');
    const scanResult = document.getElementById('scanResult');

    // Nút chọn nhanh đơn vị / nơi cất
    document.querySelectorAll('.btn-pick').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById(btn.dataset.target).value = btn.dataset.value;
        });
    });

    photoInput.addEventListener('change', function () {
        preview.innerHTML = '';
        const files = Array.from(photoInput.files).slice(0, 3);
        files.forEach(function (file) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = 'Ảnh vỏ hộp';
            img.className = 'rounded border';
            img.style.cssText = 'width: 96px; height: 96px; object-fit: cover;';
            preview.appendChild(img);
        });
        if (photoInput.files.length > 3) {
            scanResult.className = 'small mt-2 text-danger';
            scanResult.textContent = 'Chỉ gửi tối đa 3 ảnh, vui lòng chọn lại.';
        }
        btnScan.disabled = files.length === 0;
    });

    function showResult(kind, text) {
        scanResult.className = 'small mt-2 alert py-2 px-3 mb-0 alert-' + kind;
        scanResult.textContent = text;
    }

    // Chỉ điền vào ô còn trống để không ghi đè thông tin người dùng đã nhập
    function fillIfEmpty(id, value) {
        const el = document.getElementById(id);
        if (!el || !value || el.value.trim() !== '' && !(id === 'unit' && el.value === 'hộp')) return false;
        el.value = value;
        el.classList.add('border-success');
        return true;
    }

    btnScan.addEventListener('click', function () {
        if (!photoInput.files.length) return;
        const data = new FormData();
        data.append('photo', photoInput.files[0]);
        data.append('_token', document.querySelector('input[name=_token]').value);

        btnScan.disabled = true;
        btnScan.querySelector('span').textContent = 'Đang đọc ảnh...';

        fetch(@json(route('cabinet.requests.scan-box')), {
            method: 'POST',
            body: data,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
        .then(function (r) {
            if (!r.ok || !r.body.success) {
                const msg = r.body.message || (r.body.errors ? Object.values(r.body.errors)[0][0] : 'Không đọc được ảnh.');
                showResult('warning', msg);
                return;
            }
            const d = r.body;
            const filled = [
                fillIfEmpty('custom_name', d.name),
                fillIfEmpty('expiry_date', d.expiry_date),
                fillIfEmpty('batch_number', d.batch_number),
                fillIfEmpty('unit', d.unit),
            ].filter(Boolean).length;
            if (d.active_ingredient) {
                document.getElementById('active_ingredient').value = d.active_ingredient;
                document.getElementById('ingredientHint').textContent = 'Hoạt chất (AI đọc): ' + d.active_ingredient;
            }
            showResult('success', filled > 0
                ? 'AI đã điền ' + filled + ' ô (viền xanh). Hãy so lại với chữ trên vỏ hộp trước khi gửi.'
                : 'AI đọc xong nhưng không có ô trống nào cần điền. Hãy kiểm tra lại thông tin.');
        })
        .catch(function () { showResult('warning', 'Không kết nối được Trợ lý AI. Vui lòng tự nhập thông tin.'); })
        .finally(function () {
            btnScan.disabled = !photoInput.files.length;
            btnScan.querySelector('span').textContent = 'Đọc thông tin từ ảnh bằng AI';
        });
    });
})();
</script>
@endpush
@endsection
