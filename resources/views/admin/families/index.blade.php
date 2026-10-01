@extends('admin.layouts.admin')

@section('title', 'Quản lý Hộ Gia đình Toàn sàn')

@section('content')
<!-- HEADER -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-houses text-danger fs-4"></i>
            Quản trị Hộ Gia đình Toàn hệ thống
        </h5>
        <div class="text-muted small">
            Theo dõi danh sách các hộ gia đình đăng ký, số lượng hồ sơ sức khỏe và quy mô tủ thuốc.
        </div>
    </div>
</div>

<!-- SEARCH BAR -->
<div class="admin-card mb-3">
    <div class="admin-card-body p-3">
        <form method="GET" action="{{ route('admin.families.index') }}" class="row g-2 align-items-center">
            <div class="col-md-8">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Tìm theo tên hộ gia đình, mã mời (FAM-...), địa chỉ..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-4 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Tìm kiếm</button>
                @if ($search)
                    <a href="{{ route('admin.families.index') }}" class="btn btn-sm btn-outline-secondary" title="Xóa tìm kiếm">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- FAMILIES TABLE -->
<div class="admin-card">
    <div class="admin-card-header d-flex justify-content-between align-items-center">
        <div>
            <span class="text-dark"><i class="bi bi-list-ul text-primary me-2"></i>Danh sách Hộ Gia đình</span>
            <span class="badge bg-secondary-subtle text-secondary ms-2">{{ $families->total() }} gia đình</span>
        </div>
        <div class="text-muted small">
            Trang {{ $families->currentPage() }} / {{ max(1, $families->lastPage()) }}
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-admin mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">STT</th>
                    <th>Mã mời</th>
                    <th>Tên Hộ Gia đình</th>
                    <th>Chủ hộ / Người lập</th>
                    <th class="text-center">Tài khoản</th>
                    <th class="text-center">Thành viên</th>
                    <th class="text-center">Thuốc trong tủ</th>
                    <th class="text-center">Đơn điều trị</th>
                    <th>Ngày đăng ký</th>
                    <th class="text-end">Chi tiết</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($families as $idx => $family)
                    <tr>
                        <td>{{ $families->firstItem() + $idx }}</td>
                        <td>
                            <span class="badge bg-light text-primary border font-monospace">{{ $family->code }}</span>
                        </td>
                        <td>
                            <a href="{{ route('admin.families.show', $family) }}" class="fw-bold text-decoration-none text-dark">
                                {{ $family->name }}
                            </a>
                            <div class="text-muted small text-truncate" style="max-width: 250px;">{{ $family->address ?: 'Chưa cập nhật địa chỉ' }}</div>
                        </td>
                        <td>
                            <div class="small fw-semibold">{{ $family->creator?->name ?: '—' }}</div>
                            <div class="text-muted" style="font-size: 11px;">{{ $family->creator?->email ?: '' }}</div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info-subtle text-info">{{ $family->users_count }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success-subtle text-success">{{ $family->members_count }} người</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary-subtle text-primary">{{ $family->cabinet_items_count }} loại</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-warning-subtle text-warning-emphasis">{{ $family->treatment_courses_count }} đơn</span>
                        </td>
                        <td class="small text-muted">
                            {{ $family->created_at ? $family->created_at->format('d/m/Y') : '—' }}
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.families.show', $family) }}" class="btn btn-sm btn-outline-primary" title="Xem chi tiết gia đình">
                                <i class="bi bi-eye me-1"></i>Xem
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">Không tìm thấy hộ gia đình nào phù hợp.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($families->hasPages())
        <div class="card-footer bg-white border-top p-2 d-flex justify-content-between align-items-center">
            <div class="small text-muted">
                Hiển thị {{ $families->firstItem() }} - {{ $families->lastItem() }} / {{ $families->total() }} gia đình
            </div>
            <div>
                {{ $families->links('pagination::bootstrap-5') }}
            </div>
        </div>
    @endif
</div>
@endsection
