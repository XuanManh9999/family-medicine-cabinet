@extends('layouts.app')

@section('title', 'Thống kê Chi phí thuốc & Viện phí gia đình - Tủ thuốc Gia đình')

@section('content')
<!-- HEADER & QUICK ACTIONS -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-cash-stack text-success fs-4"></i>
            Thống kê Chi phí thuốc & Viện phí gia đình
            @if (!auth()->user()->canDeleteExpenses())
                <span class="badge bg-secondary-subtle text-secondary border fw-normal" style="font-size: 11.5px;">
                    <i class="bi bi-wallet2 me-1"></i>Sổ ghi chép chi tiêu
                </span>
            @else
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal" style="font-size: 11.5px;">
                    <i class="bi bi-shield-check me-1"></i>Quản lý tài chính hộ
                </span>
            @endif
        </h5>
        <div class="text-muted small">
            Theo dõi chi tiêu mua thuốc, viện phí khám chữa bệnh, phân tích xu hướng chi tiêu y tế và cơ cấu ngân sách gia đình.
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('expenses.export-csv') }}" class="btn btn-sm btn-outline-success shadow-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Xuất Excel CSV
        </a>
        <a href="{{ route('expenses.create') }}" class="btn btn-erp-primary d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-circle"></i>
            <span>Thêm khoản chi mới</span>
        </a>
    </div>
</div>

<!-- KPI SUMMARY STATS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-success">
            <div>
                <div class="kpi-title text-success">Chi tiêu năm {{ $selectedYear }}</div>
                <div class="kpi-number text-success">{{ number_format($totalYear, 0, ',', '.') }} <span class="fs-6 fw-normal">đ</span></div>
                <div class="text-muted" style="font-size: 11px;">Tổng tiền thuốc trong năm</div>
            </div>
            <i class="bi bi-currency-dollar fs-2 text-success opacity-75"></i>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-primary">
            <div>
                <div class="kpi-title text-primary">Chi tiêu tháng này</div>
                <div class="kpi-number text-primary">{{ number_format($totalMonth, 0, ',', '.') }} <span class="fs-6 fw-normal">đ</span></div>
                <div class="text-muted" style="font-size: 11px;">Hóa đơn tháng {{ \Carbon\Carbon::now()->format('m/Y') }}</div>
            </div>
            <i class="bi bi-calendar2-month fs-2 text-primary opacity-75"></i>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-warning">
            <div>
                <div class="kpi-title text-warning-emphasis">Trung bình mỗi tháng</div>
                <div class="kpi-number text-warning-emphasis">{{ number_format($avgMonthly, 0, ',', '.') }} <span class="fs-6 fw-normal">đ</span></div>
                <div class="text-muted" style="font-size: 11px;">Mức tiêu thụ định kỳ</div>
            </div>
            <i class="bi bi-graph-up fs-2 text-warning opacity-75"></i>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-info">
            <div>
                <div class="kpi-title text-info">Tổng chi toàn thời gian</div>
                <div class="kpi-number text-info">{{ number_format($totalAll, 0, ',', '.') }} <span class="fs-6 fw-normal">đ</span></div>
                <div class="text-muted" style="font-size: 11px;">Từ khi kích hoạt tủ thuốc</div>
            </div>
            <i class="bi bi-wallet2 fs-2 text-info opacity-75"></i>
        </div>
    </div>
</div>

<!-- CHARTS ROW: BIỂU ĐỒ 12 THÁNG & CƠ CẤU THEO THÀNH VIÊN -->
<div class="row g-3 mb-3">
    <!-- Biểu đồ cột: Chi tiêu theo 12 tháng -->
    <div class="col-12 col-lg-8">
        <div class="content-card h-100 mb-0">
            <div class="content-card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">
                    <i class="bi bi-bar-chart-line text-primary me-2"></i>BIẾN ĐỘNG CHI TIÊU Y TẾ NĂM {{ $selectedYear }}
                </span>
                <span class="text-muted small">Đơn vị: VNĐ</span>
            </div>
            <div class="content-card-body p-3">
                <div style="height: 250px; position: relative;">
                    <canvas id="monthlyExpenseChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Biểu đồ tròn: Cơ cấu chi phí theo Thành viên -->
    <div class="col-12 col-lg-4">
        <div class="content-card h-100 mb-0">
            <div class="content-card-header">
                <span class="fw-bold text-dark">
                    <i class="bi bi-pie-chart text-success me-2"></i>CƠ CẤU CHI PHÍ THEO THÀNH VIÊN
                </span>
            </div>
            <div class="content-card-body p-3">
                @if (empty($memberExpenses))
                    <div class="text-center py-5 text-muted small">
                        Chưa có dữ liệu chi tiêu trong năm {{ $selectedYear }}.
                    </div>
                @else
                    <div style="height: 220px; position: relative;">
                        <canvas id="memberExpenseChart"></canvas>
                    </div>
                    <div class="mt-2 text-center text-muted small" style="font-size: 11px;">
                        Phân bổ tỷ lệ tiền thuốc theo từng thành viên gia đình
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- FILTER TOOLBAR -->
<div class="filter-panel mb-3">
    <form method="GET" action="{{ route('expenses.index') }}" class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label">Tìm kiếm nội dung</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tên thuốc, tên bệnh viện, ghi chú...">
            </div>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label">Năm tài chính</label>
            <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                @for ($y = Carbon\Carbon::now()->year + 1; $y >= Carbon\Carbon::now()->year - 3; $y--)
                    <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>Năm {{ $y }}</option>
                @endfor
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label">Tháng</label>
            <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Cả năm --</option>
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ (string)$selectedMonth === (string)$m ? 'selected' : '' }}>Tháng {{ $m }}</option>
                @endfor
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label">Thành viên</label>
            <select name="member_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Tất cả --</option>
                <option value="general" {{ $memberId === 'general' ? 'selected' : '' }}>Chi phí chung (Tủ thuốc)</option>
                @foreach ($members as $mem)
                    <option value="{{ $mem->id }}" {{ (string)$memberId === (string)$mem->id ? 'selected' : '' }}>
                        {{ $mem->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label">Đợt điều trị</label>
            <select name="treatment_course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Tất cả đợt --</option>
                @foreach ($treatmentCourses as $tc)
                    <option value="{{ $tc->id }}" {{ (string)$courseId === (string)$tc->id ? 'selected' : '' }}>
                        {{ $tc->title }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-12 col-md-1 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary w-100" title="Áp dụng bộ lọc">
                <i class="bi bi-filter"></i> Lọc
            </button>
            @if(request()->hasAny(['q', 'year', 'month', 'member_id', 'treatment_course_id']))
                <a href="{{ route('expenses.index') }}" class="btn btn-sm btn-outline-secondary" title="Đặt lại bộ lọc">
                    <i class="bi bi-x-circle"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<!-- EXPENSES TABLE -->
<div class="content-card">
    <div class="content-card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark">
            <i class="bi bi-receipt-cutoff text-primary me-2"></i>DANH SÁCH HÓA ĐƠN & KHOẢN CHI Y TẾ ({{ $expenses->total() }})
        </span>
        <span class="text-muted small">Cập nhật và lưu trữ đầy đủ chứng từ y khoa của gia đình</span>
    </div>

    <div class="table-responsive">
        <table class="table table-erp table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 45px;" class="text-center">STT</th>
                    <th style="width: 120px;">Ngày chi</th>
                    <th>Nội dung hóa đơn / Khoản chi</th>
                    <th style="width: 180px;">Người thụ hưởng</th>
                    <th style="width: 220px;">Đợt điều trị / Bệnh án</th>
                    <th style="width: 140px;" class="text-end">Số tiền</th>
                    <th style="width: 100px;" class="text-center">Chứng từ</th>
                    <th style="width: 90px;" class="text-center">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $idx => $exp)
                    <tr>
                        <td class="text-center text-muted">
                            {{ $expenses->firstItem() + $idx }}
                        </td>
                        <td class="fw-semibold text-dark">
                            {{ $exp->purchase_date ? $exp->purchase_date->format('d/m/Y') : '—' }}
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $exp->title }}</div>
                            @if ($exp->notes)
                                <div class="text-muted small fst-italic" style="font-size: 11.5px;">
                                    {{ $exp->notes }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($exp->familyMember)
                                <div class="d-flex align-items-center gap-2">
                                    @if ($exp->familyMember->avatar_url)
                                        <img src="{{ $exp->familyMember->avatar_url }}" alt="{{ $exp->familyMember->name }}" class="rounded-circle border" style="width: 26px; height: 26px; object-fit: cover;">
                                    @else
                                        <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 26px; height: 26px; font-size: 10px;">
                                            {{ mb_substr($exp->familyMember->name, 0, 1) }}
                                        </div>
                                    @endif
                                    <div>
                                        <a href="{{ route('members.show', $exp->familyMember) }}" class="fw-semibold text-decoration-none text-dark small hover-primary">
                                            {{ $exp->familyMember->name }}
                                        </a>
                                        <div class="text-muted" style="font-size: 10px;">{{ $exp->familyMember->relationship_label }}</div>
                                    </div>
                                </div>
                            @else
                                <span class="badge bg-light text-secondary border">
                                    <i class="bi bi-box-seam me-1"></i>Chi phí chung Tủ thuốc
                                </span>
                            @endif
                        </td>
                        <td>
                            @if ($exp->treatmentCourse)
                                <a href="{{ route('treatment-courses.show', $exp->treatmentCourse) }}" class="small text-decoration-none fw-semibold text-primary">
                                    <i class="bi bi-journal-medical me-1"></i>{{ $exp->treatmentCourse->title }}
                                </a>
                            @else
                                <span class="text-muted small">— Mua lẻ tự túc —</span>
                            @endif
                        </td>
                        <td class="text-end fw-bold text-success fs-6">
                            {{ number_format($exp->amount, 0, ',', '.') }} đ
                        </td>
                        <td class="text-center">
                            @if ($exp->receipt_image_path)
                                <button type="button" class="btn btn-sm btn-outline-info py-0 px-2" style="font-size: 11px;" onclick="viewReceiptImage('{{ $exp->receipt_image_url }}', '{{ addslashes($exp->title) }}')">
                                    <i class="bi bi-image"></i> Xem
                                </button>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex align-items-center justify-content-center gap-1">
                                <a href="{{ route('expenses.edit', $exp) }}" class="btn btn-sm btn-light border p-1" title="Chỉnh sửa">
                                    <i class="bi bi-pencil text-warning"></i>
                                </a>
                                @if (auth()->user()->canDeleteExpenses())
                                    <button type="button" class="btn btn-sm btn-light border p-1" title="Xóa" onclick="confirmDeleteExpense('{{ $exp->id }}', '{{ addslashes($exp->title) }}', '{{ number_format($exp->amount, 0, ',', '.') }}')">
                                        <i class="bi bi-trash text-danger"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-cash fs-1 text-secondary opacity-50 d-block mb-2"></i>
                            <div class="fw-semibold fs-6">Chưa có hóa đơn hoặc chi phí nào được ghi nhận</div>
                            <div class="small mb-3">Lưu trữ các hóa đơn tiền thuốc và viện phí giúp gia đình bạn quản lý chi tiêu y tế thông minh.</div>
                            <a href="{{ route('expenses.create') }}" class="btn btn-sm btn-erp-primary">
                                <i class="bi bi-plus-circle me-1"></i>Thêm khoản chi đầu tiên
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($expenses->hasPages())
        <div class="p-3 border-top d-flex justify-content-end">
            {{ $expenses->links() }}
        </div>
    @endif
</div>

<!-- MODAL: XEM HÓA ĐƠN PHÓNG TO -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold" id="receiptModalTitle">
                    <i class="bi bi-receipt me-1"></i>Hóa đơn chứng từ
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2 text-center bg-light">
                <img src="" id="receiptModalImage" alt="Hóa đơn" class="img-fluid rounded" style="max-height: 80vh;">
            </div>
            <div class="modal-footer py-2 bg-dark">
                <a href="" id="receiptModalNewTab" target="_blank" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Mở trong tab mới
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: XÁC NHẬN XÓA KHOẢN CHI -->
<div class="modal fade" id="deleteExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Xác nhận xóa khoản chi
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <p class="mb-2">Bạn có chắc chắn muốn xóa khoản chi này?</p>
                <div class="p-2 bg-light rounded mb-2">
                    <div class="fw-bold text-dark" id="deleteExpenseTitle"></div>
                    <div class="text-danger fw-bold" id="deleteExpenseAmount"></div>
                </div>
                <div class="small text-muted">Hành động này sẽ xóa vĩnh viễn khoản chi và hình ảnh hóa đơn kèm theo.</div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form id="deleteExpenseForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger px-3">Xác nhận xóa</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// 1. Khởi tạo Biểu đồ 12 Tháng
const monthlyData = @json(array_values($monthlyData));
const monthLabels = ['Th1', 'Th2', 'Th3', 'Th4', 'Th5', 'Th6', 'Th7', 'Th8', 'Th9', 'Th10', 'Th11', 'Th12'];

const ctxBar = document.getElementById('monthlyExpenseChart').getContext('2d');
new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Chi phí tiền thuốc (VNĐ)',
            data: monthlyData,
            backgroundColor: 'rgba(0, 123, 182, 0.75)',
            borderColor: '#007bb6',
            borderWidth: 1,
            borderRadius: 4,
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
                        return new Intl.NumberFormat('vi-VN').format(context.parsed.y) + ' đ';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        if (value >= 1000000) return (value / 1000000) + ' Tr';
                        if (value >= 1000) return (value / 1000) + ' k';
                        return value;
                    }
                }
            }
        }
    }
});

// 2. Khởi tạo Biểu đồ tròn Thành viên
const memberData = @json($memberExpenses);
if (memberData && memberData.length > 0) {
    const ctxPie = document.getElementById('memberExpenseChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: memberData.map(d => d.name),
            datasets: [{
                data: memberData.map(d => d.amount),
                backgroundColor: [
                    '#007bb6',
                    '#2e7d32',
                    '#f59e0b',
                    '#ef4444',
                    '#8b5cf6',
                    '#64748b'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 11 } }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const val = context.parsed;
                            return context.label + ': ' + new Intl.NumberFormat('vi-VN').format(val) + ' đ';
                        }
                    }
                }
            }
        }
    });
}

function viewReceiptImage(url, title) {
    document.getElementById('receiptModalImage').src = url;
    document.getElementById('receiptModalTitle').innerHTML = '<i class="bi bi-receipt me-1"></i>' + title;
    document.getElementById('receiptModalNewTab').href = url;
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
}

function confirmDeleteExpense(id, title, amountFormatted) {
    document.getElementById('deleteExpenseTitle').textContent = title;
    document.getElementById('deleteExpenseAmount').textContent = amountFormatted + ' đ';
    document.getElementById('deleteExpenseForm').action = `/expenses/${id}`;
    new bootstrap.Modal(document.getElementById('deleteExpenseModal')).show();
}
</script>
@endpush
@endsection
