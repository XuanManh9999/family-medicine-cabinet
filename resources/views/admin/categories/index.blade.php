@extends('admin.layouts.admin')

@section('title', 'Quản lý Nhóm Dược lý - MedCabinet Admin')

@section('content')
<!-- HEADER -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-tags text-danger fs-4"></i>
            Quản trị Nhóm Dược lý (Medicine Categories)
        </h5>
        <div class="text-muted small">
            Phân loại dược lý chuẩn hóa (Kháng sinh, Hạ sốt, Tim mạch, Vitamin...) cho danh mục thuốc toàn quốc.
        </div>
    </div>
    <button type="button" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
        <i class="bi bi-plus-circle me-1"></i>Thêm nhóm mới
    </button>
</div>

<!-- SEARCH & FILTER FORM -->
<div class="admin-card p-3 mb-3">
    <form method="GET" action="{{ route('admin.categories.index') }}" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm border-start-0" placeholder="Tìm theo tên nhóm dược lý, mô tả tác dụng...">
            </div>
        </div>
        <div class="col-md-3">
            <select name="type" class="form-select form-select-sm">
                <option value="">-- Tất cả nhóm thuốc --</option>
                <option value="rx" {{ $type === 'rx' ? 'selected' : '' }}>Thuốc kê đơn (Rx)</option>
                <option value="otc" {{ $type === 'otc' ? 'selected' : '' }}>Thuốc không kê đơn (OTC)</option>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="bi bi-funnel me-1"></i>Lọc danh sách
            </button>
            @if ($search || $type)
                <a href="{{ route('admin.categories.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Đặt lại
                </a>
            @endif
        </div>
    </form>
</div>

<!-- CATEGORIES TABLE -->
<div class="admin-card">
    <div class="admin-card-header d-flex justify-content-between align-items-center">
        <span class="text-dark"><i class="bi bi-folder text-primary me-2"></i>Danh sách Nhóm Dược lý</span>
        <span class="badge bg-secondary-subtle text-secondary border">Tổng cộng {{ $categories->total() }} nhóm</span>
    </div>
    <div class="table-responsive">
        <table class="table table-admin mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">STT</th>
                    <th>Tên nhóm dược lý</th>
                    <th>Loại đơn</th>
                    <th>Mô tả nhóm</th>
                    <th class="text-center">Số loại thuốc</th>
                    <th class="text-end" style="width: 140px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $idx => $cat)
                    <tr>
                        <td>{{ $categories->firstItem() + $idx }}</td>
                        <td>
                            <strong class="text-dark">{{ $cat->name }}</strong>
                        </td>
                        <td>
                            @if ($cat->is_prescription_required)
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Kê đơn (Rx)</span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Không kê đơn (OTC)</span>
                            @endif
                        </td>
                        <td>
                            <span class="text-muted small">{{ $cat->description ?: '—' }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $cat->medicines_count > 0 ? 'bg-primary' : 'bg-light text-muted border' }}">
                                {{ $cat->medicines_count }} thuốc
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" 
                                        onclick="editCategory({{ json_encode($cat) }})" title="Sửa nhóm">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" action="{{ route('admin.categories.destroy', $cat) }}" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa nhóm này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" {{ $cat->medicines_count > 0 ? 'disabled title="Không thể xóa khi còn thuốc thuộc nhóm"' : 'title="Xóa nhóm"' }}>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">Không tìm thấy nhóm dược lý phù hợp.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($categories->hasPages())
        <div class="p-3 border-top d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                Hiển thị {{ $categories->firstItem() }} đến {{ $categories->lastItem() }} trên {{ $categories->total() }} nhóm
            </div>
            <div>
                {{ $categories->links() }}
            </div>
        </div>
    @endif
</div>

<!-- MODAL: THÊM NHÓM DƯỢC LÝ MỚI -->
<div class="modal fade" id="createCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form method="POST" action="{{ route('admin.categories.store') }}">
                @csrf
                <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title fw-bold">Thêm Nhóm Dược lý Mới</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Tên nhóm dược lý <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="VD: Thuốc Tim mạch & Huyết áp" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_prescription_required" value="1" id="isRxCreate">
                            <label class="form-check-label small fw-bold text-dark" for="isRxCreate">
                                Yêu cầu đơn thuốc bác sĩ (Thuốc kê đơn Rx)
                            </label>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark">Mô tả tóm tắt</label>
                        <textarea name="description" rows="3" class="form-control form-control-sm" placeholder="Mô tả công dụng chung của nhóm thuốc..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-sm btn-primary">Xác nhận tạo nhóm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: SỬA NHÓM DƯỢC LÝ -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form method="POST" id="editCategoryForm" action="">
                @csrf
                @method('PUT')
                <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title fw-bold">Chỉnh sửa Nhóm Dược lý</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Tên nhóm dược lý <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editCatName" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_prescription_required" value="1" id="editCatRx">
                            <label class="form-check-label small fw-bold text-dark" for="editCatRx">
                                Yêu cầu đơn thuốc bác sĩ (Thuốc kê đơn Rx)
                            </label>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark">Mô tả tóm tắt</label>
                        <textarea name="description" id="editCatDesc" rows="3" class="form-control form-control-sm"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-sm btn-primary">Lưu cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function editCategory(cat) {
        document.getElementById('editCategoryForm').action = '/admin/categories/' + cat.id;
        document.getElementById('editCatName').value = cat.name;
        document.getElementById('editCatDesc').value = cat.description || '';
        document.getElementById('editCatRx').checked = !!cat.is_prescription_required;
        new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
    }
</script>
@endpush
@endsection
