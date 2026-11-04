@extends('admin.layouts.admin')

@section('title', 'Duyệt đề nghị chỉ định Bác sĩ')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-person-badge text-danger fs-4"></i>
            Đề nghị chỉ định Bác sĩ / Người chăm sóc
            @if ($pendingCount > 0)
                <span class="badge bg-warning text-dark" style="font-size: 12px;">{{ $pendingCount }} chờ xác minh</span>
            @endif
        </h5>
        <div class="text-muted small">
            Bác sĩ có quyền kê đơn và xác nhận thuốc cho cả gia đình, nên cần xác minh nơi công tác / chứng chỉ hành nghề trước khi cấp quyền.
        </div>
    </div>
    <div class="btn-group btn-group-sm">
        @foreach (['pending' => 'Chờ xác minh', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', 'all' => 'Tất cả'] as $value => $label)
            <a href="{{ route('admin.doctor-requests.index', ['status' => $value]) }}" class="btn {{ $status === $value ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-admin mb-0 align-middle">
            <thead>
                <tr>
                    <th>Người được đề nghị</th>
                    <th>Hộ gia đình</th>
                    <th>Thông tin hành nghề</th>
                    <th>Trạng thái</th>
                    <th class="text-end" style="min-width: 320px;">Xử lý</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $req)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $req->user?->name ?? 'Tài khoản đã xóa' }}</div>
                            <div class="text-muted small">{{ $req->user?->email }}</div>
                        </td>
                        <td class="small">
                            <a href="{{ route('admin.families.show', $req->family_id) }}" class="text-decoration-none">{{ $req->family?->name }}</a>
                            <div class="text-muted">Đề nghị bởi {{ $req->requester?->name ?? '—' }}, {{ $req->created_at->format('d/m/Y') }}</div>
                        </td>
                        <td class="small" style="max-width: 320px;">{{ $req->credentials }}</td>
                        <td class="small">
                            @php
                                $badge = match ($req->status) {
                                    'approved' => ['bg-success-subtle text-success', 'Đã duyệt'],
                                    'rejected' => ['bg-danger-subtle text-danger', 'Từ chối'],
                                    'cancelled' => ['bg-secondary-subtle text-secondary', 'Đã hủy'],
                                    default => ['bg-warning-subtle text-warning-emphasis', 'Chờ xác minh'],
                                };
                            @endphp
                            <span class="badge border {{ $badge[0] }}">{{ $badge[1] }}</span>
                            @if ($req->review_note)
                                <div class="text-muted mt-1">{{ $req->reviewer?->name }}: {{ $req->review_note }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($req->isPending())
                                <form method="POST" action="{{ route('admin.doctor-requests.approve', $req) }}" class="d-inline-flex gap-1 mb-1">
                                    @csrf
                                    <input type="text" name="review_note" maxlength="1000" class="form-control form-control-sm" placeholder="Ghi chú xác minh (không bắt buộc)">
                                    <button class="btn btn-sm btn-success text-nowrap" type="submit">Cấp quyền Bác sĩ</button>
                                </form>
                                <form method="POST" action="{{ route('admin.doctor-requests.reject', $req) }}" class="d-inline-flex gap-1"
                                      onsubmit="return confirm('Từ chối đề nghị này?')">
                                    @csrf
                                    <input type="text" name="review_note" maxlength="1000" required class="form-control form-control-sm" placeholder="Lý do từ chối">
                                    <button class="btn btn-sm btn-outline-danger text-nowrap" type="submit">Từ chối</button>
                                </form>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">Không có đề nghị nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($requests->hasPages())
        <div class="p-2 border-top">{{ $requests->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
