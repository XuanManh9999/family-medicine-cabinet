{{--
    Bộ lọc thời gian dùng chung cho khu Quản trị.
    Biến truyền vào: $dateFilter (App\Support\DateRangeFilter), $dateFilterLabel (tên cột ngày đang lọc, tùy chọn),
    $yearOptions (mảng năm, tùy chọn)
--}}
@php
    $dateFilterLabel = $dateFilterLabel ?? 'Ngày tạo';
    $yearOptions = $yearOptions ?? \App\Support\DateRangeFilter::yearOptions();
    $period = $dateFilter->period;
@endphp

<div class="col-12 col-md-auto" data-date-filter>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm" style="width: auto;">
            <span class="input-group-text bg-light" title="Lọc theo {{ mb_strtolower($dateFilterLabel) }}">
                <i class="bi bi-calendar3 text-muted me-1"></i><span class="small">{{ $dateFilterLabel }}</span>
            </span>
            <select name="period" class="form-select form-select-sm" data-date-filter-period aria-label="Khoảng thời gian">
                @foreach (\App\Support\DateRangeFilter::PERIODS as $value => $text)
                    <option value="{{ $value }}" @selected($period === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>

        <input type="date" name="date" class="form-control form-control-sm" style="width: 150px;"
               value="{{ $dateFilter->date }}" data-date-filter-field="day" aria-label="Chọn ngày">

        <input type="month" name="month" class="form-control form-control-sm" style="width: 160px;"
               value="{{ $dateFilter->month ?? now()->format('Y-m') }}" data-date-filter-field="month" aria-label="Chọn tháng">

        <select name="year" class="form-select form-select-sm" style="width: 110px;" data-date-filter-field="year" aria-label="Chọn năm">
            @foreach ($yearOptions as $y)
                <option value="{{ $y }}" @selected(($dateFilter->year ?? now()->year) === $y)>Năm {{ $y }}</option>
            @endforeach
        </select>

        <div class="d-flex align-items-center gap-1" data-date-filter-field="range">
            <input type="date" name="from" class="form-control form-control-sm" style="width: 150px;"
                   value="{{ $dateFilter->from }}" aria-label="Từ ngày" title="Từ ngày">
            <span class="text-muted small">→</span>
            <input type="date" name="to" class="form-control form-control-sm" style="width: 150px;"
                   value="{{ $dateFilter->to }}" aria-label="Đến ngày" title="Đến ngày">
        </div>

        @if ($dateFilter->isActive())
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal">
                <i class="bi bi-funnel-fill me-1"></i>{{ $dateFilter->label() }}
            </span>
        @endif
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-date-filter]').forEach(function (wrapper) {
                const periodSelect = wrapper.querySelector('[data-date-filter-period]');
                const fields = wrapper.querySelectorAll('[data-date-filter-field]');
                const presetPeriods = ['', 'today', '7d', '30d', 'this_month', 'last_month', 'this_quarter', 'this_year'];

                function syncFields() {
                    fields.forEach(function (field) {
                        const visible = field.dataset.dateFilterField === periodSelect.value;
                        field.classList.toggle('d-none', !visible);
                        // Ô bị ẩn không gửi lên URL để giữ query gọn
                        field.querySelectorAll('input, select').forEach(function (el) { el.disabled = !visible; });
                        if (field.matches('input, select')) field.disabled = !visible;
                    });
                }

                periodSelect.addEventListener('change', function () {
                    syncFields();
                    if (presetPeriods.includes(periodSelect.value)) {
                        periodSelect.form.submit();
                    }
                });

                fields.forEach(function (field) {
                    if (field.dataset.dateFilterField !== 'range') {
                        field.addEventListener('change', function () { periodSelect.form.submit(); });
                    }
                });

                syncFields();
            });
        </script>
    @endpush
@endonce
