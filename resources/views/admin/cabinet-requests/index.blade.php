@extends('admin.layouts.admin')

@section('title', 'Đề nghị nhập thuốc toàn hệ thống')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-inbox text-danger fs-4"></i>
            Đề nghị nhập thuốc
            @if ($pendingNoDoctor > 0)
                <span class="badge bg-warning text-dark" style="font-size: 12px;">{{ $pendingNoDoctor }} chờ duyệt thay</span>
            @endif
        </h5>
        <div class="text-muted small">
            Hộ gia đình chưa có Bác sĩ / Người chăm sóc thì Quản trị viên duyệt thay để đề nghị không bị kẹt.
            Hộ đã có Bác sĩ nên để Bác sĩ của gia đình duyệt.
        </div>
    </div>
</div>

@if ($pendingRefills->isNotEmpty())
    <div class="admin-card mb-3 border-warning">
        <div class="admin-card-header bg-warning-subtle d-flex justify-content-between align-items-center">
            <span class="text-warning-emphasis fw-bold"><i class="bi bi-arrow-repeat me-2"></i>Yêu cầu bổ sung thuốc của hộ chưa có Bác sĩ</span>
            <span class="badge bg-warning text-dark">{{ $pendingRefills->count() }} chờ xử lý</span>
        </div>
        <div class="admin-card-body p-0">
            @foreach ($pendingRefills as $refill)
                <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                        <div>
                            <div class="fw-bold">{{ $refill->medicine_name }}
                                <span class="text-muted fw-normal small">· {{ $refill->family?->name }}</span>
                            </div>
                            <div class="text-muted small">
                                {{ $refill->source === 'schedule' ? 'Thuốc đang uống' : 'Thuốc trong tủ' }}
                                @if ($refill->prescriptionItem?->treatmentCourse) · Đơn: {{ $refill->prescriptionItem->treatmentCourse->title }} @endif
                                · còn {{ rtrim(rtrim((string) $refill->current_stock, '0'), '.') }} {{ $refill->unit }}
                                · {{ $refill->requester?->name }}, {{ $refill->created_at->format('H:i d/m/Y') }}
                            </div>
                            @if ($refill->requester_note)
                                <div class="small mt-1"><i class="bi bi-chat-left-quote me-1"></i>{{ $refill->requester_note }}</div>
                            @endif
                        </div>
                    </div>
                    @include('cabinet-supply.partials.refill-decision', ['refill' => $refill])
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="admin-card mb-3">
    <div class="admin-card-body p-2 d-flex flex-wrap gap-2 align-items-center">
        <div class="btn-group btn-group-sm">
            <a href="{{ route('admin.cabinet-requests.index', ['scope' => 'no_doctor', 'status' => $status]) }}"
               class="btn {{ $scope === 'no_doctor' ? 'btn-primary' : 'btn-outline-secondary' }}">Hộ chưa có Bác sĩ ({{ $pendingNoDoctor }})</a>
            <a href="{{ route('admin.cabinet-requests.index', ['scope' => 'all', 'status' => $status]) }}"
               class="btn {{ $scope === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">Tất cả hộ ({{ $pendingAll }})</a>
        </div>
        <div class="btn-group btn-group-sm ms-md-auto">
            @foreach (['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', 'all' => 'Mọi trạng thái'] as $value => $label)
                <a href="{{ route('admin.cabinet-requests.index', ['scope' => $scope, 'status' => $value]) }}"
                   class="btn {{ $status === $value ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-admin mb-0">
            <thead>
                <tr>
                    <th>Hộ gia đình</th>
                    <th>Thuốc đề nghị</th>
                    <th>HSD / Số lượng</th>
                    <th>Người gửi</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $req)
                    <tr>
                        <td>
                            <a href="{{ route('admin.families.show', $req->family_id) }}" class="fw-bold text-decoration-none">{{ $req->family?->name }}</a>
                            <div>
                                @if ($familiesWithDoctor->contains($req->family_id))
                                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px;">Đã có Bác sĩ</span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 10px;">Chưa có Bác sĩ</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $req->display_name }}</div>
                            <div class="text-muted small">
                                @if ($req->box_image_paths)
                                    <i class="bi bi-camera me-1"></i>{{ count($req->box_image_paths) }} ảnh vỏ hộp
                                @else
                                    <i class="bi bi-camera-video-off me-1"></i>Không có ảnh
                                @endif
                            </div>
                        </td>
                        <td class="small">
                            <div>HSD: {{ $req->expiry_date?->format('d/m/Y') }}</div>
                            <div class="text-muted">{{ rtrim(rtrim((string) $req->quantity, '0'), '.') }} {{ $req->unit }}</div>
                        </td>
                        <td class="small">
                            <div>{{ $req->requester?->name ?? '—' }}</div>
                            <div class="text-muted">{{ $req->created_at->format('H:i d/m/Y') }}</div>
                        </td>
                        <td>
                            <span class="badge {{ $req->status_badge_class }}">{{ $req->status_label }}</span>
                            @if ($req->reviewer && !$req->isPending())
                                <div class="text-muted small mt-1">{{ $req->reviewer->name }}</div>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($req->isPending())
                                <a href="{{ route('cabinet.requests.review', $req) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-clipboard-check me-1"></i>Xem & duyệt
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
                            Không có đề nghị nào phù hợp bộ lọc.
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
