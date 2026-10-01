@extends('admin.layouts.admin')

@section('title', $editing ? 'Chỉnh sửa Thuốc: ' . $medicine->name : 'Thêm Thuốc Mới vào Dược thư')

@section('content')
<!-- BREADCRUMB & HEADER -->
<div class="mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1 small">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Tổng quan</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.medicines.index') }}" class="text-decoration-none">Dược thư</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $editing ? 'Chỉnh sửa' : 'Thêm mới' }}</li>
        </ol>
    </nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-capsule text-danger fs-4"></i>
                <span>{{ $editing ? 'Chỉnh sửa Thông tin Thuốc: ' . $medicine->name : 'Thêm Thuốc Mới vào Dược thư Quốc gia' }}</span>
            </h5>
            <div class="text-muted small mt-1">
                Khai báo đầy đủ dược lý, chỉ định, chống chỉ định và tác dụng phụ để hỗ trợ kiểm tra an toàn AI và tra cứu.
            </div>
        </div>
        <a href="{{ route('admin.medicines.index') }}" class="btn btn-sm btn-outline-secondary shadow-sm">
            <i class="bi bi-arrow-left me-1"></i>Quay lại danh mục
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-10 col-xl-9">
        <div class="admin-card">
            <div class="admin-card-header bg-light">
                <span class="text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Thông tin Thuốc & Dược lý</span>
            </div>
            <div class="admin-card-body p-3">
                <form method="POST" action="{{ $editing ? route('admin.medicines.update', $medicine) : route('admin.medicines.store') }}">
                    @csrf
                    @if ($editing)
                        @method('PUT')
                    @endif

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-dark">Mã lưu hành thuốc <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control form-control-sm font-monospace @error('code') is-invalid @enderror" 
                                   value="{{ old('code', $medicine->code) }}" placeholder="VD: MED-PAN-01, VN-22019-19" required>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-8">
                            <label class="form-label small fw-bold text-dark">Tên thương mại của thuốc <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $medicine->name) }}" placeholder="VD: Panadol Extra, Hapacol 250, Augmentin 1g" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Nhóm dược lý <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select form-select-sm @error('category_id') is-invalid @enderror" required>
                                <option value="">-- Chọn nhóm dược lý --</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ (string)old('category_id', $medicine->category_id) === (string)$cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }} ({{ $cat->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Hoạt chất chính <span class="text-danger">*</span></label>
                            <input type="text" name="active_ingredient" class="form-control form-control-sm @error('active_ingredient') is-invalid @enderror" 
                                   value="{{ old('active_ingredient', $medicine->active_ingredient) }}" placeholder="VD: Paracetamol, Amoxicillin, Ibuprofen..." required>
                            @error('active_ingredient')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-dark">Hàm lượng / Nồng độ</label>
                            <input type="text" name="concentration" class="form-control form-control-sm @error('concentration') is-invalid @enderror" 
                                   value="{{ old('concentration', $medicine->concentration) }}" placeholder="VD: 500mg, 1000mg/62.5mg, 10ml">
                            @error('concentration')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-dark">Dạng bào chế</label>
                            <input type="text" name="dosage_form" class="form-control form-control-sm @error('dosage_form') is-invalid @enderror" 
                                   value="{{ old('dosage_form', $medicine->dosage_form) }}" placeholder="VD: Viên nén bao phim, Viên sủi, Siro, Thuốc mỡ">
                            @error('dosage_form')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-dark">Đơn vị chuẩn <span class="text-danger">*</span></label>
                            <input type="text" name="default_unit" class="form-control form-control-sm @error('default_unit') is-invalid @enderror" 
                                   value="{{ old('default_unit', $medicine->default_unit ?: 'viên') }}" placeholder="VD: viên, gói, chai, tuýp" required>
                            @error('default_unit')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Nhà sản xuất / Hãng dược</label>
                        <input type="text" name="manufacturer" class="form-control form-control-sm @error('manufacturer') is-invalid @enderror" 
                               value="{{ old('manufacturer', $medicine->manufacturer) }}" placeholder="VD: Dược Hậu Giang (DHG), GSK, Sanofi, Astra Zeneca...">
                        @error('manufacturer')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-success">
                            <i class="bi bi-journal-check me-1"></i>Chỉ định & Hướng dẫn sử dụng chuẩn
                        </label>
                        <textarea name="usage_instructions" rows="3" class="form-control form-control-sm @error('usage_instructions') is-invalid @enderror" 
                                  placeholder="Liều lượng thông thường, cữ uống, uống trước hay sau ăn...">{{ old('usage_instructions', $medicine->usage_instructions) }}</textarea>
                        @error('usage_instructions')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-danger">
                            <i class="bi bi-shield-slash me-1"></i>Chống chỉ định & Cảnh báo an toàn đặc biệt
                        </label>
                        <textarea name="contraindications" rows="3" class="form-control form-control-sm @error('contraindications') is-invalid @enderror" 
                                  placeholder="Đối tượng không được dùng: người suy gan/suy thận nặng, phụ nữ có thai, người dị ứng với hoạt chất...">{{ old('contraindications', $medicine->contraindications) }}</textarea>
                        @error('contraindications')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-warning-emphasis">
                            <i class="bi bi-exclamation-triangle me-1"></i>Tác dụng phụ có thể gặp
                        </label>
                        <textarea name="side_effects" rows="2" class="form-control form-control-sm @error('side_effects') is-invalid @enderror" 
                                  placeholder="Buồn nôn, chóng mặt, nổi mẩn, đau đầu...">{{ old('side_effects', $medicine->side_effects) }}</textarea>
                        @error('side_effects')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <a href="{{ route('admin.medicines.index') }}" class="btn btn-sm btn-secondary">Hủy bỏ</a>
                        <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">
                            <i class="bi bi-check-lg me-1"></i>{{ $editing ? 'Lưu cập nhật thuốc' : 'Tạo thuốc vào Master Data' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
