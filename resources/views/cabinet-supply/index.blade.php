@extends('layouts.app')

@section('title', 'Cần mua & bổ sung thuốc - Tủ thuốc gia đình')

@section('content')
@php
    $qty = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $canRequest = $user->canRequestCabinetItems();
    $isReviewer = $user->canManageTreatments();
    $pendingRefills = $refills->where('status', 'pending');
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="{{ route('cabinet.index') }}" class="text-decoration-none text-muted small d-inline-flex align-items-center mb-1">
            <i class="bi bi-arrow-left me-1"></i> Quay lại tủ thuốc
        </a>
        <h1 class="h4 mb-1 fw-bold text-dark d-flex align-items-center gap-2">
            <i class="bi bi-cart-check text-primary"></i> Cần mua & bổ sung thuốc
        </h1>
        <div class="text-muted small">
            Thuốc theo đơn do <strong>Bác sĩ kê</strong>, gia đình chỉ cần mua về và bấm <strong>"Đã mua"</strong>.
            Thuốc đang uống sắp hết thì gửi <strong>yêu cầu bổ sung</strong> để Bác sĩ kê tiếp hoặc cho ngưng.
        </div>
    </div>
    <a href="{{ route('cabinet.requests.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-bag-plus me-1"></i>Thuốc tự mua ngoài đơn
        @if ($pendingCabinetRequests > 0)
            <span class="badge bg-warning text-dark rounded-pill ms-1">{{ $pendingCabinetRequests }}</span>
        @endif
    </a>
</div>

@unless ($hasDoctor)
    <div class="alert alert-warning py-2 px-3 small d-flex gap-2">
        <i class="bi bi-person-exclamation fs-5"></i>
        <div>
            <strong>Gia đình chưa có Bác sĩ / Người chăm sóc</strong> nên yêu cầu bổ sung sẽ do Quản trị viên hệ thống xử lý thay.
            @if ($user->isFamilyAdmin())
                Chỉ định Bác sĩ tại <a href="{{ route('profile') }}" class="alert-link">Hồ sơ → Tài khoản truy cập hộ gia đình</a>.
            @endif
        </div>
    </div>
@endunless

@if ($isReviewer && $pendingRefills->isNotEmpty())
    <div class="alert alert-info py-2 px-3 small">
        <i class="bi bi-bell me-1"></i>Có <strong>{{ $pendingRefills->count() }}</strong> yêu cầu bổ sung đang chờ Bác sĩ xử lý —
        <a href="#refill-section" class="alert-link">xem bên dưới</a>.
    </div>
@endif

<!-- 0. THUỐC ĐÃ MUA CHỜ BÁC SĨ XÁC NHẬN (chưa được dùng) -->
@if ($pendingLots->isNotEmpty() || $returnedLots->isNotEmpty())
    <div class="content-card mb-3 border-warning" id="pending-lots">
        <div class="content-card-header bg-warning-subtle d-flex justify-content-between align-items-center">
            <span class="fw-bold text-warning-emphasis"><i class="bi bi-shield-check me-2"></i>Thuốc đã mua chờ {{ $hasDoctor ? 'Bác sĩ' : 'Quản trị viên' }} xác nhận</span>
            <span class="badge bg-warning text-dark">{{ $pendingLots->count() }} lô</span>
        </div>
        <div class="content-card-body p-0">
            <div class="px-3 pt-2 small text-muted">Thuốc chỉ được dùng (tính vào tủ, trừ khi điểm danh) sau khi được xác nhận đúng thuốc, đúng hàm lượng, còn hạn.</div>
            @foreach ($pendingLots as $lot)
                <div class="{{ !$loop->last || $returnedLots->isNotEmpty() ? 'border-bottom' : '' }}">
                    @include('cabinet-supply.partials.lot-review', ['lot' => $lot, 'canReview' => $user->canManageCabinet()])
                </div>
            @endforeach
            @foreach ($returnedLots as $lot)
                <div class="p-3 small {{ !$loop->last ? 'border-bottom' : '' }}" id="lot-{{ $lot->id }}">
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1">Bị trả lại</span>
                    <strong>{{ $lot->display_name }}</strong> ({{ $qty($lot->quantity) }} {{ $lot->unit }})
                    — {{ $lot->approver?->name }}: {{ $lot->review_note }}.
                    <span class="text-muted">Hãy đổi lại ở hiệu thuốc rồi bấm "Đã mua" lần nữa.</span>
                </div>
            @endforeach
        </div>
    </div>
@endif

<!-- 1. CẦN MUA THEO ĐƠN -->
<div class="content-card mb-3">
    <div class="content-card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="bi bi-file-earmark-medical text-primary me-2"></i>1. Cần mua theo đơn Bác sĩ</span>
        <span class="badge bg-primary-subtle text-primary">{{ $purchaseList->count() + $awaitingRefills->count() }} thuốc</span>
    </div>
    <div class="table-responsive">
        <table class="table table-erp align-middle mb-0">
            <thead>
                <tr>
                    <th>Thuốc</th>
                    <th>Đơn / Người dùng</th>
                    <th class="text-end">Kê / Đã mua</th>
                    <th class="text-end">Cần mua</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchaseList as $row)
                    @php $item = $row['item']; @endphp
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $item->medicine_name }}</div>
                            <div class="text-muted small">{{ $item->dosage }} · {{ $item->frequency }}</div>
                        </td>
                        <td class="small">
                            <a href="{{ route('treatment-courses.show', $row['course']) }}" class="text-decoration-none">{{ $row['course']->title }}</a>
                            <div class="text-muted">{{ $row['member']?->name ?? 'Gia đình' }}{{ $row['course']->hospital_doctor ? ' · ' . $row['course']->hospital_doctor : '' }}</div>
                        </td>
                        <td class="text-end small">
                            {{ $row['prescribed'] ? $qty($row['prescribed']) . ' ' . $item->unit : 'Không ghi' }}
                            <div class="text-muted">đã mua {{ $qty($row['purchased']) }}</div>
                            @if ($row['pending_review'] > 0)
                                <div class="text-warning-emphasis">{{ $qty($row['pending_review']) }} chờ xác nhận</div>
                            @endif
                        </td>
                        <td class="text-end fw-bold text-primary">
                            @if ($row['remaining'] !== null)
                                {{ $qty($row['remaining']) }} {{ $item->unit }}
                            @elseif ($row['suggested'])
                                ~{{ $qty($row['suggested']) }} {{ $item->unit }}
                                <div class="text-muted small fw-normal">ước tính tới hết đợt</div>
                            @else
                                <span class="text-muted small fw-normal">theo hướng dẫn hiệu thuốc</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('cabinet.purchases.prescription.create', $item) }}" class="btn btn-sm btn-erp-primary text-nowrap">
                                <i class="bi bi-bag-check me-1"></i>Đã mua
                            </a>
                            @if ($row['existing_stock']->isNotEmpty())
                                <form method="POST" action="{{ route('cabinet.purchases.prescription.link', $item) }}" class="d-flex gap-1 justify-content-end mt-1">
                                    @csrf
                                    <select name="cabinet_item_id" class="form-select form-select-sm" style="max-width: 190px; font-size: 11.5px;" aria-label="Lô có sẵn trong tủ">
                                        @foreach ($row['existing_stock'] as $lot)
                                            <option value="{{ $lot->id }}">{{ $lot->display_name }} (còn {{ $qty($lot->quantity) }} {{ $lot->unit }}, HSD {{ $lot->expiry_date?->format('d/m/Y') }})</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-success text-nowrap" style="font-size: 11.5px;" title="Thuốc này đã có sẵn trong tủ">Có sẵn</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @foreach ($awaitingRefills as $refill)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $refill->medicine_name }}</div>
                            <div class="text-muted small">Bổ sung thuốc ngoài đơn</div>
                        </td>
                        <td class="small text-muted">
                            Được duyệt {{ $refill->reviewed_at?->format('d/m/Y') }}
                            @if ($refill->review_note)<div>Dặn dò: {{ $refill->review_note }}</div>@endif
                        </td>
                        <td class="text-end small text-muted">—</td>
                        <td class="text-end fw-bold text-primary">{{ $qty($refill->approved_quantity) }} {{ $refill->unit }}</td>
                        <td class="text-end">
                            <a href="{{ route('cabinet.purchases.refill.create', $refill) }}" class="btn btn-sm btn-erp-primary text-nowrap">
                                <i class="bi bi-bag-check me-1"></i>Đã mua
                            </a>
                        </td>
                    </tr>
                @endforeach
                @if ($purchaseList->isEmpty() && $awaitingRefills->isEmpty())
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="bi bi-check2-circle text-success fs-4 d-block mb-1"></i>Đã mua đủ thuốc theo các đơn đang điều trị.
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<!-- 2. THUỐC ĐANG UỐNG CÒN ĐỦ DÙNG BAO LÂU -->
<div class="content-card mb-3">
    <div class="content-card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="bi bi-hourglass-split text-warning me-2"></i>2. Thuốc đang uống còn đủ dùng bao lâu</span>
        <span class="text-muted small">Tính theo liều mỗi ngày trong lịch uống</span>
    </div>
    <div class="table-responsive">
        <table class="table table-erp align-middle mb-0">
            <thead>
                <tr>
                    <th>Thuốc</th>
                    <th>Người uống</th>
                    <th class="text-end">Liều / ngày</th>
                    <th class="text-end">Còn trong tủ</th>
                    <th>Đủ dùng</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($supplyRows as $row)
                    <tr class="{{ $row['status'] === 'out' ? 'table-danger' : ($row['status'] === 'low' ? 'table-warning' : '') }}">
                        <td class="fw-bold">{{ $row['medicine_name'] }}</td>
                        <td class="small">{{ $row['members'] }}</td>
                        <td class="text-end small">{{ $qty($row['daily_usage']) }} {{ $row['unit'] }}</td>
                        <td class="text-end">{{ $qty($row['stock']) }} {{ $row['unit'] }}</td>
                        <td class="small">
                            @switch($row['status'])
                                @case('out')
                                    <span class="badge bg-danger">Đã hết trong tủ</span>
                                    @break
                                @case('low')
                                    <span class="badge bg-warning text-dark">Còn ~{{ $qty($row['days_left']) }} ngày</span>
                                    @break
                                @case('enough')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Đủ tới hết đợt</span>
                                    @break
                                @case('unknown')
                                    <span class="badge bg-secondary-subtle text-secondary border">Chưa tính được liều</span>
                                    @break
                                @default
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Còn ~{{ $qty($row['days_left']) }} ngày</span>
                            @endswitch
                            @if ($row['until'])
                                <div class="text-muted">Đợt điều trị tới {{ $row['until']->format('d/m/Y') }}</div>
                            @else
                                <div class="text-muted">Uống dài ngày</div>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($row['pending_review_quantity'] > 0)
                                <span class="badge bg-warning text-dark">Đã mua – chờ xác nhận</span>
                            @elseif ($row['awaiting_purchase'])
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Đã kê – chờ mua (mục 1)</span>
                            @elseif ($row['refill'])
                                <span class="badge {{ $row['refill']->status_badge_class }}">{{ $row['refill']->status_label }}</span>
                            @elseif (in_array($row['status'], ['out', 'low'], true))
                                @if ($canRequest)
                                    <form method="POST" action="{{ route('cabinet.refills.store') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="source" value="schedule">
                                        <input type="hidden" name="schedule_id" value="{{ $row['schedule_id'] }}">
                                        <button type="submit" class="btn btn-sm btn-warning">
                                            <i class="bi bi-arrow-repeat me-1"></i>Yêu cầu bổ sung
                                        </button>
                                    </form>
                                @elseif ($isReviewer && $row['prescription_item'])
                                    <a href="{{ route('treatment-courses.edit', $row['prescription_item']->treatment_course_id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square me-1"></i>Kê thêm trong đơn
                                    </a>
                                @endif
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Hiện không có thuốc nào đang uống theo lịch.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- 3. THUỐC TRONG TỦ SẮP HẾT / HẾT HẠN (DÙNG KHI CẦN) -->
<div class="content-card mb-3">
    <div class="content-card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="bi bi-box-seam text-secondary me-2"></i>3. Thuốc dùng khi cần sắp hết / hết hạn</span>
        <span class="text-muted small">Thuốc hạ sốt, vật tư y tế... không nằm trong lịch uống</span>
    </div>
    <div class="table-responsive">
        <table class="table table-erp align-middle mb-0">
            <thead>
                <tr>
                    <th>Thuốc / vật tư</th>
                    <th class="text-end">Còn</th>
                    <th>Hạn dùng</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cabinetLow as $row)
                    @php $lot = $row['item']; @endphp
                    <tr>
                        <td class="fw-semibold">{{ $lot->display_name }}<div class="text-muted small">{{ $lot->storage_location }}</div></td>
                        <td class="text-end">{{ $qty($lot->quantity) }} {{ $lot->unit }}</td>
                        <td class="small">
                            {{ $lot->expiry_date?->format('d/m/Y') }}
                            @if ($lot->is_expired)
                                <span class="badge bg-danger ms-1">Đã hết hạn</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($lot->is_expired)
                                {{-- Thuốc hết hạn: việc cần làm là loại bỏ, cần dùng lại thì hỏi Bác sĩ --}}
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Cần loại bỏ khỏi tủ</span>
                                <div class="text-muted small mt-1">Cần dùng lại thì hỏi Bác sĩ trước khi mua</div>
                            @elseif ($row['has_open_refill'])
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Đã gửi yêu cầu</span>
                            @elseif ($canRequest)
                                <form method="POST" action="{{ route('cabinet.refills.store') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="source" value="cabinet">
                                    <input type="hidden" name="cabinet_item_id" value="{{ $lot->id }}">
                                    <button type="submit" class="btn btn-sm btn-outline-warning text-dark">
                                        <i class="bi bi-cart-plus me-1"></i>Yêu cầu mua thêm
                                    </button>
                                </form>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">Không có thuốc dùng khi cần nào sắp hết hoặc hết hạn.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- 4. YÊU CẦU BỔ SUNG -->
<div class="content-card" id="refill-section">
    <div class="content-card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="bi bi-arrow-repeat text-warning me-2"></i>4. Yêu cầu bổ sung</span>
        @if ($pendingRefills->isNotEmpty())
            <span class="badge bg-warning text-dark">{{ $pendingRefills->count() }} chờ Bác sĩ</span>
        @endif
    </div>
    <div class="content-card-body p-0">
        @forelse ($refills as $refill)
            <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}" id="refill-{{ $refill->id }}">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="fw-bold">{{ $refill->medicine_name }}
                            <span class="badge {{ $refill->status_badge_class }} ms-1">{{ $refill->status_label }}</span>
                        </div>
                        <div class="text-muted small">
                            {{ $refill->source === 'schedule' ? 'Thuốc đang uống' : 'Thuốc trong tủ' }}
                            @if ($refill->prescriptionItem?->treatmentCourse) · Đơn: {{ $refill->prescriptionItem->treatmentCourse->title }} @endif
                            · còn {{ $qty($refill->current_stock) }} {{ $refill->unit }}
                            @if ($refill->days_left !== null) (đủ ~{{ $qty($refill->days_left) }} ngày) @endif
                            · {{ $refill->requester?->name }}, {{ $refill->created_at->format('H:i d/m/Y') }}
                        </div>
                        @if ($refill->requester_note)
                            <div class="small mt-1"><i class="bi bi-chat-left-quote me-1 text-primary"></i>{{ $refill->requester_note }}</div>
                        @endif
                        @if (!$refill->isPending() && $refill->reviewer)
                            <div class="small mt-1 text-muted">
                                {{ $refill->reviewer->name }}:
                                @if ($refill->approved_quantity) bổ sung {{ $qty($refill->approved_quantity) }} {{ $refill->unit }}@endif
                                @if ($refill->extended_until) · kéo dài tới {{ $refill->extended_until->format('d/m/Y') }}@endif
                                @if ($refill->review_note) · {{ $refill->review_note }}@endif
                            </div>
                        @endif
                    </div>
                    <div class="text-end">
                        @if ($refill->isPending() && $isReviewer)
                            <button class="btn btn-sm btn-erp-primary" type="button" data-bs-toggle="collapse" data-bs-target="#decide-{{ $refill->id }}">
                                <i class="bi bi-clipboard-check me-1"></i>Xử lý
                            </button>
                        @elseif ($refill->isCancellable() && ((int) $refill->requested_by === (int) $user->id || $user->isFamilyAdmin()))
                            <form method="POST" action="{{ route('cabinet.refills.cancel', $refill) }}" class="d-inline" onsubmit="return confirm('Hủy yêu cầu bổ sung này?')">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Hủy</button>
                            </form>
                        @endif
                    </div>
                </div>
                @if ($refill->isPending() && $isReviewer)
                    <div class="collapse mt-2" id="decide-{{ $refill->id }}">
                        @include('cabinet-supply.partials.refill-decision', ['refill' => $refill])
                    </div>
                @endif
            </div>
        @empty
            <div class="text-center text-muted py-4">Chưa có yêu cầu bổ sung nào.</div>
        @endforelse
    </div>
</div>
@endsection
