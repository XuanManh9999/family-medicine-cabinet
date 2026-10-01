@extends('admin.layouts.admin')

@section('title', 'Quản trị Dược thư Quốc gia (Master Data)')

@section('content')
<!-- HEADER -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-capsule text-danger fs-4"></i>
            Quản trị Dược thư Quốc gia (Master Data)
        </h5>
        <div class="text-muted small">
            Quản lý toàn bộ danh mục thuốc mẫu chuẩn, hoạt chất, hàm lượng, chống chỉ định và hướng dẫn sử dụng phục vụ toàn bộ các gia đình.
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('admin.categories.index') }}" class="btn btn-sm btn-outline-secondary shadow-sm">
            <i class="bi bi-tags me-1"></i>Quản lý Nhóm dược lý
        </a>
        <a href="{{ route('admin.medicines.create') }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-circle"></i>
            <span>Thêm thuốc mới vào Dược thư</span>
        </a>
    </div>
</div>

<!-- FILTER & SEARCH BAR -->
<div class="admin-card mb-3">
    <div class="admin-card-body p-3">
        <form method="GET" action="{{ route('admin.medicines.index') }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Tìm theo tên thuốc, mã, hoạt chất, nhà sản xuất..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Tất cả nhóm dược lý --</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" {{ (string)$categoryId === (string)$cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="dosage_form" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Dạng bào chế --</option>
                    @foreach ($dosageForms as $df)
                        <option value="{{ $df }}" {{ $dosageForm === $df ? 'selected' : '' }}>{{ $df }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Lọc</button>
                @if ($search || $categoryId || $dosageForm)
                    <a href="{{ route('admin.medicines.index') }}" class="btn btn-sm btn-outline-secondary" title="Xóa bộ lọc">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- MEDICINES TABLE -->
<div class="admin-card">
    <div class="admin-card-header d-flex justify-content-between align-items-center">
        <div>
            <span class="text-dark"><i class="bi bi-list-ul text-primary me-2"></i>Danh sách Dược phẩm</span>
            <span class="badge bg-secondary-subtle text-secondary ms-2">{{ $medicines->total() }} loại thuốc</span>
        </div>
        <div class="text-muted small">
            Trang {{ $medicines->currentPage() }} / {{ max(1, $medicines->lastPage()) }}
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-admin mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">STT</th>
                    <th>Mã thuốc</th>
                    <th>Tên thương mại & Hoạt chất</th>
                    <th>Nhóm dược lý</th>
                    <th>Dạng bào chế</th>
                    <th>Nhà sản xuất</th>
                    <th class="text-center">Đang dùng ở tủ</th>
                    <th class="text-end" style="width: 140px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($medicines as $idx => $med)
                    <tr>
                        <td>{{ $medicines->firstItem() + $idx }}</td>
                        <td>
                            <code class="fw-bold">{{ $med->code }}</code>
                        </td>
                        <td>
                            <a href="{{ route('medicines.show', $med) }}" target="_blank" class="fw-bold text-decoration-none text-primary" title="Xem trang hiển thị công khai">
                                {{ $med->name }} <i class="bi bi-box-arrow-up-right small" style="font-size: 10px;"></i>
                            </a>
                            <div class="text-dark small mt-0.5">
                                <span class="text-muted">Hoạt chất:</span> <strong>{{ $med->active_ingredient }}</strong>
                                @if ($med->concentration)
                                    <span class="badge bg-light text-dark border ms-1">{{ $med->concentration }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if ($med->category)
                                <span class="badge bg-info-subtle text-info border border-info-subtle">
                                    {{ $med->category->name }}
                                </span>
                            @else
                                <span class="text-muted small">Chưa phân nhóm</span>
                            @endif
                        </td>
                        <td>
                            <div class="small fw-semibold">{{ $med->dosage_form ?: 'Viên nén' }}</div>
                            <div class="text-muted" style="font-size: 11px;">ĐVT: {{ $med->default_unit ?: 'viên' }}</div>
                        </td>
                        <td>
                            <div class="small text-truncate" style="max-width: 160px;" title="{{ $med->manufacturer }}">
                                {{ $med->manufacturer ?: '—' }}
                            </div>
                        </td>
                        <td class="text-center">
                            @if ($med->cabinet_items_count > 0)
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    {{ $med->cabinet_items_count }} tủ
                                </span>
                            @else
                                <span class="badge bg-light text-muted border">0</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.medicines.edit', $med) }}" class="btn btn-outline-primary" title="Chỉnh sửa thông tin thuốc">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.medicines.destroy', $med) }}" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa thuốc này khỏi Dược thư Quốc gia?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="Xóa thuốc">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-search fs-1 mb-2 d-block opacity-50"></i>
                            <div>Không tìm thấy loại thuốc nào phù hợp.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($medicines->hasPages())
        <div class="card-footer bg-white border-top p-2 d-flex justify-content-between align-items-center">
            <div class="small text-muted">
                Hiển thị {{ $medicines->firstItem() }} - {{ $medicines->lastItem() }} / {{ $medicines->total() }} thuốc
            </div>
            <div>
                {{ $medicines->links('pagination::bootstrap-5') }}
            </div>
        </div>
    @endif
</div>
@endsection
