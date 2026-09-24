@extends('layouts.app')

@section('title', 'Tủ thuốc gia đình')

@section('content')
<!-- Header và Quick Actions -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1 fw-bold text-dark d-flex align-items-center gap-2">
            <i class="bi bi-capsule text-primary"></i> Tủ thuốc gia đình
        </h1>
        <div class="text-muted small">
            Quản lý chi tiết từng lô thuốc, giám sát hạn sử dụng & kiểm soát tồn kho y tế an toàn.
        </div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="In danh sách kiểm kê tủ thuốc">
            <i class="bi bi-printer me-1"></i> In danh mục
        </button>

        <!-- Xuất Excel -->
        <a href="{{ route('cabinet.export-excel', request()->query()) }}" class="btn btn-outline-success btn-sm" title="Xuất toàn bộ hoặc theo bộ lọc hiện tại ra file Excel">
            <i class="bi bi-file-earmark-excel me-1"></i> Xuất Excel
        </a>

        @if (auth()->user()->isFamilyAdmin())
            <!-- Nhập Excel -->
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importExcelModal" title="Nhập danh sách nhiều thuốc cùng lúc từ file Excel/CSV">
                <i class="bi bi-file-earmark-arrow-up me-1"></i> Nhập từ Excel
            </button>
        @endif

        @if(count($shoppingItems) > 0)
            <button type="button" class="btn btn-outline-warning btn-sm text-dark position-relative" data-bs-toggle="modal" data-bs-target="#shoppingListModal">
                <i class="bi bi-cart-plus me-1"></i> Cần mua bổ sung
                <span class="badge bg-danger rounded-pill ms-1">{{ count($shoppingItems) }}</span>
            </button>
        @endif

        @if (auth()->user()->isFamilyAdmin())
            <a href="{{ route('cabinet.items.create') }}" class="btn btn-erp-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Thêm thuốc vào tủ
            </a>
        @endif
    </div>
</div>

<!-- 6 Thẻ KPI chuẩn Dashboard Dược (Phân tách rạch ròi giữa Hạn sử dụng và Tồn kho) -->
<div class="row g-2 mb-3">
    <!-- 1. Tổng số lô -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('cabinet.index', array_filter(['status' => null, 'q' => request('q'), 'location' => request('location'), 'category_id' => request('category_id')])) }}" class="text-decoration-none">
            <div class="content-card h-100 mb-0 {{ $filter === 'all' ? 'border-primary shadow-sm' : '' }}">
                <div class="content-card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">Tổng số lô</span>
                        <i class="bi bi-capsule text-primary fs-5 opacity-75"></i>
                    </div>
                    <div class="fs-4 fw-bold text-primary mt-1">{{ $stats['total'] }} <small class="text-muted fw-normal" style="font-size: 11px;">lô</small></div>
                </div>
            </div>
        </a>
    </div>

    <!-- 2. Còn hạn an toàn -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('cabinet.index', array_filter(['status' => 'available', 'q' => request('q'), 'location' => request('location'), 'category_id' => request('category_id')])) }}" class="text-decoration-none">
            <div class="content-card h-100 mb-0 {{ $filter === 'available' ? 'border-success shadow-sm' : '' }}">
                <div class="content-card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">Còn hạn tốt</span>
                        <i class="bi bi-shield-check text-success fs-5 opacity-75"></i>
                    </div>
                    <div class="fs-4 fw-bold text-success mt-1">{{ $stats['available'] }} <small class="text-muted fw-normal" style="font-size: 11px;">lô</small></div>
                </div>
            </div>
        </a>
    </div>

    <!-- 3. Cận hạn (< 30 ngày) -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('cabinet.index', array_filter(['status' => 'near_expiry', 'q' => request('q'), 'location' => request('location'), 'category_id' => request('category_id')])) }}" class="text-decoration-none">
            <div class="content-card h-100 mb-0 {{ $filter === 'near_expiry' ? 'border-warning shadow-sm' : '' }}">
                <div class="content-card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">Cận hạn (&lt;30d)</span>
                        <i class="bi bi-hourglass-split text-warning fs-5 opacity-75"></i>
                    </div>
                    <div class="fs-4 fw-bold text-warning-emphasis mt-1">{{ $stats['near_expiry'] }} <small class="text-muted fw-normal" style="font-size: 11px;">lô</small></div>
                </div>
            </div>
        </a>
    </div>

    <!-- 4. Quá hạn sử dụng -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('cabinet.index', array_filter(['status' => 'expired', 'q' => request('q'), 'location' => request('location'), 'category_id' => request('category_id')])) }}" class="text-decoration-none">
            <div class="content-card h-100 mb-0 {{ $filter === 'expired' ? 'border-danger shadow-sm' : '' }}">
                <div class="content-card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">Đã quá hạn</span>
                        <i class="bi bi-exclamation-octagon text-danger fs-5 opacity-75"></i>
                    </div>
                    <div class="fs-4 fw-bold text-danger mt-1">{{ $stats['expired'] }} <small class="text-muted fw-normal" style="font-size: 11px;">lô</small></div>
                </div>
            </div>
        </a>
    </div>

    <!-- 5. Tồn kho ít (≤ 5) -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('cabinet.index', array_filter(['status' => 'low_stock', 'q' => request('q'), 'location' => request('location'), 'category_id' => request('category_id')])) }}" class="text-decoration-none">
            <div class="content-card h-100 mb-0 {{ $filter === 'low_stock' ? 'border-info shadow-sm' : '' }}">
                <div class="content-card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">Tồn kho ít (≤5)</span>
                        <i class="bi bi-box-seam text-info fs-5 opacity-75"></i>
                    </div>
                    <div class="fs-4 fw-bold text-info mt-1">{{ $stats['low_stock'] }} <small class="text-muted fw-normal" style="font-size: 11px;">lô</small></div>
                </div>
            </div>
        </a>
    </div>

    <!-- 6. Hết hàng (0) -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('cabinet.index', array_filter(['status' => 'out_of_stock', 'q' => request('q'), 'location' => request('location'), 'category_id' => request('category_id')])) }}" class="text-decoration-none">
            <div class="content-card h-100 mb-0 {{ $filter === 'out_of_stock' ? 'border-secondary shadow-sm' : '' }}">
                <div class="content-card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted fw-semibold">Hết tồn kho (0)</span>
                        <i class="bi bi-archive text-secondary fs-5 opacity-75"></i>
                    </div>
                    <div class="fs-4 fw-bold text-secondary mt-1">{{ $stats['out_of_stock'] }} <small class="text-muted fw-normal" style="font-size: 11px;">lô</small></div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Bộ lọc nâng cao đa tiêu chí -->
<div class="content-card mb-3">
    <div class="content-card-body p-3">
        <form method="GET" action="{{ route('cabinet.index') }}" class="row g-2 align-items-end">
            <!-- Tìm kiếm tên / hoạt chất / số lô -->
            <div class="col-12 col-md-4 col-lg-3">
                <label for="q" class="form-label small fw-semibold text-muted mb-1">Tìm kiếm từ khóa</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input id="q" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Tên thuốc, hoạt chất, số lô...">
                </div>
            </div>

            <!-- Tình trạng hạn dùng & tồn kho -->
            <div class="col-6 col-md-3 col-lg-2">
                <label for="status" class="form-label small fw-semibold text-muted mb-1">Tình trạng lọc</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="all" @selected($filter === 'all')>-- Tất cả trạng thái --</option>
                    <option value="available" @selected($filter === 'available')>🛡️ Còn hạn an toàn</option>
                    <option value="near_expiry" @selected($filter === 'near_expiry')>⏳ Cận hạn (&lt; 30 ngày)</option>
                    <option value="expired" @selected($filter === 'expired')>⚠️ Đã quá hạn</option>
                    <option value="low_stock" @selected($filter === 'low_stock')>📦 Tồn kho ít (≤ 5)</option>
                    <option value="out_of_stock" @selected($filter === 'out_of_stock')>❌ Hết hàng (0)</option>
                </select>
            </div>

            <!-- Vị trí lưu trữ -->
            <div class="col-6 col-md-3 col-lg-2">
                <label for="location" class="form-label small fw-semibold text-muted mb-1">Vị trí ngăn tủ</label>
                <select id="location" name="location" class="form-select form-select-sm">
                    <option value="">-- Tất cả vị trí --</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc }}" @selected(request('location') === $loc)>{{ $loc }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Nhóm dược lý -->
            <div class="col-6 col-md-3 col-lg-2">
                <label for="category_id" class="form-label small fw-semibold text-muted mb-1">Nhóm thuốc</label>
                <select id="category_id" name="category_id" class="form-select form-select-sm">
                    <option value="">-- Tất cả nhóm --</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((string)request('category_id') === (string)$cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Sắp xếp -->
            <div class="col-6 col-md-3 col-lg-2">
                <label for="sort" class="form-label small fw-semibold text-muted mb-1">Sắp xếp theo</label>
                <select id="sort" name="sort" class="form-select form-select-sm">
                    <option value="expiry_asc" @selected($sort === 'expiry_asc')>Hạn dùng gần nhất</option>
                    <option value="expiry_desc" @selected($sort === 'expiry_desc')>Hạn dùng xa nhất</option>
                    <option value="quantity_asc" @selected($sort === 'quantity_asc')>Tồn kho ít nhất</option>
                    <option value="quantity_desc" @selected($sort === 'quantity_desc')>Tồn kho nhiều nhất</option>
                    <option value="name_asc" @selected($sort === 'name_asc')>Tên thuốc A-Z</option>
                </select>
            </div>

            <!-- Action buttons -->
            <div class="col-12 col-md-3 col-lg-1 d-flex gap-1">
                <button class="btn btn-erp-primary btn-sm flex-grow-1" type="submit" title="Áp dụng bộ lọc">
                    <i class="bi bi-funnel me-1"></i>Lọc
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('cabinet.index') }}" title="Xóa toàn bộ bộ lọc">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Bảng danh mục tủ thuốc -->
<div class="content-card">
    <div class="content-card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-table text-primary"></i>
            <span class="fw-bold">DANH SÁCH THUỐC TRONG TỦ ({{ $items->total() }} LÔ)</span>
        </div>
        <div class="text-muted small">
            Ước tính giá trị tủ thuốc: <strong class="text-primary">{{ number_format($stats['total_value'], 0, ',', '.') }} đ</strong>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-erp table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 40px;" class="text-center">STT</th>
                    <th>Thuốc / Hoạt chất</th>
                    <th style="width: 120px;">Số lô</th>
                    <th style="width: 170px;">Hạn sử dụng</th>
                    <th style="width: 160px;">Tồn kho</th>
                    <th style="width: 170px;">Vị trí cất</th>
                    <th style="width: 150px;" class="text-center">Tình trạng y tế</th>
                    <th style="width: 110px;" class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($items as $idx => $item)
                @php
                    $isExpired = $item->is_expired;
                    $isNear = $item->is_near_expiry;
                    $isOutOfStock = $item->is_out_of_stock;
                    $isLowStock = $item->is_low_stock;
                @endphp
                <tr class="{{ $isExpired ? 'table-danger bg-opacity-25' : ($isNear ? 'table-warning bg-opacity-25' : '') }}">
                    <!-- STT -->
                    <td class="text-center text-muted small">{{ $items->firstItem() + $idx }}</td>

                    <!-- Tên thuốc & Hoạt chất -->
                    <td>
                        <div class="d-flex align-items-start gap-1">
                            <div>
                                <a href="javascript:void(0)" class="fw-bold text-dark text-decoration-none hover-primary btn-quick-view"
                                   data-item='@json($item)'
                                   data-medicine='@json($item->medicine)'
                                   data-category='{{ $item->medicine?->category?->name ?? "Khác" }}'>
                                    {{ $item->display_name }}
                                </a>
                                @if ($item->medicine?->active_ingredient)
                                    <div class="small text-muted" style="font-size: 12px;">
                                        <i class="bi bi-droplet me-1"></i>{{ $item->medicine->active_ingredient }}
                                        @if($item->medicine->concentration) · <span class="fw-semibold text-secondary">{{ $item->medicine->concentration }}</span>@endif
                                    </div>
                                @endif
                                @if ($item->medicine?->category)
                                    <span class="badge bg-light text-secondary border mt-1" style="font-size: 11px;">
                                        {{ $item->medicine->category->name }}
                                    </span>
                                @endif
                                @if ($item->notes)
                                    <div class="small text-muted text-truncate mt-1" style="max-width: 250px;" title="{{ $item->notes }}">
                                        <i class="bi bi-info-circle me-1"></i>{{ $item->notes }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>

                    <!-- Số lô -->
                    <td>
                        <div class="fw-semibold text-dark">{{ $item->batch_number ?: '—' }}</div>
                        @if ($item->manufacturing_date)
                            <div class="text-muted small" style="font-size: 11px;">
                                NSX: {{ $item->manufacturing_date->format('d/m/Y') }}
                            </div>
                        @endif
                    </td>

                    <!-- HẠN SỬ DỤNG: Thể hiện cực kỳ rõ ràng, không gây hiểu lầm -->
                    <td>
                        <div class="fw-bold {{ $isExpired ? 'text-danger' : ($isNear ? 'text-warning-emphasis' : 'text-dark') }}">
                            {{ $item->expiry_date->format('d/m/Y') }}
                        </div>
                        @if ($isExpired)
                            <span class="badge bg-danger text-white mt-1" style="font-size: 11px;">
                                <i class="bi bi-x-octagon me-1"></i>Đã quá hạn {{ abs($item->days_until_expiry) }} ngày
                            </span>
                        @elseif ($isNear)
                            <span class="badge bg-warning text-dark mt-1" style="font-size: 11px;">
                                <i class="bi bi-hourglass-split me-1"></i>Cận hạn: còn {{ $item->days_until_expiry }} ngày
                            </span>
                        @else
                            <div class="text-success small mt-1" style="font-size: 11.5px;">
                                <i class="bi bi-shield-check me-1"></i>{{ $item->expiry_remaining_text }}
                            </div>
                        @endif
                    </td>

                    <!-- TỒN KHO: Tách riêng với nhãn Tồn kho thấp / Hết hàng -->
                    <td>
                        @if (auth()->user()->isFamilyAdmin())
                            <form method="POST" action="{{ route('cabinet.items.quantity', $item) }}" class="d-flex gap-1 align-items-center">
                                @csrf @method('PATCH')
                                <input aria-label="Số lượng {{ $item->display_name }}" name="quantity" type="number" min="0" max="999999.99" step="0.01" value="{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}" class="form-control form-control-sm text-end fw-bold" style="width: 76px;">
                                <span class="small text-muted">{{ $item->unit }}</span>
                                <button class="btn btn-sm btn-outline-primary px-2" title="Lưu số lượng mới"><i class="bi bi-check-lg"></i></button>
                            </form>
                        @else
                            <span class="fw-bold fs-6">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</span> <span class="text-muted small">{{ $item->unit }}</span>
                        @endif

                        <!-- Nhãn tồn kho chuyên dụng -->
                        <div class="mt-1">
                            @if ($isOutOfStock)
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size: 11px;">
                                    <i class="bi bi-archive me-1"></i>Hết hàng (0)
                                </span>
                            @elseif ($isLowStock)
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: 11px;" title="Số lượng còn ≤ 5, nên mua bổ sung dự phòng">
                                    <i class="bi bi-box-seam me-1"></i>Tồn ít (≤ 5 {{ $item->unit }})
                                </span>
                            @else
                                <span class="badge bg-light text-muted border" style="font-size: 11px;">
                                    <i class="bi bi-check2 me-1"></i>Đủ dùng
                                </span>
                            @endif
                        </div>
                    </td>

                    <!-- Vị trí cất -->
                    <td>
                        <div class="small">
                            <i class="bi bi-geo-alt text-primary me-1"></i>{{ $item->storage_location ?: 'Ngăn tủ chính' }}
                        </div>
                        @if ($item->price_per_unit)
                            <div class="text-muted small mt-1" style="font-size: 11px;">
                                Đơn giá: {{ number_format($item->price_per_unit, 0, ',', '.') }} đ
                            </div>
                        @endif
                    </td>

                    <!-- TÌNH TRẠNG Y TẾ (An toàn sử dụng): Chuẩn xác, không bao giờ nhầm lẫn -->
                    <td class="text-center">
                        @if ($isExpired)
                            <span class="badge bg-danger text-white py-1 px-2 d-inline-block">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Quá hạn (Bỏ)
                            </span>
                        @elseif ($isNear)
                            <span class="badge bg-warning text-dark py-1 px-2 d-inline-block">
                                <i class="bi bi-clock-history me-1"></i>Cận hạn
                            </span>
                        @else
                            <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 d-inline-block">
                                <i class="bi bi-shield-check me-1"></i>Còn hạn an toàn
                            </span>
                        @endif

                        @if ($isOutOfStock)
                            <div class="mt-1">
                                <span class="badge bg-danger-subtle text-danger" style="font-size: 10px;">Cần mua ngay</span>
                            </div>
                        @elseif ($isLowStock)
                            <div class="mt-1">
                                <span class="badge bg-warning-subtle text-warning-emphasis" style="font-size: 10px;">Nên mua thêm</span>
                            </div>
                        @endif
                    </td>

                    <!-- Thao tác -->
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-view" title="Xem chi tiết thuốc"
                                data-item='@json($item)'
                                data-medicine='@json($item->medicine)'
                                data-category='{{ $item->medicine?->category?->name ?? "Khác" }}'>
                            <i class="bi bi-eye"></i>
                        </button>

                        @if (auth()->user()->isFamilyAdmin())
                            <a href="{{ route('cabinet.items.edit', $item) }}" class="btn btn-sm btn-outline-primary" title="Sửa thông tin"><i class="bi bi-pencil"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-cabinet" title="Xóa khỏi tủ"
                                    data-action="{{ route('cabinet.items.destroy', $item) }}"
                                    data-name="{{ $item->display_name }}"
                                    data-batch="{{ $item->batch_number ?: '—' }}"
                                    data-qty="{{ rtrim(rtrim((string)$item->quantity, '0'), '.') }} {{ $item->unit }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <i class="bi bi-capsule fs-1 text-muted d-block mb-2"></i>
                        <div class="fw-semibold fs-6">{{ request()->filled('q') || $filter !== 'all' || request()->filled('location') || request()->filled('category_id') ? 'Không tìm thấy thuốc phù hợp bộ lọc' : 'Tủ thuốc chưa có mục thuốc nào' }}</div>
                        <div class="text-muted small mt-1">Thêm các lô thuốc đang có trong gia đình để tự động theo dõi hạn dùng và nhận cảnh báo an toàn.</div>
                        @if (auth()->user()->isFamilyAdmin() && !request()->filled('q') && $filter === 'all')
                            <a href="{{ route('cabinet.items.create') }}" class="btn btn-sm btn-erp-primary mt-3">
                                <i class="bi bi-plus-lg me-1"></i>Thêm thuốc đầu tiên
                            </a>
                        @else
                            <a href="{{ route('cabinet.index') }}" class="btn btn-sm btn-outline-secondary mt-3">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Xóa bộ lọc
                            </a>
                        @endif
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="content-card-body py-2 border-top">
            {{ $items->links() }}
        </div>
    @endif
</div>

<!-- MODAL 1: XEM CHI TIẾT THUỐC NHANH (QUICK DETAIL MODAL) -->
<div class="modal fade" id="quickDetailModal" tabindex="-1" aria-labelledby="quickDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="quickDetailModalLabel">
                    <i class="bi bi-capsule-pill text-primary me-2"></i>Chi tiết thuốc trong tủ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="modalDetailBody">
                <!-- Nội dung được điền tự động qua JavaScript -->
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 2: DANH SÁCH THUỐC CẦN MUA BỔ SUNG (SHOPPING LIST MODAL) -->
<div class="modal fade" id="shoppingListModal" tabindex="-1" aria-labelledby="shoppingListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning bg-opacity-10 border-bottom border-warning-subtle">
                <h5 class="modal-title fw-bold text-dark" id="shoppingListModalLabel">
                    <i class="bi bi-cart-check-fill text-warning me-2"></i>Danh sách thuốc & vật tư y tế cần mua bổ sung
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Hệ thống tự động phát hiện <strong>{{ count($shoppingItems) }}</strong> mục thuốc đang hết hàng, tồn kho ít (≤ 5) hoặc đã quá hạn cần mua thay mới.
                </div>

                <div class="table-responsive" id="shoppingPrintArea">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>Tên thuốc / Vật tư</th>
                                <th>Hoạt chất / Quy cách</th>
                                <th class="text-center" style="width: 110px;">Tồn hiện tại</th>
                                <th>Vị trí cất</th>
                                <th>Lý do mua</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shoppingItems as $sIdx => $sItem)
                                <tr>
                                    <td class="text-center">{{ $sIdx + 1 }}</td>
                                    <td class="fw-bold">{{ $sItem->display_name }}</td>
                                    <td>{{ $sItem->medicine?->active_ingredient ?: '—' }}</td>
                                    <td class="text-center fw-bold {{ $sItem->quantity <= 0 ? 'text-danger' : 'text-warning-emphasis' }}">
                                        {{ rtrim(rtrim((string)$sItem->quantity, '0'), '.') }} {{ $sItem->unit }}
                                    </td>
                                    <td>{{ $sItem->storage_location ?: 'Tủ thuốc gia đình' }}</td>
                                    <td>
                                        @if ($sItem->is_expired)
                                            <span class="badge bg-danger">Hết hạn - Thay mới</span>
                                        @elseif ($sItem->quantity <= 0)
                                            <span class="badge bg-secondary">Hết hàng</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Tồn ít (≤ 5 {{ $sItem->unit }})</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-3 text-muted">Hiện tại tủ thuốc đã đầy đủ, không có loại nào cần mua bổ sung.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyShoppingList()">
                    <i class="bi bi-clipboard me-1"></i>Sao chép danh sách
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary" onclick="printShoppingList()">
                        <i class="bi bi-printer me-1"></i>In phiếu mua sắm
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 3: NHẬP DỮ LIỆU EXCEL / CSV -->
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-labelledby="importExcelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title fw-bold fs-6" id="importExcelModalLabel">
                    <i class="bi bi-file-earmark-excel-fill me-2"></i>Nhập nhiều thuốc bằng Excel / CSV
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('cabinet.import-excel') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded border">
                        <div>
                            <div class="fw-bold small text-dark"><i class="bi bi-download me-1 text-primary"></i>Tải file mẫu Excel (.csv)</div>
                            <div class="text-muted" style="font-size: 11.5px;">Bảng tính mẫu có sẵn các cột chuẩn kèm 3 dòng ví dụ minh họa.</div>
                        </div>
                        <a href="{{ route('cabinet.sample-excel') }}" class="btn btn-sm btn-outline-primary text-nowrap">
                            <i class="bi bi-file-earmark-arrow-down me-1"></i>Tải mẫu Excel
                        </a>
                    </div>

                    <div class="mb-3">
                        <label for="excel_file" class="form-label fw-semibold text-dark mb-1">
                            Chọn file từ máy tính (.csv) <span class="text-danger">*</span>
                        </label>
                        <input type="file" id="excel_file" name="excel_file" required accept=".csv,.txt" class="form-control">
                        <div class="form-text text-muted" style="font-size: 11.5px;">
                            Hỗ trợ file CSV (UTF-8) xuất từ Microsoft Excel hoặc Google Sheets. Dung lượng tối đa 5MB.
                        </div>
                    </div>

                    <div class="alert alert-info py-2 small mb-0" style="font-size: 12px;">
                        <div class="fw-bold mb-1"><i class="bi bi-info-circle me-1"></i>Thứ tự cột chuẩn trong file:</div>
                        <code>Tên thuốc, Hoạt chất, Số lô, Ngày SX, Hạn sử dụng, Số lượng, Đơn vị, Đơn giá, Vị trí cất, Ghi chú</code>
                        <div class="mt-1 text-muted">Định dạng ngày tháng: <strong>YYYY-MM-DD</strong> (ví dụ 2028-06-30) hoặc <strong>DD/MM/YYYY</strong>.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-erp-primary btn-sm">
                        <i class="bi bi-cloud-arrow-up me-1"></i>Tải lên & Nhập vào tủ thuốc
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL 4: XÁC NHẬN XÓA LÔ THUỐC (CHUẨN ERP Y TẾ) -->
<div class="modal fade" id="deleteCabinetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 shadow">
            <div class="modal-body p-4 text-center">
                <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex p-3 text-danger mb-3">
                    <i class="bi bi-trash3-fill fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Xác nhận xóa lô thuốc?</h5>
                <p class="text-muted small mb-3">
                    Bạn có chắc chắn muốn xóa lô thuốc này khỏi tủ thuốc gia đình? Dữ liệu đã xóa sẽ không thể phục hồi.
                </p>
                <div class="bg-light p-3 rounded text-start mb-3 border">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Tên thuốc:</span>
                        <strong class="text-dark small" id="delItemName">—</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Số lô:</span>
                        <span class="text-dark small" id="delItemBatch">—</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Tồn kho hiện tại:</span>
                        <span class="text-dark small fw-bold" id="delItemQty">—</span>
                    </div>
                </div>
                <form id="deleteCabinetForm" method="POST" action="">
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
    // Quick Detail Modal rendering
    document.querySelectorAll('.btn-quick-view').forEach(btn => {
        btn.addEventListener('click', function () {
            const item = JSON.parse(this.dataset.item);
            const medicine = this.dataset.medicine ? JSON.parse(this.dataset.medicine) : null;
            const categoryName = this.dataset.category || 'Dược phẩm dùng chung';

            const expiryFormatted = item.expiry_date ? new Date(item.expiry_date).toLocaleDateString('vi-VN') : '—';
            const mfgFormatted = item.manufacturing_date ? new Date(item.manufacturing_date).toLocaleDateString('vi-VN') : '—';

            const priceFormatted = item.price_per_unit ? new Intl.NumberFormat('vi-VN').format(item.price_per_unit) + ' đ' : 'Chưa có giá';
            const totalValFormatted = item.price_per_unit ? new Intl.NumberFormat('vi-VN').format(item.quantity * item.price_per_unit) + ' đ' : '—';

            let modalHtml = `
                <div class="row g-3">
                    <div class="col-12 border-bottom pb-3">
                        <span class="badge bg-primary-subtle text-primary mb-1">${categoryName}</span>
                        <h4 class="fw-bold text-dark mb-1">${item.custom_name}</h4>
                        <div class="text-muted small">
                            ${medicine && medicine.active_ingredient ? '<i class="bi bi-droplet me-1"></i><strong>Hoạt chất:</strong> ' + medicine.active_ingredient : ''}
                            ${medicine && medicine.concentration ? ' · ' + medicine.concentration : ''}
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <div class="small text-muted">Số lô sản xuất</div>
                        <div class="fw-bold text-dark">${item.batch_number || '—'}</div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="small text-muted">Ngày sản xuất</div>
                        <div class="fw-bold text-dark">${mfgFormatted}</div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="small text-muted">Hạn sử dụng</div>
                        <div class="fw-bold text-primary">${expiryFormatted}</div>
                    </div>

                    <div class="col-6 col-md-4">
                        <div class="small text-muted">Số lượng tồn</div>
                        <div class="fw-bold fs-5 text-dark">${parseFloat(item.quantity)} ${item.unit}</div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="small text-muted">Đơn giá / đơn vị</div>
                        <div class="fw-semibold text-dark">${priceFormatted}</div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="small text-muted">Tổng giá trị tồn</div>
                        <div class="fw-bold text-success">${totalValFormatted}</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="small text-muted">Vị trí lưu trữ trong nhà</div>
                        <div class="fw-semibold text-dark"><i class="bi bi-geo-alt text-primary me-1"></i>${item.storage_location || 'Ngăn chính tủ thuốc'}</div>
                    </div>

                    <div class="col-12">
                        <div class="card bg-light border-0">
                            <div class="card-body p-3">
                                <div class="fw-bold small text-dark mb-1"><i class="bi bi-card-text me-1 text-primary"></i>Ghi chú bảo quản & sử dụng:</div>
                                <div class="small text-secondary">${item.notes || 'Không có ghi chú thêm.'}</div>
                            </div>
                        </div>
                    </div>

                    ${medicine && medicine.usage_instructions ? `
                    <div class="col-12">
                        <div class="small text-muted fw-semibold mb-1"><i class="bi bi-journal-medical me-1"></i>Hướng dẫn sử dụng chung:</div>
                        <div class="small text-dark p-2 bg-white rounded border">${medicine.usage_instructions}</div>
                    </div>
                    ` : ''}

                    ${medicine && medicine.contraindications ? `
                    <div class="col-12">
                        <div class="small text-danger fw-semibold mb-1"><i class="bi bi-exclamation-diamond me-1"></i>Chống chỉ định & Thận trọng:</div>
                        <div class="small text-dark p-2 bg-danger-subtle rounded border border-danger-subtle">${medicine.contraindications}</div>
                    </div>
                    ` : ''}
                </div>
            `;

            document.getElementById('modalDetailBody').innerHTML = modalHtml;
            const modal = new bootstrap.Modal(document.getElementById('quickDetailModal'));
            modal.show();
        });
    });

    // In danh sách cần mua
    function printShoppingList() {
        const printContent = document.getElementById('shoppingPrintArea').innerHTML;
        const originalContent = document.body.innerHTML;
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Danh sách thuốc cần mua - Tủ thuốc gia đình</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">');
        printWindow.document.write('</head><body class="p-4">');
        printWindow.document.write('<h3 class="fw-bold mb-3">DANH SÁCH THUỐC & VẬT TƯ CẦN MUA BỔ SUNG</h3>');
        printWindow.document.write('<p class="text-muted">Ngày lập: ' + new Date().toLocaleDateString('vi-VN') + '</p>');
        printWindow.document.write(printContent);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
    }

    // Sao chép danh sách cần mua vào Clipboard
    function copyShoppingList() {
        const rows = document.querySelectorAll('#shoppingPrintArea tbody tr');
        let text = "📋 DANH SÁCH THUỐC CẦN MUA BỔ SUNG:\n";
        rows.forEach((r, i) => {
            const cells = r.querySelectorAll('td');
            if (cells.length >= 6) {
                text += `${i + 1}. ${cells[1].innerText.trim()} (${cells[2].innerText.trim()}) - Hiện còn: ${cells[3].innerText.trim()} - Lý do: ${cells[5].innerText.trim()}\n`;
            }
        });
        navigator.clipboard.writeText(text).then(() => {
            alert('Đã sao chép danh sách mua sắm vào Clipboard! Bạn có thể dán (Paste) gửi qua Zalo/Tin nhắn.');
        });
    }

    // Modal xác nhận xóa chuẩn Bootstrap
    document.querySelectorAll('.btn-delete-cabinet').forEach(btn => {
        btn.addEventListener('click', function () {
            const action = this.dataset.action;
            const name = this.dataset.name;
            const batch = this.dataset.batch;
            const qty = this.dataset.qty;

            document.getElementById('deleteCabinetForm').action = action;
            document.getElementById('delItemName').innerText = name;
            document.getElementById('delItemBatch').innerText = batch;
            document.getElementById('delItemQty').innerText = qty;

            const delModal = new bootstrap.Modal(document.getElementById('deleteCabinetModal'));
            delModal.show();
        });
    });
</script>
@endpush
