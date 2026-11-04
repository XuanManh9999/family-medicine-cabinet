@extends('layouts.app')

@section('title', 'Ghi nhận đã mua thuốc - Tủ thuốc gia đình')

@section('content')
@php
    $qty = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $units = ['viên', 'vỉ', 'hộp', 'gói', 'chai', 'ống', 'tuýp', 'lọ'];
    $locations = ['Ngăn 1 - Tủ phòng khách', 'Ngăn 2 - Tủ phòng khách', 'Ngăn mát tủ lạnh', 'Tủ đầu giường', 'Hộp sơ cứu'];
    $course = $prescriptionItem?->treatmentCourse;
@endphp

<div class="mb-3">
    <a href="{{ route('cabinet.supply') }}" class="text-decoration-none text-muted small d-inline-flex align-items-center mb-1">
        <i class="bi bi-arrow-left me-1"></i> Quay lại Cần mua & bổ sung
    </a>
    <h1 class="h4 fw-bold text-dark mb-1"><i class="bi bi-bag-check text-success me-1"></i>Đã mua: {{ $medicineName }}</h1>
    <div class="text-muted small">Thuốc do Bác sĩ đã kê / đồng ý nên không cần duyệt lại. Bạn chỉ ghi số lượng thực mua và hạn dùng in trên vỏ hộp.</div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <form method="POST" action="{{ $formAction }}" class="content-card">
            @csrf
            <input type="hidden" name="return_to" value="{{ request('return') }}">
            <div class="content-card-body p-3">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="quantity" class="form-label fw-semibold mb-1">Số lượng thực mua <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input id="quantity" name="quantity" type="number" min="0.5" max="999999.99" step="0.5" required
                                   value="{{ old('quantity', $qty($suggestedQuantity) ?: '1') }}"
                                   class="form-control @error('quantity') is-invalid @enderror">
                            <input id="unit" name="unit" maxlength="30" required value="{{ old('unit', $unit) }}" style="max-width: 110px;"
                                   class="form-control @error('unit') is-invalid @enderror" aria-label="Đơn vị">
                        </div>
                        @error('quantity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('unit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach ($units as $u)
                                <button type="button" class="btn btn-sm btn-light border py-0 px-2 btn-pick" data-target="unit" data-value="{{ $u }}" style="font-size: 11.5px;">{{ $u }}</button>
                            @endforeach
                        </div>
                        @if ($suggestedQuantity)
                            <div class="form-text">Bác sĩ kê còn {{ $qty($suggestedQuantity) }} {{ $unit }} chưa mua.</div>
                        @endif
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="expiry_date" class="form-label fw-semibold mb-1">Hạn sử dụng <span class="text-danger">*</span></label>
                        <input id="expiry_date" name="expiry_date" type="date" required min="{{ today()->format('Y-m-d') }}"
                               value="{{ old('expiry_date') }}" class="form-control @error('expiry_date') is-invalid @enderror">
                        @error('expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Dòng <strong>HSD</strong> / <strong>EXP</strong> trên vỏ. Chỉ có tháng/năm thì chọn ngày cuối tháng.</div>
                    </div>

                    <div class="col-12">
                        <label for="storage_location" class="form-label fw-semibold mb-1">Cất ở đâu trong nhà</label>
                        <input id="storage_location" name="storage_location" maxlength="255" value="{{ old('storage_location') }}"
                               placeholder="VD: Ngăn 1 - Tủ phòng khách" class="form-control @error('storage_location') is-invalid @enderror">
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach ($locations as $loc)
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-pick" data-target="storage_location" data-value="{{ $loc }}" style="font-size: 11.5px;">{{ $loc }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="price_per_unit" class="form-label small fw-semibold mb-1">Giá mua mỗi {{ $unit }} (đ) <span class="text-muted fw-normal">– không bắt buộc</span></label>
                        <input id="price_per_unit" name="price_per_unit" type="number" min="0" max="999999999.99" step="any" value="{{ old('price_per_unit') }}"
                               class="form-control form-control-sm @error('price_per_unit') is-invalid @enderror">
                        @error('price_per_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if ($canRecordExpense)
                            <div class="form-check mt-1">
                                <input type="hidden" name="record_expense" value="0">
                                <input class="form-check-input" type="checkbox" name="record_expense" value="1" id="record_expense" @checked(old('record_expense', true))>
                                <label class="form-check-label small" for="record_expense">Ghi vào sổ chi phí y tế</label>
                            </div>
                        @endif
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="batch_number" class="form-label small fw-semibold mb-1">Số lô <span class="text-muted fw-normal">– nếu thấy "Lô/Lot/SL"</span></label>
                        <input id="batch_number" name="batch_number" maxlength="255" value="{{ old('batch_number') }}" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
            <div class="content-card-body p-3 border-top d-flex justify-content-between">
                <a href="{{ route('cabinet.supply') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-erp-primary px-4"><i class="bi bi-box-arrow-in-down me-1"></i>Cho vào tủ thuốc</button>
            </div>
        </form>
    </div>

    <div class="col-12 col-lg-5">
        <div class="content-card mb-3">
            <div class="content-card-header bg-light">
                <span class="fw-bold text-dark"><i class="bi bi-info-circle text-primary me-2"></i>{{ $target === 'prescription' ? 'Theo đơn của Bác sĩ' : 'Bổ sung đã được đồng ý' }}</span>
            </div>
            <div class="content-card-body p-3 small">
                @if ($target === 'prescription')
                    <div class="fw-bold fs-6">{{ $prescriptionItem->medicine_name }}</div>
                    <div class="text-muted mb-2">{{ $prescriptionItem->medicine?->active_ingredient }}</div>
                    <div>Đơn: <strong>{{ $course->title }}</strong></div>
                    <div>Người dùng: <strong>{{ $course->familyMember?->name ?? 'Gia đình' }}</strong></div>
                    <div>Liều: {{ $prescriptionItem->dosage }} · {{ $prescriptionItem->frequency }}</div>
                    @if ($prescriptionItem->instructions)<div>Cách dùng: {{ $prescriptionItem->instructions }}</div>@endif
                    <div>Kê: {{ (float) $prescriptionItem->total_prescribed_quantity > 0 ? $qty($prescriptionItem->total_prescribed_quantity) . ' ' . $unit : 'không ghi tổng số lượng' }}
                        · đã mua {{ $qty($prescriptionItem->purchased_quantity) ?: '0' }}</div>
                @else
                    <div class="fw-bold fs-6">{{ $refill->medicine_name }}</div>
                    <div>Được mua thêm: <strong>{{ $qty($refill->approved_quantity) }} {{ $refill->unit }}</strong></div>
                    @if ($refill->review_note)<div>Dặn dò: {{ $refill->review_note }}</div>@endif
                @endif
                <div class="alert alert-warning py-2 px-2 mt-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>Kiểm tra tên trên vỏ hộp <strong>đúng với tên thuốc trên đơn</strong>. Hiệu thuốc đổi sang thuốc khác thì hỏi lại Bác sĩ trước khi dùng.
                </div>
            </div>
        </div>

        @if ($existingStock->isNotEmpty())
            <div class="content-card">
                <div class="content-card-header bg-light">
                    <span class="fw-bold text-dark"><i class="bi bi-box-seam text-success me-2"></i>Đã có sẵn trong tủ?</span>
                </div>
                <div class="content-card-body p-3 small">
                    <div class="text-muted mb-2">Nếu thuốc này đã mua từ trước và đang có trong tủ, chọn lô đó thay vì nhập mới:</div>
                    @foreach ($existingStock as $lot)
                        <form method="POST" action="{{ route('cabinet.purchases.prescription.link', $prescriptionItem) }}" class="d-flex justify-content-between align-items-center gap-2 py-1 border-bottom">
                            @csrf
                            <input type="hidden" name="cabinet_item_id" value="{{ $lot->id }}">
                            <span>{{ $lot->display_name }} — còn {{ $qty($lot->quantity) }} {{ $lot->unit }}, HSD {{ $lot->expiry_date?->format('d/m/Y') }}</span>
                            <button type="submit" class="btn btn-sm btn-outline-success py-0">Dùng lô này</button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    document.querySelectorAll('.btn-pick').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById(btn.dataset.target).value = btn.dataset.value;
        });
    });
</script>
@endpush
@endsection
