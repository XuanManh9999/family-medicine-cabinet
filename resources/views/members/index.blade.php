@extends('layouts.app')

@section('title', 'Hồ sơ Sức khỏe Thành viên Gia đình')

@section('content')
<!-- Header và Quick Actions -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1 fw-bold text-dark d-flex align-items-center gap-2">
            <i class="bi bi-people-fill text-primary"></i> Hồ sơ Sức khỏe Thành viên Gia đình
            @if (!auth()->user()->canManageMembers())
                <span class="badge bg-secondary-subtle text-secondary border fw-normal" style="font-size: 11.5px;">
                    <i class="bi bi-eye me-1"></i>Chế độ xem
                </span>
            @else
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal" style="font-size: 11.5px;">
                    <i class="bi bi-shield-check me-1"></i>Quản trị sổ hộ gia đình
                </span>
            @endif
        </h1>
        <div class="text-muted small">
            Quản lý hồ sơ bệnh án điện tử, theo dõi tiền sử dị ứng thuốc và bệnh nền mạn tính của từng người thân.
        </div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="In danh sách hồ sơ y tế gia đình">
            <i class="bi bi-printer me-1"></i> In tóm tắt y tế
        </button>

        @if (auth()->user()->canManageMembers())
            <a href="{{ route('members.create') }}" class="btn btn-erp-primary btn-sm">
                <i class="bi bi-person-plus-fill me-1"></i> Thêm thành viên mới
            </a>
        @endif
    </div>
</div>

<!-- 5 Thẻ KPI Thống kê Sức khỏe Gia đình -->
<div class="row g-2 mb-3">
    <!-- 1. Tổng thành viên -->
    <div class="col-6 col-md">
        <a href="{{ route('members.index') }}" class="text-decoration-none">
            <div class="content-card h-100 mb-0 {{ $healthFilter === 'all' && !request('relationship') ? 'border-primary shadow-sm' : '' }}">
                <div class="content-card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">Tổng thành viên</span>
                        <i class="bi bi-people text-primary fs-5 opacity-75"></i>
                    </div>
                    <div class="fs-4 fw-bold text-primary mt-1">{{ $stats['total'] }} <small class="text-muted fw-normal" style="font-size: 11px;">người</small></div>
                </div>
            </div>
        </a>
    </div>

    <!-- 2. Người cao tuổi -->
    <div class="col-6 col-md">
        <a href="{{ route('members.index', ['health' => 'elderly', 'q' => request('q')]) }}" class="text-decoration-none">
            <div class="content-card h-100 mb-0 {{ $healthFilter === 'elderly' ? 'border-info shadow-sm' : '' }}">
                <div class="content-card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">Người cao tuổi (&ge;60)</span>
                        <i class="bi bi-person-heart text-info fs-5 opacity-75"></i>
                    </div>
                    <div class="fs-4 fw-bold text-info mt-1">{{ $stats['elderly'] }} <small class="text-muted fw-normal" style="font-size: 11px;">người</small></div>
                </div>
            </div>
        </a>
    </div>

    <!-- 3. Trẻ em -->
    <div class="col-6 col-md">
        <a href="{{ route('members.index', ['health' => 'children', 'q' => request('q')]) }}" class="text-decoration-none">
            <div class="content-card h-100 mb-0 {{ $healthFilter === 'children' ? 'border-success shadow-sm' : '' }}">
                <div class="content-card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">Trẻ nhỏ (&lt;16 tuổi)</span>
                        <i class="bi bi-balloon-heart text-success fs-5 opacity-75"></i>
                    </div>
                    <div class="fs-4 fw-bold text-success mt-1">{{ $stats['children'] }} <small class="text-muted fw-normal" style="font-size: 11px;">bé</small></div>
                </div>
            </div>
        </a>
    </div>

    <!-- 4. Có dị ứng thuốc -->
    <div class="col-6 col-md">
        <a href="{{ route('members.index', ['health' => 'allergies', 'q' => request('q')]) }}" class="text-decoration-none">
            <div class="content-card h-100 mb-0 {{ $healthFilter === 'allergies' ? 'border-danger shadow-sm' : '' }}">
                <div class="content-card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-danger fw-semibold">Có dị ứng thuốc</span>
                        <i class="bi bi-shield-slash text-danger fs-5 opacity-75"></i>
                    </div>
                    <div class="fs-4 fw-bold text-danger mt-1">{{ $stats['with_allergies'] }} <small class="text-muted fw-normal" style="font-size: 11px;">người</small></div>
                </div>
            </div>
        </a>
    </div>

    <!-- 5. Có bệnh nền -->
    <div class="col-6 col-md">
        <a href="{{ route('members.index', ['health' => 'diseases', 'q' => request('q')]) }}" class="text-decoration-none">
            <div class="content-card h-100 mb-0 {{ $healthFilter === 'diseases' ? 'border-warning shadow-sm' : '' }}">
                <div class="content-card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-warning-emphasis fw-semibold">Bệnh mạn tính</span>
                        <i class="bi bi-heart-pulse text-warning fs-5 opacity-75"></i>
                    </div>
                    <div class="fs-4 fw-bold text-warning-emphasis mt-1">{{ $stats['with_diseases'] }} <small class="text-muted fw-normal" style="font-size: 11px;">người</small></div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Thanh Lọc & Tìm kiếm Thành viên -->
<div class="content-card mb-3">
    <div class="content-card-body p-3">
        <form method="GET" action="{{ route('members.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label for="q" class="form-label small fw-semibold text-muted mb-1">Tìm kiếm thành viên</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input id="q" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Họ tên, dị ứng, bệnh lý nền...">
                </div>
            </div>

            <div class="col-6 col-md-3">
                <label for="relationship" class="form-label small fw-semibold text-muted mb-1">Mối quan hệ</label>
                <select id="relationship" name="relationship" class="form-select form-select-sm">
                    <option value="">-- Tất cả quan hệ --</option>
                    @foreach (['ong_ba' => 'Ông / Bà', 'bo_me' => 'Bố / Mẹ', 'vo_chong' => 'Vợ / Chồng', 'con_cai' => 'Con cái', 'ban_than' => 'Bản thân', 'khac' => 'Người thân khác'] as $k => $lbl)
                        <option value="{{ $k }}" @selected(request('relationship') === $k)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-3">
                <label for="health" class="form-label small fw-semibold text-muted mb-1">Tình trạng sức khỏe</label>
                <select id="health" name="health" class="form-select form-select-sm">
                    <option value="all" @selected($healthFilter === 'all')>-- Tất cả tình trạng --</option>
                    <option value="allergies" @selected($healthFilter === 'allergies')>⚠️ Có tiền sử dị ứng thuốc</option>
                    <option value="diseases" @selected($healthFilter === 'diseases')>🩺 Có bệnh lý mạn tính</option>
                    <option value="elderly" @selected($healthFilter === 'elderly')>👴 Người cao tuổi (&ge;60)</option>
                    <option value="children" @selected($healthFilter === 'children')>🧒 Trẻ em (&lt;16 tuổi)</option>
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex gap-1">
                <button class="btn btn-erp-primary btn-sm flex-grow-1" type="submit">
                    <i class="bi bi-funnel me-1"></i>Lọc
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('members.index') }}" title="Xóa bộ lọc">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Danh sách Thẻ Hồ sơ Y tế (Grid of Health Cards) -->
<div class="row g-3">
    @forelse ($members as $m)
        <div class="col-12 col-md-6 col-xl-6">
            <div class="content-card h-100 mb-0 shadow-sm d-flex flex-column border-start {{ $m->has_allergies ? 'border-start-danger' : ($m->has_underlying_diseases ? 'border-start-warning' : 'border-start-primary') }}" style="border-left-width: 4px !important;">
                <div class="content-card-body p-3 flex-grow-1">
                    <!-- Top row: Avatar, Name, Relationship, Blood Type -->
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <img src="{{ $m->avatar_url }}" alt="{{ $m->name }}" class="rounded-circle border shadow-sm flex-shrink-0" style="width: 58px; height: 58px; object-fit: cover;">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="fw-bold text-dark mb-0">
                                        <a href="{{ route('members.show', $m) }}" class="text-dark text-decoration-none hover-primary">
                                            {{ $m->name }}
                                        </a>
                                    </h5>
                                    <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                        <span class="badge bg-primary-subtle text-primary" style="font-size: 11px;">
                                            <i class="bi bi-people me-1"></i>{{ $m->relationship_label }}
                                        </span>
                                        <span class="badge bg-light text-muted border" style="font-size: 11px;">
                                            {{ $m->gender_label }} · {{ $m->age ? $m->age . ' tuổi' : 'Chưa có năm sinh' }}
                                        </span>
                                        @if ($m->blood_type)
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 11px;">
                                                <i class="bi bi-droplet-fill me-1"></i>Nhóm máu {{ $m->blood_type }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                @if ($m->user_id)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px;" title="Tài khoản đã liên kết ứng dụng">
                                        <i class="bi bi-phone me-1"></i>Đã kích hoạt App
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size: 10px;" title="Thành viên phụ thuộc">
                                        Cần chăm sóc
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- CẢNH BÁO DỊ ỨNG THUỐC (Mục sống còn trong y tế) -->
                    @if ($m->has_allergies)
                        <div class="alert alert-danger py-2 px-3 small mb-2 border-0 bg-danger bg-opacity-10">
                            <div class="fw-bold text-danger d-flex align-items-center gap-1">
                                <i class="bi bi-exclamation-octagon-fill"></i> TIỀN SỬ DỊ ỨNG THUỐC:
                            </div>
                            <div class="text-danger-emphasis mt-1 fw-semibold" style="font-size: 12px;">
                                {{ $m->allergies }}
                            </div>
                        </div>
                    @endif

                    <!-- BỆNH LÝ MẠN TÍNH & BỆNH NỀN -->
                    @if ($m->has_underlying_diseases)
                        <div class="alert alert-warning py-2 px-3 small mb-2 border-0 bg-warning bg-opacity-10">
                            <div class="fw-bold text-warning-emphasis d-flex align-items-center gap-1">
                                <i class="bi bi-heart-pulse-fill"></i> BỆNH LÝ NỀN / MẠN TÍNH:
                            </div>
                            <div class="text-dark mt-1" style="font-size: 12px;">
                                {{ $m->underlying_diseases }}
                            </div>
                        </div>
                    @endif

                    @if (!$m->has_allergies && !$m->has_underlying_diseases)
                        <div class="p-2 small text-muted bg-light rounded mb-2" style="font-size: 12px;">
                            <i class="bi bi-check-circle-fill text-success me-1"></i> Sức khỏe bình thường, chưa ghi nhận tiền sử dị ứng hoặc bệnh mạn tính.
                        </div>
                    @endif

                    <!-- Tóm tắt số liệu y tế của thành viên này -->
                    <div class="row g-2 text-center pt-2 mt-auto border-top">
                        <div class="col-4">
                            <div class="text-muted small" style="font-size: 11px;">Đơn điều trị</div>
                            <strong class="text-dark">{{ $m->treatment_courses_count }}</strong>
                        </div>
                        <div class="col-4 border-start border-end">
                            <div class="text-muted small" style="font-size: 11px;">Thuốc đang uống</div>
                            <strong class="text-primary">{{ $m->medication_schedules_count }}</strong>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small" style="font-size: 11px;">Đợt khám/tiền</div>
                            <strong class="text-success">{{ $m->expenses_count }} đợt</strong>
                        </div>
                    </div>
                </div>

                <!-- Footer hành động -->
                <div class="content-card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center border-top">
                    <a href="{{ route('members.show', $m) }}" class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size: 12px;">
                        <i class="bi bi-file-earmark-medical me-1"></i>Xem hồ sơ bệnh án
                    </a>

                    <div class="d-flex gap-1">
                        @if (auth()->user()->canEditMember($m))
                            <a href="{{ route('members.edit', $m) }}" class="btn btn-sm btn-outline-secondary py-1 px-2 d-inline-flex align-items-center gap-1" title="Chỉnh sửa hồ sơ" style="font-size: 12px;">
                                <i class="bi bi-pencil"></i>
                                @if (!auth()->user()->canManageMembers() && (int)$m->user_id === (int)auth()->id())
                                    <span class="d-none d-sm-inline">Sửa hồ sơ của tôi</span>
                                @endif
                            </a>
                        @endif

                        @if (auth()->user()->canManageMembers())
                            <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 btn-delete-member" title="Xóa thành viên" style="font-size: 12px;"
                                    data-action="{{ route('members.destroy', $m) }}"
                                    data-name="{{ $m->name }}"
                                    data-rel="{{ $m->relationship_label }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="content-card text-center py-5">
                <i class="bi bi-people fs-1 text-muted d-block mb-2"></i>
                <h5 class="fw-semibold">Không tìm thấy thành viên phù hợp</h5>
                <p class="text-muted small">Hãy thử tìm kiếm với từ khóa khác hoặc thêm thành viên mới vào gia đình.</p>
                @if (auth()->user()->canManageMembers())
                    <a href="{{ route('members.create') }}" class="btn btn-erp-primary btn-sm mt-2">
                        <i class="bi bi-person-plus-fill me-1"></i>Thêm thành viên ngay
                    </a>
                @endif
            </div>
        </div>
    @endforelse
</div>

<!-- MODAL XÁC NHẬN XÓA THÀNH VIÊN -->
<div class="modal fade" id="deleteMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 shadow">
            <div class="modal-body p-4 text-center">
                <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex p-3 text-danger mb-3">
                    <i class="bi bi-person-x-fill fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Xác nhận xóa thành viên?</h5>
                <p class="text-muted small mb-3">
                    Bạn có chắc chắn muốn xóa hồ sơ của thành viên này khỏi gia đình? Toàn bộ lịch uống thuốc và đơn điều trị liên quan sẽ bị ảnh hưởng.
                </p>
                <div class="bg-light p-3 rounded text-start mb-3 border">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Họ và tên:</span>
                        <strong class="text-dark small" id="delMemberName">—</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Mối quan hệ:</span>
                        <span class="text-dark small fw-semibold" id="delMemberRel">—</span>
                    </div>
                </div>
                <form id="deleteMemberForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light border flex-grow-1" data-bs-dismiss="modal">Hủy bỏ</button>
                        <button type="submit" class="btn btn-danger flex-grow-1">
                            <i class="bi bi-trash me-1"></i>Xác nhận xóa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.btn-delete-member').forEach(btn => {
        btn.addEventListener('click', function () {
            const action = this.dataset.action;
            const name = this.dataset.name;
            const rel = this.dataset.rel;

            document.getElementById('deleteMemberForm').action = action;
            document.getElementById('delMemberName').innerText = name;
            document.getElementById('delMemberRel').innerText = rel;

            const modal = new bootstrap.Modal(document.getElementById('deleteMemberModal'));
            modal.show();
        });
    });
</script>
@endpush
