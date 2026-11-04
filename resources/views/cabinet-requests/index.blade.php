@extends('layouts.app')

@section('title', 'Đề nghị nhập thuốc - Tủ thuốc gia đình')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="{{ route('cabinet.index') }}" class="text-decoration-none text-muted small d-inline-flex align-items-center mb-1">
            <i class="bi bi-arrow-left me-1"></i> Quay lại tủ thuốc
        </a>
        <h1 class="h4 mb-1 fw-bold text-dark d-flex align-items-center gap-2">
            <i class="bi bi-inbox text-primary"></i> Đề nghị nhập thuốc
            @if ($pendingCount > 0)
                <span class="badge bg-warning text-dark" style="font-size: 12px;">{{ $pendingCount }} chờ duyệt</span>
            @endif
        </h1>
        <div class="text-muted small">
            @if ($user->canManageCabinet())
                Kiểm tra thông tin thuốc gia đình vừa mua (tên, số lô, hạn dùng, số lượng) trước khi duyệt đưa vào tủ.
            @else
                Thuốc gia đình mua thêm được Bác sĩ phụ trách kiểm tra và duyệt trước khi đưa vào tủ thuốc.
            @endif
        </div>
    </div>
    @if ($user->canRequestCabinetItems())
        <a href="{{ route('cabinet.requests.create') }}" class="btn btn-erp-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Gửi đề nghị nhập thuốc
        </a>
    @endif
</div>

<div class="content-card mb-3">
    <div class="content-card-body p-2 d-flex flex-wrap gap-1">
        @php
            $tabs = ['' => 'Tất cả', 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', 'cancelled' => 'Đã hủy'];
        @endphp
        @foreach ($tabs as $value => $label)
            <a href="{{ route('cabinet.requests.index', $value ? ['status' => $value] : []) }}"
               class="btn btn-sm {{ (string) $status === $value ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

<div class="content-card">
    <div class="table-responsive">
        <table class="table table-erp table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Thuốc đề nghị</th>
                    <th>Số lô / HSD</th>
                    <th class="text-end">Số lượng</th>
                    <th>Người gửi</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $req)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $req->display_name }}</div>
                            @if ($req->medicine)
                                <div class="text-muted small">{{ $req->medicine->active_ingredient }}</div>
                            @endif
                            @if ($req->price_per_unit)
                                <div class="text-muted small">
                                    Đơn giá {{ number_format((float) $req->price_per_unit, 0, ',', '.') }} đ
                                    @if ($req->record_expense)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 10px;">Ghi chi phí khi duyệt</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="small">
                            <div>{{ $req->batch_number ?: '—' }}</div>
                            <div class="text-muted">HSD: {{ $req->expiry_date?->format('d/m/Y') }}</div>
                        </td>
                        <td class="text-end fw-semibold">
                            {{ rtrim(rtrim((string) $req->quantity, '0'), '.') }} {{ $req->unit }}
                        </td>
                        <td class="small">
                            <div>{{ $req->requester?->name ?? 'Tài khoản đã gỡ' }}</div>
                            <div class="text-muted">{{ $req->created_at->format('H:i d/m/Y') }}</div>
                        </td>
                        <td>
                            <span class="badge {{ $req->status_badge_class }}">{{ $req->status_label }}</span>
                            @if ($req->reviewer && !$req->isPending())
                                <div class="text-muted small mt-1">
                                    {{ $req->reviewer->name }} · {{ $req->reviewed_at?->format('d/m/Y') }}
                                </div>
                            @endif
                            @if ($req->review_note)
                                <div class="small text-danger-emphasis mt-1"><i class="bi bi-chat-left-text me-1"></i>{{ $req->review_note }}</div>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($req->isPending() && $user->canManageCabinet())
                                <a href="{{ route('cabinet.requests.review', $req) }}" class="btn btn-sm btn-erp-primary">
                                    <i class="bi bi-clipboard-check me-1"></i>Xem & duyệt
                                </a>
                            @elseif ($req->isPending() && (int) $req->requested_by === (int) $user->id)
                                <form method="POST" action="{{ route('cabinet.requests.cancel', $req) }}" class="d-inline"
                                      onsubmit="return confirm('Hủy đề nghị nhập thuốc này?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-x-circle me-1"></i>Hủy
                                    </button>
                                </form>
                            @elseif ($req->cabinet_item_id)
                                <a href="{{ route('cabinet.index', ['q' => $req->custom_name]) }}" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-box-seam me-1"></i>Xem trong tủ
                                </a>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            Chưa có đề nghị nhập thuốc nào.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($requests->hasPages())
        <div class="p-2 border-top">{{ $requests->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
