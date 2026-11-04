{{-- Bác sĩ / Quản trị viên xử lý một yêu cầu bổ sung: kê tiếp (đồng ý mua thêm), ngưng thuốc hoặc không mua thêm --}}
@php $isPrescription = (bool) $refill->prescription_item_id; @endphp
<div class="row g-2">
    <div class="col-12 col-lg-7">
        <form method="POST" action="{{ route('cabinet.refills.approve', $refill) }}" class="border rounded p-2 bg-light h-100">
            @csrf
            <div class="fw-semibold small mb-1 text-primary">
                <i class="bi bi-check2-circle me-1"></i>{{ $isPrescription ? 'Kê tiếp thuốc này' : 'Đồng ý mua thêm' }}
            </div>
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label small mb-0" for="approved_quantity_{{ $refill->id }}">Số lượng ({{ $refill->unit }}) <span class="text-danger">*</span></label>
                    <input type="number" step="0.5" min="0.5" max="999999.99" required name="approved_quantity" id="approved_quantity_{{ $refill->id }}"
                           class="form-control form-control-sm" placeholder="VD: 30">
                </div>
                @if ($isPrescription)
                    <div class="col-6">
                        <label class="form-label small mb-0" for="extended_until_{{ $refill->id }}">Uống tiếp tới ngày</label>
                        <input type="date" name="extended_until" id="extended_until_{{ $refill->id }}" min="{{ today()->format('Y-m-d') }}" class="form-control form-control-sm">
                    </div>
                @endif
                <div class="col-12">
                    <input type="text" name="review_note" maxlength="1000" class="form-control form-control-sm" placeholder="Dặn dò gia đình (không bắt buộc)">
                </div>
            </div>
            <button type="submit" class="btn btn-sm btn-primary mt-2">{{ $isPrescription ? 'Kê tiếp' : 'Đồng ý mua thêm' }}</button>
        </form>
    </div>
    <div class="col-12 col-lg-5">
        <form method="POST" action="{{ $isPrescription ? route('cabinet.refills.stop', $refill) : route('cabinet.refills.reject', $refill) }}"
              class="border rounded p-2 h-100" onsubmit="return confirm(@js($isPrescription ? 'Cho ngưng thuốc này? Lịch uống sẽ dừng sau hôm nay.' : 'Không đồng ý mua thêm?'))">
            @csrf
            <div class="fw-semibold small mb-1 text-danger">
                <i class="bi bi-x-octagon me-1"></i>{{ $isPrescription ? 'Ngưng thuốc' : 'Không mua thêm' }}
            </div>
            <input type="text" name="review_note" maxlength="1000" required class="form-control form-control-sm"
                   placeholder="{{ $isPrescription ? 'Lý do ngưng, VD: đã ổn định, đổi thuốc khác' : 'Lý do, VD: chưa cần dự trữ' }}">
            <button type="submit" class="btn btn-sm btn-outline-danger mt-2">{{ $isPrescription ? 'Cho ngưng thuốc' : 'Không mua thêm' }}</button>
        </form>
    </div>
</div>
