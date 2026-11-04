{{-- Phần bổ sung của form "Đề nghị nhập thuốc" --}}
<div class="content-card mb-3">
    <div class="content-card-header bg-light">
        <span class="fw-bold text-dark"><i class="bi bi-send-check text-primary me-2"></i>5. Gửi đề nghị</span>
    </div>
    <div class="content-card-body p-3 small">
        <div class="alert alert-info py-2 px-3 mb-2">
            <i class="bi bi-info-circle me-1"></i>
            Thuốc chỉ được đưa vào tủ sau khi <strong>Bác sĩ phụ trách</strong> kiểm tra số lô, hạn dùng và duyệt đề nghị.
            Bạn có thể theo dõi trạng thái tại trang <a href="{{ route('cabinet.requests.index') }}">Đề nghị nhập thuốc</a>.
        </div>
        @if (auth()->user()->isFamilyAdmin())
            <div class="form-check">
                <input type="hidden" name="record_expense" value="0">
                <input class="form-check-input" type="checkbox" name="record_expense" value="1" id="record_expense"
                       @checked(old('record_expense', true))>
                <label class="form-check-label" for="record_expense">
                    Tự động ghi nhận <strong>chi phí mua thuốc</strong> (số lượng × đơn giá) vào sổ chi tiêu khi đề nghị được duyệt
                </label>
            </div>
        @endif
    </div>
</div>
