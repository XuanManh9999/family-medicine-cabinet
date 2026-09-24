@extends('layouts.app')

@section('title', 'Lịch uống thuốc & Nhật ký tuân thủ - Tủ thuốc Gia đình')

@section('content')
<!-- HEADER & DATE NAVIGATION -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-alarm-fill text-primary fs-4"></i>
            Lịch uống thuốc & Nhật ký tuân thủ
        </h5>
        <div class="text-muted small">
            Theo dõi cữ uống theo thời gian thực, nhắc nhở đúng giờ, ghi nhận tuân thủ và tự động trừ số lượng tồn trong Tủ thuốc.
        </div>
    </div>

    <!-- DATE CONTROLS & CREATE BUTTON -->
    <div class="d-flex flex-wrap align-items-center gap-2">
        @php
            $prevDate = $carbonDate->copy()->subDay()->toDateString();
            $nextDate = $carbonDate->copy()->addDay()->toDateString();
            $isToday = $carbonDate->isToday();
        @endphp

        <div class="btn-group btn-group-sm shadow-sm" role="group">
            <a href="{{ route('schedules.index', array_merge(request()->query(), ['date' => $prevDate])) }}" class="btn btn-outline-secondary" title="Ngày hôm trước">
                <i class="bi bi-chevron-left"></i>
            </a>
            <button type="button" class="btn btn-outline-secondary fw-bold px-3 d-flex align-items-center gap-2" onclick="document.getElementById('datePickerInput').showPicker ? document.getElementById('datePickerInput').showPicker() : document.getElementById('datePickerInput').focus()">
                <i class="bi bi-calendar3 text-primary"></i>
                <span>{{ $carbonDate->format('d/m/Y') }}</span>
                @if ($isToday)
                    <span class="badge bg-success" style="font-size: 10px;">Hôm nay</span>
                @endif
            </button>
            <input type="date" id="datePickerInput" value="{{ $selectedDate }}" style="position: absolute; opacity: 0; pointer-events: none; width: 0;" onchange="window.location.href = '{{ route('schedules.index', request()->except('date')) }}' + (window.location.search.includes('?') ? '&' : '?') + 'date=' + this.value">
            <a href="{{ route('schedules.index', array_merge(request()->query(), ['date' => $nextDate])) }}" class="btn btn-outline-secondary" title="Ngày kế tiếp">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        @if (!$isToday)
            <a href="{{ route('schedules.index', request()->except('date')) }}" class="btn btn-sm btn-outline-primary shadow-sm">
                Về hôm nay
            </a>
        @endif

        <a href="{{ route('schedules.create') }}" class="btn btn-erp-primary d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-circle"></i>
            <span>Thêm lịch uống</span>
        </a>
    </div>
</div>

<!-- KPI SUMMARY STATS -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-primary">
            <div>
                <div class="kpi-title text-primary">Cần uống trong ngày</div>
                <div class="kpi-number text-primary">{{ $totalDoses }} <span class="fs-6 fw-normal text-muted">cữ</span></div>
                <div class="text-muted" style="font-size: 11px;">Tổng các liều đã lập lịch</div>
            </div>
            <i class="bi bi-calendar2-check fs-2 text-primary opacity-75"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-success">
            <div>
                <div class="kpi-title text-success">Đã uống đúng giờ</div>
                <div class="kpi-number text-success">{{ $takenCount }} <span class="fs-6 fw-normal text-muted">cữ</span></div>
                <div class="text-muted" style="font-size: 11px;">Đã hoàn thành liều uống</div>
            </div>
            <i class="bi bi-check-circle-fill fs-2 text-success opacity-75"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-warning">
            <div>
                <div class="kpi-title text-warning-emphasis">Chưa uống / Chờ đến giờ</div>
                <div class="kpi-number text-warning-emphasis">{{ $remainingCount }} <span class="fs-6 fw-normal text-muted">cữ</span></div>
                <div class="text-muted" style="font-size: 11px;">Các cữ tiếp theo trong ngày</div>
            </div>
            <i class="bi bi-hourglass-split fs-2 text-warning opacity-75"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-box border-start border-4 border-info">
            <div class="w-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="kpi-title text-info">Tỷ lệ tuân thủ</div>
                    <div class="fw-bold fs-5 text-info">{{ $adherenceRate }}%</div>
                </div>
                <div class="progress mt-1 mb-1" style="height: 6px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: {{ $adherenceRate }}%;"></div>
                </div>
                <div class="text-muted" style="font-size: 11px;">
                    @if ($missedCount > 0)
                        <span class="text-danger"><i class="bi bi-exclamation-circle"></i> {{ $missedCount }} cữ quên/bỏ qua</span>
                    @else
                        <span class="text-success"><i class="bi bi-shield-check"></i> Uống thuốc rất đều đặn</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- VIEW NAVIGATION TABS & FILTER TOOLBAR -->
<div class="card mb-3 border shadow-sm">
    <div class="card-header bg-white p-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <ul class="nav nav-pills card-header-pills small" id="scheduleViewTabs">
            <li class="nav-item">
                <a class="nav-link {{ $viewMode === 'timeline' ? 'active fw-bold' : 'text-dark' }}" href="{{ route('schedules.index', array_merge(request()->query(), ['view' => 'timeline'])) }}">
                    <i class="bi bi-clock-history me-1"></i>Dòng thời gian các cữ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $viewMode === 'all' ? 'active fw-bold' : 'text-dark' }}" href="{{ route('schedules.index', array_merge(request()->query(), ['view' => 'all'])) }}">
                    <i class="bi bi-list-task me-1"></i>Quản lý lịch lặp ({{ $allSchedules ? $allSchedules->total() : '' }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $viewMode === 'logs' ? 'active fw-bold' : 'text-dark' }}" href="{{ route('schedules.index', array_merge(request()->query(), ['view' => 'logs'])) }}">
                    <i class="bi bi-journal-check me-1"></i>Nhật ký tuân thủ chi tiết
                </a>
            </li>
        </ul>

        <!-- FILTER BAR -->
        <form method="GET" action="{{ route('schedules.index') }}" class="d-flex align-items-center gap-2">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <input type="hidden" name="date" value="{{ $selectedDate }}">

            <select name="member_id" class="form-select form-select-sm" style="width: auto; min-width: 170px;" onchange="this.form.submit()">
                <option value="">-- Tất cả thành viên --</option>
                @foreach ($members as $m)
                    <option value="{{ $m->id }}" {{ (string)$memberId === (string)$m->id ? 'selected' : '' }}>
                        {{ $m->name }} ({{ $m->relationship_label }})
                    </option>
                @endforeach
            </select>

            @if ($viewMode === 'timeline')
                <select name="time_of_day" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="all" {{ $timeOfDay === 'all' ? 'selected' : '' }}>Tất cả cữ</option>
                    <option value="sang" {{ $timeOfDay === 'sang' ? 'selected' : '' }}>Sáng (07:30)</option>
                    <option value="trua" {{ $timeOfDay === 'trua' ? 'selected' : '' }}>Trưa (12:00)</option>
                    <option value="chieu" {{ $timeOfDay === 'chieu' ? 'selected' : '' }}>Chiều (17:30)</option>
                    <option value="toi" {{ $timeOfDay === 'toi' ? 'selected' : '' }}>Tối (20:00)</option>
                </select>
            @endif

            @if(request()->hasAny(['member_id', 'time_of_day']))
                <a href="{{ route('schedules.index', ['view' => $viewMode, 'date' => $selectedDate]) }}" class="btn btn-sm btn-outline-secondary" title="Đặt lại bộ lọc">
                    <i class="bi bi-x-circle"></i>
                </a>
            @endif
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- VIEW 1: DÒNG THỜI GIAN THEO NGÀY (TIMELINE) -->
<!-- ========================================== -->
@if ($viewMode === 'timeline')
    @php
        $anyScheduleInDay = false;
        foreach ($timelineSlots as $slot) {
            if ($slot['items']->count() > 0) {
                $anyScheduleInDay = true;
                break;
            }
        }
    @endphp

    @if (!$anyScheduleInDay)
        <div class="content-card text-center py-5">
            <i class="bi bi-calendar-check fs-1 text-secondary opacity-50 d-block mb-2"></i>
            <h6 class="fw-bold text-dark">Không có cữ uống thuốc nào được lập cho ngày {{ $carbonDate->format('d/m/Y') }}</h6>
            <p class="text-muted small mb-3">
                Bạn có thể tạo lịch uống định kỳ từ Đơn thuốc của bác sĩ hoặc tự thêm lịch bổ sung vitamin/thuốc dài hạn.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="{{ route('treatment-courses.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-journal-medical me-1"></i>Xem danh sách Đơn thuốc
                </a>
                <a href="{{ route('schedules.create') }}" class="btn btn-sm btn-erp-primary">
                    <i class="bi bi-plus-circle me-1"></i>Thêm lịch uống riêng
                </a>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($timelineSlots as $slotKey => $slot)
                @if ($timeOfDay !== 'all' && $timeOfDay !== $slotKey)
                    @continue
                @endif

                <div class="col-12">
                    <div class="card border shadow-sm">
                        <!-- SLOT HEADER -->
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <i class="{{ $slot['icon'] }} fs-5"></i>
                                <span class="fw-bold text-dark">{{ $slot['title'] }}</span>
                                <span class="badge bg-white text-secondary border small">{{ $slot['time_range'] }}</span>
                            </div>
                            <span class="badge {{ $slot['items']->count() ? 'bg-primary-subtle text-primary' : 'bg-light text-muted border' }}">
                                {{ $slot['items']->count() }} cữ thuốc
                            </span>
                        </div>

                        <!-- SLOT DOSES LIST -->
                        <div class="card-body p-3">
                            @if ($slot['items']->isEmpty())
                                <div class="text-center py-3 text-muted small fst-italic">
                                    Không có thuốc cần uống trong cữ này.
                                </div>
                            @else
                                <div class="row g-3">
                                    @foreach ($slot['items'] as $item)
                                        @php
                                            $log = $item->getLogForDate($carbonDate);
                                            $status = $log ? $log->status : 'chua_uong';
                                            $scheduledTimeFormatted = substr($item->scheduled_time, 0, 5);
                                            $scheduledDateTimeStr = $carbonDate->format('Y-m-d') . ' ' . $item->scheduled_time;
                                        @endphp

                                        <div class="col-12 col-md-6 col-xl-4" id="schedule-card-{{ $item->id }}">
                                            <div class="card h-100 border {{ $status === 'da_uong' ? 'border-success bg-success-subtle bg-opacity-10' : ($status === 'quen_uong' || $status === 'bo_qua' ? 'border-secondary bg-light' : 'border-primary border-opacity-25') }} shadow-none">
                                                <div class="card-body p-3 d-flex flex-column justify-content-between">
                                                    <!-- THÔNG TIN NGƯỜI BỆNH & THUỐC -->
                                                    <div>
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div class="d-flex align-items-center gap-2">
                                                                @if ($item->familyMember?->avatar_url)
                                                                    <img src="{{ $item->familyMember->avatar_url }}" alt="{{ $item->familyMember->name }}" class="rounded-circle border" style="width: 28px; height: 28px; object-fit: cover;">
                                                                @else
                                                                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold small" style="width: 28px; height: 28px; font-size: 11px;">
                                                                        {{ mb_substr($item->familyMember?->name ?? 'U', 0, 1) }}
                                                                    </div>
                                                                @endif
                                                                <div>
                                                                    <a href="{{ route('members.show', $item->familyMember) }}" class="fw-bold text-decoration-none text-dark small hover-primary">
                                                                        {{ $item->familyMember?->name }}
                                                                    </a>
                                                                    <span class="text-muted" style="font-size: 10.5px;">({{ $item->familyMember?->relationship_label }})</span>
                                                                </div>
                                                            </div>

                                                            <span class="badge bg-light text-dark border fw-bold" style="font-size: 11px;">
                                                                <i class="bi bi-clock me-1 text-primary"></i>{{ $scheduledTimeFormatted }}
                                                            </span>
                                                        </div>

                                                        <div class="mb-2">
                                                            <div class="fw-bold text-dark fs-6">{{ $item->medicine_name }}</div>
                                                            <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                                    Liều: {{ $item->dosage }} {{ $item->unit }}
                                                                </span>
                                                                <span class="badge bg-light text-dark border">
                                                                    {{ $item->meal_timing_label }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        @if ($item->prescriptionItem && $item->prescriptionItem->treatmentCourse)
                                                            <div class="small text-muted mb-2 text-truncate" style="font-size: 11px;">
                                                                <i class="bi bi-journal-medical text-primary me-1"></i>
                                                                Đơn: <a href="{{ route('treatment-courses.show', $item->prescriptionItem->treatmentCourse) }}" class="text-decoration-none text-muted">
                                                                    {{ $item->prescriptionItem->treatmentCourse->title }}
                                                                </a>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <!-- TRẠNG THÁI & NÚT THAO TÁC 1 CHẠM -->
                                                    <div class="pt-2 border-top mt-2">
                                                        @if ($status === 'da_uong')
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="badge bg-success d-inline-flex align-items-center gap-1 py-1 px-2">
                                                                    <i class="bi bi-check-circle-fill"></i>
                                                                    Đã uống {{ $log->actual_taken_time ? '(' . $log->actual_taken_time->format('H:i') . ')' : '' }}
                                                                </span>
                                                                <button type="button" class="btn btn-xs btn-link text-muted p-0 small text-decoration-none" onclick="openTakeMedModal('{{ $item->id }}', '{{ addslashes($item->medicine_name) }}', '{{ $scheduledDateTimeStr }}', 'da_uong', '{{ addslashes($log->note ?? '') }}')">
                                                                    <i class="bi bi-pencil"></i> Sửa
                                                                </button>
                                                            </div>
                                                            @if ($log->note)
                                                                <div class="small text-muted mt-1 fst-italic" style="font-size: 11px;">
                                                                    "{{ $log->note }}"
                                                                </div>
                                                            @endif
                                                        @elseif ($status === 'quen_uong' || $status === 'bo_qua')
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="badge bg-secondary d-inline-flex align-items-center gap-1 py-1 px-2">
                                                                    <i class="bi bi-dash-circle-fill"></i>
                                                                    {{ $status === 'quen_uong' ? 'Quên uống' : 'Bỏ qua cữ' }}
                                                                </span>
                                                                <button type="button" class="btn btn-xs btn-link text-primary p-0 small text-decoration-none" onclick="quickTakeMed('{{ $item->id }}', '{{ $scheduledDateTimeStr }}')">
                                                                    Uống bù ngay
                                                                </button>
                                                            </div>
                                                            @if ($log && $log->note)
                                                                <div class="small text-muted mt-1 fst-italic" style="font-size: 11px;">
                                                                    Lý do: {{ $log->note }}
                                                                </div>
                                                            @endif
                                                        @else
                                                            <!-- CHƯA UỐNG: NÚT THAO TÁC 1 CHẠM -->
                                                            <div class="d-flex gap-2">
                                                                <button type="button" class="btn btn-sm btn-erp-success flex-grow-1 d-flex align-items-center justify-content-center gap-1 py-1" onclick="quickTakeMed('{{ $item->id }}', '{{ $scheduledDateTimeStr }}')">
                                                                    <i class="bi bi-check-lg"></i>
                                                                    <span>Đã uống</span>
                                                                </button>

                                                                <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1" onclick="openSkipMedModal('{{ $item->id }}', '{{ addslashes($item->medicine_name) }}', '{{ $scheduledDateTimeStr }}')" title="Bỏ qua hoặc Quên uống">
                                                                    <i class="bi bi-x-lg"></i>
                                                                </button>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endif

<!-- ========================================== -->
<!-- VIEW 2: QUẢN LÝ TOÀN BỘ LỊCH LẶP (ALL) -->
<!-- ========================================== -->
@if ($viewMode === 'all')
    <div class="content-card">
        <div class="content-card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark">
                <i class="bi bi-list-task text-primary me-2"></i>DANH SÁCH LỊCH UỐNG THUỐC ĐỊNH KỲ ({{ $allSchedules->total() }})
            </span>
            <span class="text-muted small">Bật hoặc tắt nhắc nhở cho từng cữ thuốc</span>
        </div>

        <div class="table-responsive">
            <table class="table table-erp table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 45px;" class="text-center">STT</th>
                        <th style="width: 200px;">Thành viên</th>
                        <th>Tên thuốc & Nguồn đơn</th>
                        <th style="width: 130px;">Liều dùng</th>
                        <th style="width: 140px;">Cữ & Giờ quy định</th>
                        <th style="width: 170px;">Quy định bữa ăn</th>
                        <th style="width: 160px;">Thời gian áp dụng</th>
                        <th style="width: 110px;" class="text-center">Nhắc nhở</th>
                        <th style="width: 100px;" class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($allSchedules as $idx => $s)
                        <tr>
                            <td class="text-center text-muted">{{ $allSchedules->firstItem() + $idx }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($s->familyMember?->avatar_url)
                                        <img src="{{ $s->familyMember->avatar_url }}" alt="{{ $s->familyMember->name }}" class="rounded-circle border" style="width: 30px; height: 30px; object-fit: cover;">
                                    @else
                                        <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold small" style="width: 30px; height: 30px; font-size: 11px;">
                                            {{ mb_substr($s->familyMember?->name ?? 'U', 0, 1) }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="fw-bold text-dark small">{{ $s->familyMember?->name }}</div>
                                        <span class="badge bg-light text-secondary border" style="font-size: 10px;">{{ $s->familyMember?->relationship_label }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $s->medicine_name }}</div>
                                @if ($s->prescriptionItem && $s->prescriptionItem->treatmentCourse)
                                    <div class="text-muted small" style="font-size: 11px;">
                                        Đơn: {{ $s->prescriptionItem->treatmentCourse->title }}
                                    </div>
                                @else
                                    <span class="badge bg-light text-muted border" style="font-size: 10px;">Thuốc tự thêm</span>
                                @endif
                            </td>
                            <td><strong>{{ $s->dosage }}</strong> {{ $s->unit }}</td>
                            <td>
                                <span class="badge {{ $s->time_of_day_badge_class }}">
                                    {{ $s->time_of_day_label }}
                                </span>
                                <span class="fw-bold text-dark ms-1">{{ substr($s->scheduled_time, 0, 5) }}</span>
                            </td>
                            <td><span class="badge bg-light text-dark border">{{ $s->meal_timing_label }}</span></td>
                            <td class="small">
                                <div>Từ: {{ $s->start_date ? $s->start_date->format('d/m/Y') : '—' }}</div>
                                <div>Đến: {{ $s->end_date ? $s->end_date->format('d/m/Y') : 'Dài hạn' }}</div>
                            </td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('schedules.toggle', $s) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm py-0 px-2 {{ $s->is_active ? 'btn-success' : 'btn-outline-secondary' }}" style="font-size: 11.5px;" title="Nhấn để Bật/Tắt">
                                        {{ $s->is_active ? 'Đang bật' : 'Tạm dừng' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border px-2 py-1" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-1" style="font-size: 12.5px;">
                                        <li>
                                            <a class="dropdown-item py-1" href="{{ route('schedules.edit', $s) }}">
                                                <i class="bi bi-pencil text-warning me-2"></i>Chỉnh sửa lịch
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <form method="POST" action="{{ route('schedules.destroy', $s) }}" onsubmit="return confirm('Bạn có chắc chắn muốn xóa lịch uống thuốc này?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item py-1 text-danger">
                                                    <i class="bi bi-trash me-2"></i>Xóa lịch
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                Chưa có lịch uống thuốc định kỳ nào.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($allSchedules && $allSchedules->hasPages())
            <div class="p-3 border-top d-flex justify-content-end">
                {{ $allSchedules->links() }}
            </div>
        @endif
    </div>
@endif

<!-- ========================================== -->
<!-- VIEW 3: NHẬT KÝ TUÂN THỦ (LOGS) -->
<!-- ========================================== -->
@if ($viewMode === 'logs')
    <div class="content-card">
        <div class="content-card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark">
                <i class="bi bi-journal-check text-primary me-2"></i>LỊCH SỬ NHẬT KÝ UỐNG THUỐC
            </span>
            <span class="text-muted small">Ghi nhận chi tiết thời điểm thực tế uống thuốc của các thành viên</span>
        </div>

        <div class="table-responsive">
            <table class="table table-erp table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 45px;" class="text-center">STT</th>
                        <th style="width: 150px;">Thời gian quy định</th>
                        <th style="width: 180px;">Người bệnh</th>
                        <th>Thuốc & Cữ uống</th>
                        <th style="width: 150px;">Giờ uống thực tế</th>
                        <th style="width: 130px;" class="text-center">Trạng thái</th>
                        <th>Ghi chú tại thời điểm uống</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $lIdx => $log)
                        <tr>
                            <td class="text-center text-muted">{{ $logs->firstItem() + $lIdx }}</td>
                            <td>
                                <div class="fw-bold text-dark">{{ $log->scheduled_time ? $log->scheduled_time->format('d/m/Y') : '—' }}</div>
                                <div class="text-muted small">{{ $log->scheduled_time ? $log->scheduled_time->format('H:i') : '' }}</div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $log->familyMember?->name }}</div>
                                <span class="badge bg-light text-secondary border" style="font-size: 10px;">{{ $log->familyMember?->relationship_label }}</span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $log->schedule?->medicine_name ?? 'Thuốc' }}</div>
                                <div class="small text-muted">
                                    Cữ {{ $log->schedule?->time_of_day_label ?? '—' }} ({{ $log->schedule?->dosage }} {{ $log->schedule?->unit }})
                                </div>
                            </td>
                            <td>
                                @if ($log->actual_taken_time)
                                    <span class="text-success fw-semibold">
                                        <i class="bi bi-check-circle me-1"></i>{{ $log->actual_taken_time->format('d/m H:i') }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $log->status_badge_class }}">
                                    {{ $log->status_label }}
                                </span>
                            </td>
                            <td class="small text-muted">
                                {{ $log->note ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                Chưa có dữ liệu nhật ký uống thuốc nào được ghi nhận.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs && $logs->hasPages())
            <div class="p-3 border-top d-flex justify-content-end">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endif

<!-- MODAL: GHI NHẬN ĐÃ UỐNG KÈM GHI CHÚ & TRỪ KHO -->
<div class="modal fade" id="takeMedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="{{ route('schedules.log') }}" id="takeMedForm">
                @csrf
                <input type="hidden" name="schedule_id" id="take_schedule_id" value="">
                <input type="hidden" name="scheduled_time" id="take_scheduled_time" value="">
                <input type="hidden" name="status" value="da_uong">

                <div class="modal-header bg-success text-white py-2">
                    <h6 class="modal-title fw-bold">
                        <i class="bi bi-check-circle-fill me-1"></i>Ghi nhận đã uống thuốc
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3 text-center">
                        <span class="text-muted small">Thuốc:</span>
                        <h5 class="fw-bold text-success mb-1" id="take_med_name">Tên thuốc</h5>
                        <div class="text-muted small" id="take_scheduled_time_display">Thời gian</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="actual_taken_time">Giờ uống thực tế</label>
                        <input type="datetime-local" name="actual_taken_time" id="actual_taken_time" class="form-control form-control-sm" value="{{ date('Y-m-d\TH:i') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="take_note">Ghi chú sức khỏe (tùy chọn)</label>
                        <input type="text" name="note" id="take_note" class="form-control form-control-sm" placeholder="VD: Huyết áp đo được 120/80, uống sau ăn sáng 30p...">
                    </div>

                    <div class="form-check form-switch p-2 bg-light rounded border">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="deduct_cabinet" id="deduct_cabinet" value="1" checked>
                        <label class="form-check-label small fw-semibold text-dark" for="deduct_cabinet">
                            <i class="bi bi-box-seam text-primary me-1"></i>Tự động trừ số lượng trong Tủ thuốc gia đình
                        </label>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-sm btn-success fw-bold px-3">Xác nhận đã uống</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: BỎ QUA HOẶC QUÊN UỐNG -->
<div class="modal fade" id="skipMedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="{{ route('schedules.log') }}" id="skipMedForm">
                @csrf
                <input type="hidden" name="schedule_id" id="skip_schedule_id" value="">
                <input type="hidden" name="scheduled_time" id="skip_scheduled_time" value="">

                <div class="modal-header bg-secondary text-white py-2">
                    <h6 class="modal-title fw-bold">
                        <i class="bi bi-dash-circle-fill me-1"></i>Ghi nhận Quên hoặc Bỏ qua cữ thuốc
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3 text-center">
                        <span class="text-muted small">Thuốc:</span>
                        <h6 class="fw-bold text-dark mb-0" id="skip_med_name">Tên thuốc</h6>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tình trạng</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="status_quen" value="quen_uong" checked>
                                <label class="form-check-label small" for="status_quen">Quên uống</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="status_bo" value="bo_qua">
                                <label class="form-check-label small" for="status_bo">Chủ động bỏ qua (theo chỉ định/bận)</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="skip_note">Lý do</label>
                        <input type="text" name="note" id="skip_note" class="form-control form-control-sm" placeholder="VD: Hết thuốc, bận việc đột xuất, buồn nôn...">
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-sm btn-danger px-3">Ghi nhận</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Thao tác nhanh 1 chạm: Đã uống (AJAX)
function quickTakeMed(scheduleId, scheduledTimeStr) {
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('schedule_id', scheduleId);
    formData.append('scheduled_time', scheduledTimeStr);
    formData.append('status', 'da_uong');
    formData.append('deduct_cabinet', '1');

    fetch('{{ route('schedules.log') }}', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Không thể ghi nhận: ' + (data.message || 'Lỗi hệ thống'));
        }
    })
    .catch(err => {
        window.location.reload();
    });
}

function openTakeMedModal(scheduleId, medName, scheduledTimeStr, status, note) {
    document.getElementById('take_schedule_id').value = scheduleId;
    document.getElementById('take_scheduled_time').value = scheduledTimeStr;
    document.getElementById('take_med_name').textContent = medName;
    document.getElementById('take_scheduled_time_display').textContent = 'Quy định: ' + scheduledTimeStr;
    document.getElementById('take_note').value = note || '';

    new bootstrap.Modal(document.getElementById('takeMedModal')).show();
}

function openSkipMedModal(scheduleId, medName, scheduledTimeStr) {
    document.getElementById('skip_schedule_id').value = scheduleId;
    document.getElementById('skip_scheduled_time').value = scheduledTimeStr;
    document.getElementById('skip_med_name').textContent = medName;

    new bootstrap.Modal(document.getElementById('skipMedModal')).show();
}
</script>
@endpush
@endsection
