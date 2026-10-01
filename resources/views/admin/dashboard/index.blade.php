@extends('admin.layouts.admin')

@section('title', 'Bảng điều khiển Quản trị Toàn hệ thống')

@section('content')
<!-- HEADER -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-speedometer2 text-danger fs-4"></i>
            Tổng quan Hệ thống MedCabinet Quản trị
        </h5>
        <div class="text-muted small">
            Giám sát vận hành thời gian thực, quản lý cơ sở dữ liệu Dược thư quốc gia, tài khoản người dùng và mạng lưới hộ gia đình.
        </div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="POST" action="{{ route('admin.scan-alerts') }}" class="d-inline" onsubmit="return confirm('Kích hoạt quét toàn diện hạn sử dụng và tồn kho cho toàn bộ hộ gia đình trong hệ thống?')">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-warning text-dark shadow-sm fw-semibold">
                <i class="bi bi-bell-fill text-warning me-1"></i>Quét cảnh báo toàn hệ thống
            </button>
        </form>
        <a href="{{ route('admin.medicines.create') }}" class="btn btn-sm btn-primary shadow-sm">
            <i class="bi bi-plus-circle me-1"></i>Thêm thuốc vào Dược thư
        </a>
        <a href="{{ route('admin.categories.index') }}" class="btn btn-sm btn-outline-secondary shadow-sm">
            <i class="bi bi-tags me-1"></i>Nhóm dược lý
        </a>
    </div>
</div>

<!-- SYSTEM HEALTH STATUS ROW -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="p-2 px-3 border rounded bg-white d-flex align-items-center justify-content-between shadow-sm">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-circle bg-success p-1" style="width: 10px; height: 10px; display: inline-block;"></span>
                <div>
                    <div class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600;">Cơ sở dữ liệu</div>
                    <div class="fw-bold text-dark small">{{ strtoupper($systemHealth['db_driver']) }} {{ $systemHealth['db_status'] ? 'Online' : 'Offline' }}</div>
                </div>
            </div>
            <i class="bi bi-database-check fs-4 text-success opacity-75"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-2 px-3 border rounded bg-white d-flex align-items-center justify-content-between shadow-sm">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-circle bg-primary p-1" style="width: 10px; height: 10px; display: inline-block;"></span>
                <div>
                    <div class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600;">Môi trường Runtime</div>
                    <div class="fw-bold text-dark small">PHP {{ $systemHealth['php_version'] }} / Lv {{ $systemHealth['laravel_version'] }}</div>
                </div>
            </div>
            <i class="bi bi-cpu fs-4 text-primary opacity-75"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-2 px-3 border rounded bg-white d-flex align-items-center justify-content-between shadow-sm">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-circle {{ $systemHealth['storage_linked'] ? 'bg-success' : 'bg-warning' }} p-1" style="width: 10px; height: 10px; display: inline-block;"></span>
                <div>
                    <div class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600;">Bộ nhớ Storage</div>
                    <div class="fw-bold text-dark small">{{ $systemHealth['storage_linked'] ? 'Đã liên kết (Link)' : 'Chưa Symlink' }}</div>
                </div>
            </div>
            <i class="bi bi-folder-check fs-4 text-info opacity-75"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="p-2 px-3 border rounded bg-white d-flex align-items-center justify-content-between shadow-sm">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-circle {{ $systemHealth['gemini_configured'] ? 'bg-success' : 'bg-secondary' }} p-1" style="width: 10px; height: 10px; display: inline-block;"></span>
                <div>
                    <div class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600;">AI Dược sĩ Trợ lý</div>
                    <div class="fw-bold text-dark small">{{ $systemHealth['gemini_configured'] ? 'Gemini 1.5 Flash' : 'Chưa cấu hình Key' }}</div>
                </div>
            </div>
            <i class="bi bi-robot fs-4 text-warning opacity-75"></i>
        </div>
    </div>
</div>

<!-- KPI CARDS ROW -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-kpi-card border-start border-4 border-primary">
            <div>
                <div class="admin-kpi-title">Hộ Gia đình</div>
                <div class="admin-kpi-value text-primary">{{ number_format($totalFamilies) }}</div>
            </div>
            <i class="bi bi-houses fs-1 text-primary opacity-75"></i>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-kpi-card border-start border-4 border-info">
            <div>
                <div class="admin-kpi-title">Tài khoản User</div>
                <div class="admin-kpi-value text-info">{{ number_format($totalUsers) }}</div>
            </div>
            <i class="bi bi-people fs-1 text-info opacity-75"></i>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-kpi-card border-start border-4 border-success">
            <div>
                <div class="admin-kpi-title">Hồ sơ Sức khỏe</div>
                <div class="admin-kpi-value text-success">{{ number_format($totalMembers) }}</div>
            </div>
            <i class="bi bi-heart-pulse fs-1 text-success opacity-75"></i>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-kpi-card border-start border-4 border-danger">
            <div>
                <div class="admin-kpi-title">Dược thư Chuẩn</div>
                <div class="admin-kpi-value text-danger">{{ number_format($totalMedicines) }}</div>
            </div>
            <i class="bi bi-capsule fs-1 text-danger opacity-75"></i>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-kpi-card border-start border-4 border-warning">
            <div>
                <div class="admin-kpi-title">Đơn Điều trị</div>
                <div class="admin-kpi-value text-warning-emphasis">{{ number_format($totalTreatmentCourses) }}</div>
            </div>
            <i class="bi bi-journal-medical fs-1 text-warning opacity-75"></i>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-kpi-card border-start border-4 border-dark">
            <div>
                <div class="admin-kpi-title">Lô thuốc trong tủ</div>
                <div class="admin-kpi-value text-dark">{{ number_format($totalCabinetItems) }}</div>
            </div>
            <i class="bi bi-box-seam fs-1 text-secondary opacity-75"></i>
        </div>
    </div>
</div>

<!-- INTERACTIVE CHARTS ROW -->
<div class="row g-3 mb-3">
    <!-- CHART 1: XU HƯỚNG GIA ĐÌNH ĐĂNG KÝ THEO THÁNG (LINE CHART) -->
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-graph-up text-primary me-2"></i>Tốc độ Tăng trưởng Hộ gia đình (Năm {{ $currentYear }})
                </span>
                <span class="badge bg-primary-subtle text-primary">Theo tháng</span>
            </div>
            <div class="admin-card-body p-3">
                <div style="position: relative; height: 260px;">
                    <canvas id="monthlyGrowthChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- CHART 2: CƠ CẤU VAI TRÒ NGƯỜI DÙNG (DOUGHNUT CHART) -->
    <div class="col-lg-3">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <i class="bi bi-pie-chart text-info me-2"></i>Cơ cấu Vai trò Người dùng
            </div>
            <div class="admin-card-body p-3">
                <div style="position: relative; height: 260px;">
                    <canvas id="userRolesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- CHART 3: PHÂN BỐ THUỐC THEO NHÓM DƯỢC LÝ (BAR CHART) -->
    <div class="col-lg-3">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <i class="bi bi-bar-chart text-success me-2"></i>Thuốc theo Nhóm Dược lý
            </div>
            <div class="admin-card-body p-3">
                <div style="position: relative; height: 260px;">
                    <canvas id="categoryDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DATA LISTS ROW -->
<div class="row g-3 mb-3">
    <!-- CỘT TRÁI: GIA ĐÌNH ĐĂNG KÝ MỚI NHẤT -->
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="admin-card-header">
                <span class="text-dark"><i class="bi bi-houses text-success me-2"></i>Hộ Gia đình mới đăng ký gần đây</span>
                <a href="{{ route('admin.families.index') }}" class="small text-decoration-none">Xem toàn bộ ({{ $totalFamilies }})</a>
            </div>
            <div class="table-responsive">
                <table class="table table-admin mb-0">
                    <thead>
                        <tr>
                            <th>Mã GD</th>
                            <th>Tên Gia đình</th>
                            <th class="text-center">Thành viên</th>
                            <th class="text-center">Thuốc tủ</th>
                            <th>Ngày tạo</th>
                            <th class="text-end">Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentFamilies as $f)
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">{{ $f->code }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.families.show', $f) }}" class="fw-bold text-decoration-none text-primary">
                                        {{ $f->name }}
                                    </a>
                                    <div class="text-muted small text-truncate" style="max-width: 220px;">{{ $f->address ?: 'Chưa cập nhật địa chỉ' }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info-subtle text-info">{{ $f->members_count }} người</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-subtle text-secondary">{{ $f->cabinet_items_count }} lô</span>
                                </td>
                                <td class="small text-muted">
                                    {{ $f->created_at ? $f->created_at->format('d/m/Y') : '—' }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.families.show', $f) }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px;">
                                        Xem <i class="bi bi-chevron-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">Chưa có hộ gia đình nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- CỘT PHẢI: NGƯỜI DÙNG & DƯỢC PHẨM MASTER DATA MỚI -->
    <div class="col-lg-5">
        <!-- Người dùng mới đăng ký -->
        <div class="admin-card mb-3">
            <div class="admin-card-header">
                <span class="text-dark"><i class="bi bi-people text-info me-2"></i>Tài khoản người dùng mới</span>
                <a href="{{ route('admin.users.index') }}" class="small text-decoration-none">Quản lý ({{ $totalUsers }})</a>
            </div>
            <div class="table-responsive">
                <table class="table table-admin mb-0">
                    <thead>
                        <tr>
                            <th>Tên & Email</th>
                            <th>Vai trò</th>
                            <th>Gia đình</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentUsers as $u)
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">{{ $u->name }}</div>
                                    <div class="text-muted" style="font-size: 11px;">{{ $u->email }}</div>
                                </td>
                                <td>
                                    @if ($u->isSystemAdmin())
                                        <span class="badge bg-danger">Super Admin</span>
                                    @elseif ($u->isFamilyAdmin())
                                        <span class="badge bg-primary">Chủ hộ</span>
                                    @else
                                        <span class="badge bg-secondary">Thành viên</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="small text-truncate d-inline-block text-muted" style="max-width: 120px;">
                                        {{ $u->family?->name ?: 'Chưa gán' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-3 text-muted">Chưa có người dùng nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Thuốc Master Data mới -->
        <div class="admin-card">
            <div class="admin-card-header">
                <span class="text-dark"><i class="bi bi-capsule text-danger me-2"></i>Dược thư Master Data mới thêm</span>
                <a href="{{ route('admin.medicines.index') }}" class="small text-decoration-none">Dược thư ({{ $totalMedicines }})</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($recentMedicines as $med)
                    <div class="list-group-item p-2 d-flex justify-content-between align-items-center">
                        <div>
                            <a href="{{ route('admin.medicines.edit', $med) }}" class="fw-bold text-decoration-none text-dark small">
                                {{ $med->name }}
                            </a>
                            <div class="text-muted" style="font-size: 11px;">
                                {{ $med->active_ingredient }} | <span class="text-primary">{{ $med->category?->name ?: 'Chung' }}</span>
                            </div>
                        </div>
                        <a href="{{ route('admin.medicines.edit', $med) }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px;">
                            Sửa
                        </a>
                    </div>
                @empty
                    <div class="p-3 text-center text-muted">Chưa có thuốc trong Dược thư.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Biểu đồ Tăng trưởng Hộ gia đình 12 tháng (Line Chart)
        const ctxMonthly = document.getElementById('monthlyGrowthChart');
        if (ctxMonthly) {
            const months = ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'];
            const monthlyData = @json($monthlyFamiliesData);

            new Chart(ctxMonthly, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Hộ gia đình mới',
                        data: monthlyData,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: '#ffffff',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ' Đăng ký: ' + context.parsed.y + ' gia đình';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                stepSize: 1
                            },
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        // 2. Biểu đồ Cơ cấu Vai trò Người dùng (Doughnut Chart)
        const ctxRoles = document.getElementById('userRolesChart');
        if (ctxRoles) {
            const roleLabels = @json(array_keys($userRoleCounts));
            const roleValues = @json(array_values($userRoleCounts));

            new Chart(ctxRoles, {
                type: 'doughnut',
                data: {
                    labels: roleLabels,
                    datasets: [{
                        data: roleValues,
                        backgroundColor: [
                            '#ef4444', // Super Admin - Đỏ
                            '#2563eb', // Chủ hộ - Xanh dương
                            '#64748b', // Thành viên - Xám đậm
                            '#10b981'  // Bác sĩ / Chăm sóc - Xanh lá
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: { size: 11 }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        }

        // 3. Biểu đồ Phân bố Thuốc theo Nhóm Dược lý (Bar Chart)
        const ctxCat = document.getElementById('categoryDistributionChart');
        if (ctxCat) {
            const catLabels = @json(array_slice($categoryChartLabels, 0, 6));
            const catValues = @json(array_slice($categoryChartCounts, 0, 6));

            new Chart(ctxCat, {
                type: 'bar',
                data: {
                    labels: catLabels,
                    datasets: [{
                        label: 'Số lượng thuốc',
                        data: catValues,
                        backgroundColor: '#10b981',
                        borderRadius: 4,
                        barThickness: 16
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: '#f1f5f9' }
                        },
                        y: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 10.5 }
                            }
                        }
                    }
                }
            });
        }
    });
</script>
@endpush
