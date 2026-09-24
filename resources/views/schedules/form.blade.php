@extends('layouts.app')

@section('title', ($editing ? 'Chỉnh sửa lịch uống thuốc' : 'Thêm lịch uống thuốc mới') . ' - Tủ thuốc Gia đình')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Tổng quan</a></li>
                <li class="breadcrumb-item"><a href="{{ route('schedules.index') }}" class="text-decoration-none">Lịch uống thuốc</a></li>
                <li class="breadcrumb-item active">{{ $editing ? 'Chỉnh sửa' : 'Tạo mới' }}</li>
            </ol>
        </nav>
        <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-alarm-fill text-primary fs-4"></i>
            {{ $editing ? 'Chỉnh sửa lịch uống: ' . $schedule->medicine_name : 'Thêm lịch uống thuốc / Vitamin định kỳ' }}
        </h5>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Quay lại lịch
        </a>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger py-2 px-3 mb-3">
        <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Vui lòng kiểm tra lại thông tin:</div>
        <ul class="mb-0 ps-3 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row justify-content-center">
    <div class="col-12 col-md-9 col-lg-7">
        <div class="content-card">
            <div class="content-card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">
                    <i class="bi bi-pencil-square text-primary me-2"></i>THÔNG TIN LỊCH UỐNG THUỐC
                </span>
                <span class="text-danger small">* Bắt buộc nhập</span>
            </div>

            <div class="content-card-body p-4">
                <form method="POST" action="{{ $editing ? route('schedules.update', $schedule) : route('schedules.store') }}">
                    @csrf
                    @if ($editing)
                        @method('PUT')
                    @endif

                    <!-- Thành viên gia đình -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="family_member_id">
                            Thành viên uống thuốc <span class="text-danger">*</span>
                        </label>
                        <select name="family_member_id" id="family_member_id" class="form-select @error('family_member_id') is-invalid @enderror" required>
                            <option value="">-- Chọn thành viên --</option>
                            @foreach ($members as $m)
                                <option value="{{ $m->id }}" {{ (string)old('family_member_id', $preselectedMemberId ?? $schedule->family_member_id) === (string)$m->id ? 'selected' : '' }}>
                                    {{ $m->name }} ({{ $m->relationship_label }}{{ $m->age ? ', ' . $m->age . ' tuổi' : '' }})
                                </option>
                            @endforeach
                        </select>
                        @error('family_member_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Tên thuốc & Chọn nhanh -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="medicine_name">
                            Tên thuốc hoặc Vitamin / Thực phẩm bổ sung <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="text" name="medicine_name" id="medicine_name" class="form-control @error('medicine_name') is-invalid @enderror"
                                value="{{ old('medicine_name', $schedule->medicine_name) }}"
                                placeholder="Ví dụ: Vitamin C 500mg, Glucosamine, Panadol..." required>
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                Chọn sẵn
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="max-height: 250px; overflow-y: auto; font-size: 12px;">
                                <li class="dropdown-header fw-bold text-primary">Thuốc trong Tủ thuốc:</li>
                                @forelse ($cabinetItems as $ci)
                                    <li>
                                        <a class="dropdown-item py-1" href="javascript:void(0)" onclick="selectMedicineQuickly('{{ addslashes($ci->medicine?->name ?? $ci->custom_medicine_name) }}', '{{ $ci->unit }}')">
                                            <i class="bi bi-box-seam me-1 text-success"></i>{{ $ci->medicine?->name ?? $ci->custom_medicine_name }} (Còn: {{ $ci->current_quantity }} {{ $ci->unit }})
                                        </a>
                                    </li>
                                @empty
                                    <li><span class="dropdown-item text-muted">Tủ thuốc chưa có thuốc</span></li>
                                @endforelse
                                <li><hr class="dropdown-divider"></li>
                                <li class="dropdown-header fw-bold text-primary">Danh mục thuốc:</li>
                                @foreach ($availableMedicines->take(15) as $stdMed)
                                    <li>
                                        <a class="dropdown-item py-1" href="javascript:void(0)" onclick="selectMedicineQuickly('{{ addslashes($stdMed->name) }}', '{{ $stdMed->default_unit }}')">
                                            <i class="bi bi-capsule me-1 text-primary"></i>{{ $stdMed->name }} ({{ $stdMed->concentration }})
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        @error('medicine_name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Liều dùng & Đơn vị -->
                    <div class="row g-3 mb-3">
                        <div class="col-8">
                            <label class="form-label fw-semibold" for="dosage">
                                Liều lượng mỗi lần uống <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="dosage" id="dosage" class="form-control @error('dosage') is-invalid @enderror"
                                value="{{ old('dosage', $schedule->dosage ?? '1 viên') }}" placeholder="Ví dụ: 1 viên, 2 gói, 10ml..." required>
                            @error('dosage')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-4">
                            <label class="form-label fw-semibold" for="unit">Đơn vị</label>
                            <input type="text" name="unit" id="unit" class="form-control @error('unit') is-invalid @enderror"
                                value="{{ old('unit', $schedule->unit ?? 'viên') }}" placeholder="viên/gói/ml">
                            @error('unit')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Cữ uống & Giờ quy định -->
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold" for="time_of_day">
                                Cữ uống trong ngày <span class="text-danger">*</span>
                            </label>
                            <select name="time_of_day" id="time_of_day" class="form-select @error('time_of_day') is-invalid @enderror" required onchange="autoSuggestTime(this.value)">
                                <option value="sang" {{ old('time_of_day', $schedule->time_of_day ?? 'sang') === 'sang' ? 'selected' : '' }}>
                                    Cữ Sáng (06:00 - 11:59)
                                </option>
                                <option value="trua" {{ old('time_of_day', $schedule->time_of_day) === 'trua' ? 'selected' : '' }}>
                                    Cữ Trưa (12:00 - 16:59)
                                </option>
                                <option value="chieu" {{ old('time_of_day', $schedule->time_of_day) === 'chieu' ? 'selected' : '' }}>
                                    Cữ Chiều (17:00 - 18:59)
                                </option>
                                <option value="toi" {{ old('time_of_day', $schedule->time_of_day) === 'toi' ? 'selected' : '' }}>
                                    Cữ Tối (19:00 - 23:59)
                                </option>
                            </select>
                            @error('time_of_day')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold" for="scheduled_time">
                                Giờ uống quy định <span class="text-danger">*</span>
                            </label>
                            <input type="time" name="scheduled_time" id="scheduled_time" class="form-control @error('scheduled_time') is-invalid @enderror"
                                value="{{ old('scheduled_time', $schedule->scheduled_time ? substr($schedule->scheduled_time, 0, 5) : '07:30') }}" required>
                            @error('scheduled_time')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Quy định bữa ăn -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="meal_timing">
                            Thời điểm uống so với bữa ăn <span class="text-danger">*</span>
                        </label>
                        <select name="meal_timing" id="meal_timing" class="form-select @error('meal_timing') is-invalid @enderror" required>
                            <option value="sau_an" {{ old('meal_timing', $schedule->meal_timing ?? 'sau_an') === 'sau_an' ? 'selected' : '' }}>
                                Sau khi ăn no (Khuyên dùng cho thuốc dạ dày, kháng sinh, giảm đau)
                            </option>
                            <option value="truoc_an" {{ old('meal_timing', $schedule->meal_timing) === 'truoc_an' ? 'selected' : '' }}>
                                Trước khi ăn 30 phút (Hấp thu lúc đói)
                            </option>
                            <option value="trong_khi_an" {{ old('meal_timing', $schedule->meal_timing) === 'trong_khi_an' ? 'selected' : '' }}>
                                Ngay trong bữa ăn
                            </option>
                            <option value="khong_phu_thuoc" {{ old('meal_timing', $schedule->meal_timing) === 'khong_phu_thuoc' ? 'selected' : '' }}>
                                Không phụ thuộc bữa ăn (Bổ sung vi chất, vitamin)
                            </option>
                        </select>
                        @error('meal_timing')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Thời gian áp dụng -->
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold" for="start_date">
                                Ngày bắt đầu <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="start_date" id="start_date" class="form-control @error('start_date') is-invalid @enderror"
                                value="{{ old('start_date', $schedule->start_date ? $schedule->start_date->format('Y-m-d') : date('Y-m-d')) }}" required>
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold" for="end_date">
                                Ngày kết thúc (tùy chọn)
                            </label>
                            <input type="date" name="end_date" id="end_date" class="form-control @error('end_date') is-invalid @enderror"
                                value="{{ old('end_date', $schedule->end_date ? $schedule->end_date->format('Y-m-d') : '') }}">
                            <div class="form-text small text-muted">Để trống nếu là thuốc duy trì lâu dài.</div>
                            @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Kích hoạt nhắc nhở -->
                    <div class="mb-4 p-3 bg-light rounded border">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                                {{ old('is_active', $schedule->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold text-dark" for="is_active">
                                <i class="bi bi-bell-fill text-primary me-1"></i>Bật nhắc nhở uống thuốc định kỳ hàng ngày
                            </label>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-erp-primary flex-grow-1 py-2 fw-bold">
                            <i class="bi bi-check2-circle me-1"></i>
                            {{ $editing ? 'Lưu thay đổi lịch uống' : 'Tạo lịch uống thuốc' }}
                        </button>
                        <a href="{{ route('schedules.index') }}" class="btn btn-outline-secondary px-4 py-2">
                            Hủy
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function selectMedicineQuickly(name, unit) {
    document.getElementById('medicine_name').value = name;
    if (unit) {
        document.getElementById('unit').value = unit;
    }
}

function autoSuggestTime(timeOfDay) {
    const timeInput = document.getElementById('scheduled_time');
    const defaultTimes = {
        'sang': '07:30',
        'trua': '12:00',
        'chieu': '17:30',
        'toi': '20:00'
    };
    if (defaultTimes[timeOfDay] && (!timeInput.value || timeInput.value === '07:30' || timeInput.value === '12:00' || timeInput.value === '17:30' || timeInput.value === '20:00')) {
        timeInput.value = defaultTimes[timeOfDay];
    }
}
</script>
@endpush
@endsection
