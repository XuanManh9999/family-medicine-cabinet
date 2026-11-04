{{-- Thông tin người gửi trên form duyệt đề nghị nhập thuốc --}}
<div class="content-card mb-3 border-warning">
    <div class="content-card-header bg-warning-subtle">
        <span class="fw-bold text-warning-emphasis"><i class="bi bi-person-check me-2"></i>5. Thông tin đề nghị</span>
    </div>
    <div class="content-card-body p-3 small">
        <div class="row g-2">
            <div class="col-md-6">
                Người gửi: <strong>{{ $cabinetRequest->requester?->name ?? 'Tài khoản đã gỡ' }}</strong>
                <span class="text-muted">({{ $cabinetRequest->requester?->role_name }})</span>
            </div>
            <div class="col-md-6">
                Thời gian gửi: <strong>{{ $cabinetRequest->created_at->format('H:i d/m/Y') }}</strong>
            </div>
            <div class="col-12">
                @if ($cabinetRequest->record_expense)
                    <i class="bi bi-cash-coin text-success me-1"></i>Khi duyệt sẽ tự ghi chi phí mua thuốc (số lượng × đơn giá) vào sổ chi tiêu gia đình.
                @else
                    <i class="bi bi-cash text-muted me-1"></i>Không ghi nhận chi phí tự động.
                @endif
            </div>
        </div>
    </div>
</div>
