@extends('layouts.app')

@section('title', 'Đơn thuốc & Đợt điều trị ngoại trú - Tủ thuốc Gia đình')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-journal-medical text-primary fs-4"></i>
            Đơn thuốc & Đợt điều trị ngoại trú
            @if (!auth()->user()->canManageTreatments())
                <span class="badge bg-secondary-subtle text-secondary border fw-normal" style="font-size: 11.5px;">
                    <i class="bi bi-eye me-1"></i>Chế độ xem
                </span>
            @else
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal" style="font-size: 11.5px;">
                    <i class="bi bi-file-earmark-medical-fill me-1"></i>Quyền kê đơn & phác đồ
                </span>
            @endif
        </h5>
        <div class="text-muted small">
            Quản lý toa thuốc bác sĩ, theo dõi phác đồ điều trị, thẩm định an toàn tương tác thuốc và tự động sinh lịch uống.
        </div>
    </div>
    @if (auth()->user()->canManageTreatments())
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('treatment-courses.create') }}" class="btn btn-erp-primary d-inline-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-plus-circle"></i>
                <span>Thêm đợt điều trị / Kê đơn mới</span>
            </a>
        </div>
    @endif
</div>

<!-- KPI SUMMARY CARDS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-success">
            <div>
                <div class="kpi-title text-success">Đang điều trị</div>
                <div class="kpi-number text-success">{{ $stats['active'] }}</div>
                <div class="text-muted" style="font-size: 11px;">Đơn thuốc đang có hiệu lực</div>
            </div>
            <i class="bi bi-heart-pulse fs-2 text-success opacity-75"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-primary">
            <div>
                <div class="kpi-title text-primary">Thành viên điều trị</div>
                <div class="kpi-number text-primary">{{ $stats['active_members'] }}</div>
                <div class="text-muted" style="font-size: 11px;">Người đang uống thuốc</div>
            </div>
            <i class="bi bi-people fs-2 text-primary opacity-75"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-secondary">
            <div>
                <div class="kpi-title text-secondary">Đã hoàn thành</div>
                <div class="kpi-number text-secondary">{{ $stats['completed'] }}</div>
                <div class="text-muted" style="font-size: 11px;">Đợt bệnh đã dứt điểm</div>
            </div>
            <i class="bi bi-check2-circle fs-2 text-secondary opacity-75"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-info">
            <div>
                <div class="kpi-title text-info">Tổng số hồ sơ đơn</div>
                <div class="kpi-number text-info">{{ $stats['total'] }}</div>
                <div class="text-muted" style="font-size: 11px;">Lưu trữ bệnh án gia đình</div>
            </div>
            <i class="bi bi-folder2-open fs-2 text-info opacity-75"></i>
        </div>
    </div>
</div>

<!-- FILTER TOOLBAR -->
<div class="filter-panel mb-3">
    <form method="GET" action="{{ route('treatment-courses.index') }}" class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
            <label class="form-label">Tìm kiếm đơn thuốc / Chẩn đoán</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên đơn, chẩn đoán, bác sĩ, tên thuốc...">
            </div>
        </div>

        <div class="col-6 col-md-3">
            <label class="form-label">Thành viên điều trị</label>
            <select name="member_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Tất cả thành viên --</option>
                @foreach ($members as $m)
                    <option value="{{ $m->id }}" {{ (string)request('member_id') === (string)$m->id ? 'selected' : '' }}>
                        {{ $m->name }} ({{ $m->relationship_label }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-md-3">
            <label class="form-label">Trạng thái đợt điều trị</label>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Tất cả trạng thái</option>
                <option value="dang_dieu_tri" {{ request('status') === 'dang_dieu_tri' ? 'selected' : '' }}>Đang điều trị</option>
                <option value="da_hoan_thanh" {{ request('status') === 'da_hoan_thanh' ? 'selected' : '' }}>Đã hoàn thành</option>
                <option value="tam_dung" {{ request('status') === 'tam_dung' ? 'selected' : '' }}>Tạm dừng</option>
            </select>
        </div>

        <div class="col-12 col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary w-100">
                <i class="bi bi-filter"></i> Lọc
            </button>
            @if(request()->hasAny(['q', 'member_id', 'status']))
                <a href="{{ route('treatment-courses.index') }}" class="btn btn-sm btn-outline-secondary" title="Đặt lại bộ lọc">
                    <i class="bi bi-x-circle"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<!-- TREATMENT COURSES TABLE -->
<div class="content-card">
    <div class="content-card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark">
            <i class="bi bi-list-check text-primary me-2"></i>DANH SÁCH ĐỢT ĐIỀU TRỊ & ĐƠN THUỐC ({{ $treatmentCourses->total() }})
        </span>
        <span class="text-muted small">Nhấn vào hàng để xem chi tiết đơn thuốc và thẩm định tương tác y khoa</span>
    </div>

    <div class="table-responsive">
        <table class="table table-erp table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 45px;" class="text-center">STT</th>
                    <th style="width: 220px;">Người bệnh</th>
                    <th>Đơn thuốc & Chẩn đoán</th>
                    <th style="width: 200px;">Bác sĩ / Bệnh viện</th>
                    <th style="width: 150px;">Thời gian</th>
                    <th style="width: 140px;" class="text-center">An toàn AI</th>
                    <th style="width: 130px;" class="text-center">Trạng thái</th>
                    <th style="width: 110px;" class="text-center">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($treatmentCourses as $index => $course)
                    @php
                        $aiRisk = $course->safety_risk_level;
                        $hasConflict = !empty($course->ai_analysis_data['has_conflict']);
                    @endphp
                    <tr>
                        <td class="text-center text-muted">
                            {{ $treatmentCourses->firstItem() + $index }}
                        </td>

                        <!-- Người bệnh -->
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if ($course->familyMember?->avatar_url)
                                    <img src="{{ $course->familyMember->avatar_url }}" alt="{{ $course->familyMember->name }}" class="rounded-circle border" style="width: 34px; height: 34px; object-fit: cover;">
                                @else
                                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 13px;">
                                        {{ mb_substr($course->familyMember?->name ?? 'U', 0, 1) }}
                                    </div>
                                @endif
                                <div>
                                    <a href="{{ route('members.show', $course->familyMember) }}" class="fw-bold text-decoration-none text-dark hover-primary" style="font-size: 13px;">
                                        {{ $course->familyMember?->name }}
                                    </a>
                                    <div class="d-flex align-items-center gap-1 mt-0.5">
                                        <span class="badge bg-light text-secondary border" style="font-size: 10px;">
                                            {{ $course->familyMember?->relationship_label }}
                                        </span>
                                        @if ($course->familyMember?->has_allergies)
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 9.5px;" title="Dị ứng: {{ $course->familyMember->allergies }}">
                                                <i class="bi bi-exclamation-triangle"></i> Dị ứng
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Đơn thuốc & Chẩn đoán -->
                        <td>
                            <div class="d-flex align-items-start justify-content-between">
                                <div>
                                    <a href="{{ route('treatment-courses.show', $course) }}" class="fw-bold text-primary text-decoration-none">
                                        {{ $course->title }}
                                    </a>
                                    @if ($course->diagnosis)
                                        <div class="text-muted small mt-0.5" style="font-size: 12px;">
                                            <i class="bi bi-clipboard-pulse text-muted me-1"></i>{{ $course->diagnosis }}
                                        </div>
                                    @endif
                                </div>
                                @if ($course->prescription_image_path)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle ms-2" title="Có đính kèm ảnh đơn thuốc">
                                        <i class="bi bi-camera"></i> Ảnh toa
                                    </span>
                                @endif
                            </div>

                            <!-- Thuốc chỉ định trong đơn -->
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                @forelse ($course->prescriptionItems->take(4) as $pItem)
                                    <span class="badge bg-light text-dark border fw-normal" style="font-size: 11px;">
                                        <i class="bi bi-capsule text-primary me-0.5"></i>
                                        <strong>{{ $pItem->medicine_name }}</strong>: {{ $pItem->dosage }}
                                    </span>
                                @empty
                                    <span class="text-muted small fst-italic">Chưa nhập thuốc</span>
                                @endforelse
                                @if ($course->prescriptionItems->count() > 4)
                                    <span class="badge bg-light text-muted border" style="font-size: 10px;">
                                        +{{ $course->prescriptionItems->count() - 4 }} loại khác
                                    </span>
                                @endif
                            </div>
                        </td>

                        <!-- Bác sĩ / Bệnh viện -->
                        <td>
                            @if ($course->hospital_doctor)
                                <div class="fw-semibold text-dark" style="font-size: 12.5px;">
                                    {{ $course->hospital_name }}
                                </div>
                                @if ($course->doctor_name)
                                    <div class="text-muted small" style="font-size: 11.5px;">
                                        <i class="bi bi-person-badge me-1"></i>{{ $course->doctor_name }}
                                    </div>
                                @endif
                            @else
                                <span class="text-muted small">— Chưa ghi nhận —</span>
                            @endif
                        </td>

                        <!-- Thời gian điều trị -->
                        <td class="small">
                            <div>
                                <span class="text-muted">Từ:</span> <strong>{{ $course->start_date ? $course->start_date->format('d/m/Y') : '—' }}</strong>
                            </div>
                            <div>
                                <span class="text-muted">Đến:</span>
                                @if ($course->end_date)
                                    <strong>{{ $course->end_date->format('d/m/Y') }}</strong>
                                @else
                                    <span class="text-primary fw-semibold">Uống dài hạn</span>
                                @endif
                            </div>
                        </td>

                        <!-- An toàn AI -->
                        <td class="text-center">
                            @if ($hasConflict || $aiRisk === 'danger')
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" title="Phát hiện nguy cơ tương tác thuốc hoặc dị ứng">
                                    <i class="bi bi-exclamation-octagon-fill me-1"></i>Nguy cơ cao
                                </span>
                            @elseif ($aiRisk === 'warning')
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1" title="Cần lưu ý liều dùng hoặc tác dụng phụ">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Lưu ý
                                </span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" title="Không phát hiện tương tác nguy hiểm">
                                    <i class="bi bi-shield-check me-1"></i>An toàn
                                </span>
                            @endif
                        </td>

                        <!-- Trạng thái -->
                        <td class="text-center">
                            @if ($course->status === 'dang_dieu_tri')
                                <span class="badge bg-success">
                                    <i class="bi bi-activity me-1"></i>Đang điều trị
                                </span>
                            @elseif ($course->status === 'da_hoan_thanh')
                                <span class="badge bg-secondary">
                                    <i class="bi bi-check-all me-1"></i>Đã xong
                                </span>
                            @else
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-pause-circle me-1"></i>Tạm dừng
                                </span>
                            @endif
                        </td>

                        <!-- Thao tác -->
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border px-2 py-1" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-1" style="font-size: 12.5px;">
                                    <li>
                                        <a class="dropdown-item py-1" href="{{ route('treatment-courses.show', $course) }}">
                                            <i class="bi bi-eye text-primary me-2"></i>Xem chi tiết đơn
                                        </a>
                                    </li>
                                    @if (auth()->user()->canManageTreatments())
                                        <li>
                                            <a class="dropdown-item py-1" href="{{ route('treatment-courses.edit', $course) }}">
                                                <i class="bi bi-pencil text-warning me-2"></i>Chỉnh sửa đơn
                                            </a>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('treatment-courses.toggle-status', $course) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="dropdown-item py-1 text-dark">
                                                    <i class="bi bi-arrow-repeat text-info me-2"></i>
                                                    {{ $course->status === 'dang_dieu_tri' ? 'Đánh dấu Hoàn thành' : 'Kích hoạt lại' }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                    @if (auth()->user()->canDeleteTreatments())
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <button type="button" class="dropdown-item py-1 text-danger" onclick="confirmDeleteCourse('{{ $course->id }}', '{{ addslashes($course->title) }}')">
                                                <i class="bi bi-trash me-2"></i>Xóa đợt điều trị
                                            </button>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 text-secondary opacity-50 d-block mb-2"></i>
                            <div class="fw-semibold fs-6">Chưa có đợt điều trị hoặc đơn thuốc nào</div>
                            <div class="small mb-3">Lưu trữ đơn thuốc bác sĩ giúp bạn theo dõi lịch uống và tránh ngộ độc tương tác thuốc.</div>
                            @if (auth()->user()->canManageTreatments())
                                <a href="{{ route('treatment-courses.create') }}" class="btn btn-sm btn-erp-primary">
                                    <i class="bi bi-plus-circle me-1"></i>Thêm đơn thuốc đầu tiên
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($treatmentCourses->hasPages())
        <div class="p-3 border-top d-flex justify-content-end">
            {{ $treatmentCourses->links() }}
        </div>
    @endif
</div>

<!-- Modal xác nhận xóa đợt điều trị -->
<div class="modal fade" id="deleteCourseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Xác nhận xóa đợt điều trị
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <p class="mb-2">Bạn có chắc chắn muốn xóa vĩnh viễn đợt điều trị:</p>
                <div class="p-2 bg-light rounded fw-bold text-danger mb-2" id="deleteCourseTitle"></div>
                <div class="alert alert-warning py-2 px-3 small mb-0">
                    <i class="bi bi-info-circle me-1"></i>Toàn bộ danh sách thuốc và lịch uống thuốc tương ứng của đợt này sẽ bị xóa cùng.
                </div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                <form id="deleteCourseForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">Xác nhận xóa</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function confirmDeleteCourse(id, title) {
    const modal = new bootstrap.Modal(document.getElementById('deleteCourseModal'));
    document.getElementById('deleteCourseTitle').textContent = title;
    document.getElementById('deleteCourseForm').action = `/treatment-courses/${id}`;
    modal.show();
}
</script>
@endpush
@endsection
