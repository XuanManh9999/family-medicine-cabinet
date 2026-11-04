@extends('admin.layouts.admin')

@section('title', 'Chi tiết Hộ Gia đình: ' . $family->name)

@section('content')
<!-- BREADCRUMB & HEADER -->
<div class="mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1 small">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Tổng quan</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.families.index') }}" class="text-decoration-none">Hộ gia đình</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $family->name }}</li>
        </ol>
    </nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-house-heart text-danger fs-4"></i>
                <span>{{ $family->name }}</span>
                <span class="badge bg-light text-primary border font-monospace fs-6">{{ $family->code }}</span>
            </h5>
            <div class="text-muted small mt-1">
                Địa chỉ: {{ $family->address ?: 'Chưa cập nhật' }} | 
                Ngày tạo: <strong>{{ $family->created_at ? $family->created_at->format('d/m/Y H:i') : '—' }}</strong>
            </div>
        </div>
        <a href="{{ route('admin.families.index') }}" class="btn btn-sm btn-outline-secondary shadow-sm">
            <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
        </a>
    </div>
</div>

<!-- BỘ LỌC THỜI GIAN CHO ĐƠN ĐIỀU TRỊ & CHI PHÍ -->
<div class="admin-card mb-3">
    <div class="admin-card-body p-2 px-3">
        <form method="GET" action="{{ route('admin.families.show', $family) }}" class="row g-2 align-items-center">
            @include('admin.partials.date-filter', ['dateFilterLabel' => 'Đơn thuốc & chi phí'])
            <div class="col-md-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary px-3">Lọc</button>
                @if ($dateFilter->isActive())
                    <a href="{{ route('admin.families.show', $family) }}" class="btn btn-sm btn-outline-secondary" title="Xóa bộ lọc">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <!-- CỘT TRÁI: THÀNH VIÊN & TÀI KHOẢN -->
    <div class="col-lg-6">
        <!-- Danh sách tài khoản người dùng -->
        <div class="admin-card mb-3">
            <div class="admin-card-header bg-light">
                <span class="text-dark"><i class="bi bi-person-badge text-primary me-2"></i>Tài khoản Đăng nhập ({{ $family->users->count() }})</span>
            </div>
            <div class="table-responsive">
                <table class="table table-admin mb-0">
                    <thead>
                        <tr>
                            <th>Tên & Email</th>
                            <th>Điện thoại</th>
                            <th>Vai trò</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($family->users as $u)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $u->name }}</div>
                                    <div class="text-muted small">{{ $u->email }}</div>
                                </td>
                                <td class="small">{{ $u->phone ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $u->role_badge_class }}">
                                        {{ $u->role_name }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-3 text-muted">Không có tài khoản nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Danh sách hồ sơ sức khỏe thành viên -->
        <div class="admin-card">
            <div class="admin-card-header bg-light">
                <span class="text-dark"><i class="bi bi-people text-success me-2"></i>Hồ sơ Sức khỏe Thành viên ({{ $family->members->count() }})</span>
            </div>
            <div class="table-responsive">
                <table class="table table-admin mb-0">
                    <thead>
                        <tr>
                            <th>Họ và tên</th>
                            <th>Quan hệ / Tuổi</th>
                            <th>Bệnh nền & Dị ứng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($family->members as $mem)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $mem->name }}</div>
                                    <span class="badge bg-light text-dark border" style="font-size: 10px;">Nhóm máu: {{ $mem->blood_type ?: 'Chưa rõ' }}</span>
                                </td>
                                <td>
                                    <div class="small fw-semibold">{{ $mem->relationship_label }}</div>
                                    <div class="text-muted" style="font-size: 11px;">{{ $mem->age ? $mem->age . ' tuổi' : '—' }}</div>
                                </td>
                                <td class="small">
                                    @if ($mem->underlying_diseases)
                                        <div class="text-danger mb-0.5"><i class="bi bi-heart-pulse me-1"></i>{{ Str::limit($mem->underlying_diseases, 40) }}</div>
                                    @endif
                                    @if ($mem->allergies)
                                        <div class="text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>Dị ứng: {{ Str::limit($mem->allergies, 40) }}</div>
                                    @endif
                                    @if (!$mem->underlying_diseases && !$mem->allergies)
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-3 text-muted">Chưa có hồ sơ thành viên nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- CỘT PHẢI: TỦ THUỐC & ĐỢT ĐIỀU TRỊ -->
    <div class="col-lg-6">
        <!-- Tủ thuốc gia đình -->
        <div class="admin-card mb-3">
            <div class="admin-card-header bg-light">
                <span class="text-dark"><i class="bi bi-box-seam text-danger me-2"></i>Tủ thuốc Gia đình ({{ $family->cabinetItems->count() }} loại)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-admin mb-0">
                    <thead>
                        <tr>
                            <th>Tên thuốc</th>
                            <th>Hạn dùng (EXP)</th>
                            <th class="text-center">Số lượng</th>
                            <th>Vị trí</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($family->cabinetItems as $item)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $item->display_name }}</div>
                                    <div class="text-muted" style="font-size: 11px;">Lô: {{ $item->batch_number ?: 'N/A' }}</div>
                                </td>
                                <td class="small">
                                    <strong>{{ $item->expiry_date ? $item->expiry_date->format('d/m/Y') : '—' }}</strong>
                                    <div><span class="badge {{ $item->status_badge_class }}" style="font-size: 10px;">{{ $item->status_label }}</span></div>
                                </td>
                                <td class="text-center fw-bold">
                                    {{ $item->quantity }} {{ $item->unit }}
                                </td>
                                <td class="small text-muted">{{ $item->storage_location ?: 'Ngăn chính' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-3 text-muted">Tủ thuốc đang trống.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Đợt điều trị ngoại trú -->
        <div class="admin-card">
            <div class="admin-card-header bg-light">
                <span class="text-dark"><i class="bi bi-journal-medical text-warning me-2"></i>Đơn thuốc & Đợt điều trị ({{ $family->treatmentCourses->count() }})</span>
            </div>
            <div class="table-responsive">
                <table class="table table-admin mb-0">
                    <thead>
                        <tr>
                            <th>Tiêu đề / Bệnh nhân</th>
                            <th>Chẩn đoán</th>
                            <th>Thời gian</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($family->treatmentCourses as $tc)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $tc->title }}</div>
                                    <div class="text-muted small">Người bệnh: <strong>{{ $tc->familyMember?->name ?: 'Gia đình' }}</strong></div>
                                </td>
                                <td class="small text-muted text-truncate" style="max-width: 140px;">{{ $tc->diagnosis ?: '—' }}</td>
                                <td class="small text-muted">
                                    {{ $tc->start_date ? $tc->start_date->format('d/m/Y') : '—' }}
                                </td>
                                <td>
                                    <span class="badge {{ $tc->status_badge_class }}" style="font-size: 10px;">{{ $tc->status_label }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-3 text-muted">
                                    {{ $dateFilter->isActive() ? 'Không có đợt điều trị nào trong khoảng thời gian đã chọn.' : 'Chưa có đợt điều trị nào.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Chi phí y tế -->
        <div class="admin-card mt-3">
            <div class="admin-card-header bg-light d-flex justify-content-between align-items-center">
                <span class="text-dark"><i class="bi bi-cash-coin text-success me-2"></i>Chi phí Y tế ({{ $expenseCount }})</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                    Tổng: {{ number_format($expenseTotal, 0, ',', '.') }} đ
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-admin mb-0">
                    <thead>
                        <tr>
                            <th>Nội dung chi</th>
                            <th>Người thụ hưởng</th>
                            <th>Ngày mua</th>
                            <th class="text-end">Số tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expenses as $expense)
                            <tr>
                                <td class="fw-semibold">{{ $expense->title }}</td>
                                <td class="small text-muted">{{ $expense->familyMember?->name ?: 'Cả gia đình' }}</td>
                                <td class="small text-muted">{{ $expense->purchase_date ? $expense->purchase_date->format('d/m/Y') : '—' }}</td>
                                <td class="text-end fw-bold text-success">{{ number_format((float) $expense->amount, 0, ',', '.') }} đ</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-3 text-muted">
                                    {{ $dateFilter->isActive() ? 'Không có khoản chi nào trong khoảng thời gian đã chọn.' : 'Chưa có khoản chi phí nào.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($expenseCount > $expenses->count())
                <div class="p-2 text-center text-muted small border-top">Hiển thị {{ $expenses->count() }} khoản gần nhất / {{ $expenseCount }} khoản</div>
            @endif
        </div>
    </div>
</div>
@endsection
