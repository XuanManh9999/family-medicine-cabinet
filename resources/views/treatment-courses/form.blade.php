@extends('layouts.app')

@section('title', ($editing ? 'Chỉnh sửa đợt điều trị' : 'Kê đơn / Lập đợt điều trị mới') . ' - Tủ thuốc Gia đình')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Tổng quan</a></li>
                <li class="breadcrumb-item"><a href="{{ route('treatment-courses.index') }}" class="text-decoration-none">Đơn thuốc & Điều trị</a></li>
                <li class="breadcrumb-item active">{{ $editing ? 'Chỉnh sửa đợt điều trị' : 'Lập đợt điều trị mới' }}</li>
            </ol>
        </nav>
        <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <i class="bi bi-prescription2 text-primary fs-4"></i>
            {{ $editing ? 'Chỉnh sửa đợt điều trị: ' . $treatmentCourse->title : 'Kê đơn thuốc & Lập đợt điều trị mới' }}
        </h5>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ $editing ? route('treatment-courses.show', $treatmentCourse) : route('treatment-courses.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Quay lại
        </a>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger py-2 px-3 mb-3">
        <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Vui lòng kiểm tra lại dữ liệu:</div>
        <ul class="mb-0 ps-3 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $editing ? route('treatment-courses.update', $treatmentCourse) : route('treatment-courses.store') }}" enctype="multipart/form-data" id="treatmentCourseForm">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="row g-3">
        <!-- CỘT TRÁI (8 CỘT): THÔNG TIN ĐỢT ĐIỀU TRỊ & BẢNG THUỐC -->
        <div class="col-12 col-lg-8">
            <!-- KHỐI 1: THÔNG TIN HÀNH CHÍNH & CHẨN ĐOÁN -->
            <div class="content-card mb-3">
                <div class="content-card-header d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-info-circle-fill text-primary me-2"></i>1. THÔNG TIN BỆNH NHÂN & BỆNH ÁN
                    </span>
                    <span class="text-danger small">* Bắt buộc nhập</span>
                </div>
                <div class="content-card-body p-3">
                    <div class="row g-3">
                        <!-- Chọn người bệnh -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="family_member_id">
                                Bệnh nhân được kê đơn <span class="text-danger">*</span>
                            </label>
                            <select name="family_member_id" id="family_member_id" class="form-select @error('family_member_id') is-invalid @enderror" required onchange="handleMemberChange(this)">
                                <option value="">-- Chọn thành viên trong gia đình --</option>
                                @foreach ($members as $m)
                                    <option value="{{ $m->id }}"
                                        data-name="{{ $m->name }}"
                                        data-allergies="{{ $m->allergies ?? '' }}"
                                        data-diseases="{{ $m->underlying_diseases ?? '' }}"
                                        data-blood="{{ $m->blood_type ?? 'Chưa rõ' }}"
                                        data-age="{{ $m->age ?? '—' }}"
                                        {{ (string)old('family_member_id', $preselectedMemberId ?? $treatmentCourse->family_member_id) === (string)$m->id ? 'selected' : '' }}>
                                        {{ $m->name }} ({{ $m->relationship_label }}{{ $m->age ? ', ' . $m->age . ' tuổi' : '' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('family_member_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Trạng thái đợt điều trị -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="status">
                                Trạng thái điều trị <span class="text-danger">*</span>
                            </label>
                            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="dang_dieu_tri" {{ old('status', $treatmentCourse->status ?? 'dang_dieu_tri') === 'dang_dieu_tri' ? 'selected' : '' }}>
                                    Đang điều trị (Đang uống thuốc)
                                </option>
                                <option value="da_hoan_thanh" {{ old('status', $treatmentCourse->status) === 'da_hoan_thanh' ? 'selected' : '' }}>
                                    Đã hoàn thành (Khỏi bệnh / Dứt điểm)
                                </option>
                                <option value="tam_dung" {{ old('status', $treatmentCourse->status) === 'tam_dung' ? 'selected' : '' }}>
                                    Tạm dừng theo chỉ định bác sĩ
                                </option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Tiêu đề đợt bệnh / tên đơn -->
                        <div class="col-12 col-md-7">
                            <label class="form-label fw-semibold" for="title">
                                Tên đợt điều trị / Tiêu đề đơn thuốc <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                                value="{{ old('title', $treatmentCourse->title) }}"
                                placeholder="Ví dụ: Đợt điều trị Viêm họng cấp, Đơn thuốc Huyết áp tháng 09/2026..." required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Cơ sở y tế / Bác sĩ -->
                        <div class="col-12 col-md-5">
                            <label class="form-label fw-semibold" for="hospital_doctor">
                                Bệnh viện / Phòng khám - Bác sĩ
                            </label>
                            <input type="text" name="hospital_doctor" id="hospital_doctor" class="form-control @error('hospital_doctor') is-invalid @enderror"
                                value="{{ old('hospital_doctor', $treatmentCourse->hospital_doctor) }}"
                                placeholder="Ví dụ: Bệnh viện Bạch Mai - BS. Tuấn">
                            @error('hospital_doctor')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Chẩn đoán y khoa -->
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="diagnosis">
                                Chẩn đoán y khoa & Triệu chứng bệnh
                            </label>
                            <textarea name="diagnosis" id="diagnosis" rows="2" class="form-control @error('diagnosis') is-invalid @enderror"
                                placeholder="Ví dụ: Viêm amidan hốc mủ, sốt cao 39 độ, ho khan từng cơn...">{{ old('diagnosis', $treatmentCourse->diagnosis) }}</textarea>
                            @error('diagnosis')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Ngày bắt đầu & Kết thúc -->
                        <div class="col-6 col-md-6">
                            <label class="form-label fw-semibold" for="start_date">
                                Ngày bắt đầu uống <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="start_date" id="start_date" class="form-control @error('start_date') is-invalid @enderror"
                                value="{{ old('start_date', $treatmentCourse->start_date ? $treatmentCourse->start_date->format('Y-m-d') : date('Y-m-d')) }}" required>
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-6 col-md-6">
                            <label class="form-label fw-semibold" for="end_date">
                                Ngày kết thúc dự kiến
                            </label>
                            <input type="date" name="end_date" id="end_date" class="form-control @error('end_date') is-invalid @enderror"
                                value="{{ old('end_date', $treatmentCourse->end_date ? $treatmentCourse->end_date->format('Y-m-d') : date('Y-m-d', strtotime('+7 days'))) }}">
                            <div class="form-text small text-muted">Để trống nếu bệnh mạn tính dùng thuốc duy trì dài hạn.</div>
                            @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- KHỐI 2: DANH SÁCH THUỐC KÊ ĐƠN (DYNAMIC MEDICINE REPEATER) -->
            <div class="content-card mb-3">
                <div class="content-card-header d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold text-dark">
                            <i class="bi bi-capsule text-primary me-2"></i>2. CÁC LOẠI THUỐC TRONG ĐƠN BÁC SĨ
                        </span>
                        <span class="badge bg-primary-subtle text-primary ms-1" id="medicineCountBadge">1 loại</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addMedicineRow()">
                        <i class="bi bi-plus-lg me-1"></i>Thêm thuốc vào đơn
                    </button>
                </div>

                <div class="p-3 bg-light border-bottom">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="auto_generate_schedules" id="auto_generate_schedules" value="1" checked>
                        <label class="form-check-label fw-semibold text-dark" for="auto_generate_schedules">
                            <i class="bi bi-alarm-fill text-warning me-1"></i>Tự động đồng bộ sang Lịch uống thuốc hàng ngày của người bệnh
                        </label>
                        <div class="form-text small text-muted ps-4">
                            Hệ thống sẽ tự động tạo các mốc giờ uống (Sáng: 07:30, Trưa: 12:00, Chiều: 17:30, Tối: 20:00) theo các ô chọn bên dưới.
                        </div>
                    </div>
                </div>

                <div class="content-card-body p-3">
                    <div id="medicinesContainer">
                        @php
                            $items = old('items', $treatmentCourse->prescriptionItems->count() ? $treatmentCourse->prescriptionItems->toArray() : [[]]);
                        @endphp

                        @foreach ($items as $index => $item)
                            <div class="medicine-row card mb-3 border shadow-none" id="med-row-{{ $index }}">
                                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark medicine-number">
                                        <i class="bi bi-tag-fill text-primary me-1"></i>Thuốc số {{ $index + 1 }}
                                    </span>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeMedicineRow('{{ $index }}')" title="Xóa thuốc này">
                                        <i class="bi bi-trash"></i> Xóa
                                    </button>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row g-2">
                                        <!-- Chọn nhanh từ tủ thuốc hoặc nhập tên -->
                                        <div class="col-12 col-md-7">
                                            <label class="form-label small fw-semibold">
                                                Tên thuốc & Hàm lượng <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="items[{{ $index }}][medicine_name]" class="form-control med-name-input"
                                                    value="{{ $item['medicine_name'] ?? '' }}"
                                                    placeholder="Ví dụ: Augmentin 1g, Panadol Extra..." required>
                                                <input type="hidden" name="items[{{ $index }}][medicine_id]" class="med-id-input" value="{{ $item['medicine_id'] ?? '' }}">
                                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Chọn từ thuốc có sẵn">
                                                    Chọn sẵn
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="max-height: 250px; overflow-y: auto; font-size: 12px;">
                                                    <li class="dropdown-header fw-bold text-primary">Thuốc hiện có trong Tủ:</li>
                                                    @forelse ($cabinetItems as $ci)
                                                        <li>
                                                            <a class="dropdown-item py-1" href="javascript:void(0)" onclick="selectMedicineQuickly('{{ $index }}', '{{ addslashes($ci->medicine?->name ?? $ci->custom_medicine_name) }}', '{{ $ci->medicine_id }}', '{{ $ci->unit }}')">
                                                                <i class="bi bi-box-seam me-1 text-success"></i>{{ $ci->medicine?->name ?? $ci->custom_medicine_name }} (Còn: {{ $ci->current_quantity }} {{ $ci->unit }})
                                                            </a>
                                                        </li>
                                                    @empty
                                                        <li><span class="dropdown-item text-muted">Tủ thuốc gia đình chưa có thuốc</span></li>
                                                    @endforelse
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li class="dropdown-header fw-bold text-primary">Danh mục thuốc chuẩn:</li>
                                                    @foreach ($availableMedicines->take(15) as $stdMed)
                                                        <li>
                                                            <a class="dropdown-item py-1" href="javascript:void(0)" onclick="selectMedicineQuickly('{{ $index }}', '{{ addslashes($stdMed->name) }}', '{{ $stdMed->id }}', '{{ $stdMed->default_unit }}')">
                                                                <i class="bi bi-capsule me-1 text-primary"></i>{{ $stdMed->name }} ({{ $stdMed->concentration }})
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Liều lượng mỗi lần uống -->
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small fw-semibold">
                                                Liều mỗi lần <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="items[{{ $index }}][dosage]" class="form-control form-control-sm"
                                                value="{{ $item['dosage'] ?? '1 viên' }}" placeholder="1 viên / 2 gói..." required>
                                        </div>

                                        <!-- Đơn vị tính -->
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small fw-semibold">Đơn vị</label>
                                            <input type="text" name="items[{{ $index }}][unit]" class="form-control form-control-sm med-unit-input"
                                                value="{{ $item['unit'] ?? 'viên' }}" placeholder="viên/gói">
                                        </div>

                                        <!-- Tần suất & hướng dẫn -->
                                        <div class="col-12 col-md-7">
                                            <label class="form-label small fw-semibold">Tần suất & Cách dùng</label>
                                            <input type="text" name="items[{{ $index }}][frequency]" class="form-control form-control-sm"
                                                value="{{ $item['frequency'] ?? '2 lần/ngày (Sáng - Tối)' }}"
                                                placeholder="VD: 2 lần/ngày sau khi ăn no, uống nhiều nước...">
                                        </div>

                                        <!-- Tổng số lượng kê đơn -->
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small fw-semibold">Tổng số lượng</label>
                                            <input type="number" step="1" min="0" name="items[{{ $index }}][total_prescribed_quantity]" class="form-control form-control-sm"
                                                value="{{ $item['total_prescribed_quantity'] ?? 10 }}">
                                        </div>

                                        <!-- Thời điểm ăn -->
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small fw-semibold">Thời điểm ăn</label>
                                            <select name="items[{{ $index }}][meal_timing]" class="form-select form-select-sm">
                                                <option value="sau_an" {{ ($item['meal_timing'] ?? 'sau_an') === 'sau_an' ? 'selected' : '' }}>Sau khi ăn no</option>
                                                <option value="truoc_an" {{ ($item['meal_timing'] ?? '') === 'truoc_an' ? 'selected' : '' }}>Trước khi ăn 30p</option>
                                                <option value="trong_khi_an" {{ ($item['meal_timing'] ?? '') === 'trong_khi_an' ? 'selected' : '' }}>Trong bữa ăn</option>
                                                <option value="khong_phu_thuoc" {{ ($item['meal_timing'] ?? '') === 'khong_phu_thuoc' ? 'selected' : '' }}>Không phụ thuộc</option>
                                            </select>
                                        </div>

                                        <!-- Các cữ uống trong ngày -->
                                        <div class="col-12 pt-1 border-top mt-2">
                                            <div class="d-flex flex-wrap align-items-center gap-3">
                                                <span class="small fw-semibold text-muted">Cữ uống trong ngày:</span>
                                                <div class="form-check form-check-inline mb-0">
                                                    <input class="form-check-input" type="checkbox" name="items[{{ $index }}][times][]" value="sang" id="time_sang_{{ $index }}"
                                                        {{ in_array('sang', $item['times'] ?? ['sang', 'toi']) ? 'checked' : '' }}>
                                                    <label class="form-check-label small" for="time_sang_{{ $index }}">
                                                        <i class="bi bi-brightness-high text-warning me-1"></i>Sáng (07:30)
                                                    </label>
                                                </div>
                                                <div class="form-check form-check-inline mb-0">
                                                    <input class="form-check-input" type="checkbox" name="items[{{ $index }}][times][]" value="trua" id="time_trua_{{ $index }}"
                                                        {{ in_array('trua', $item['times'] ?? []) ? 'checked' : '' }}>
                                                    <label class="form-check-label small" for="time_trua_{{ $index }}">
                                                        <i class="bi bi-sun text-info me-1"></i>Trưa (12:00)
                                                    </label>
                                                </div>
                                                <div class="form-check form-check-inline mb-0">
                                                    <input class="form-check-input" type="checkbox" name="items[{{ $index }}][times][]" value="chieu" id="time_chieu_{{ $index }}"
                                                        {{ in_array('chieu', $item['times'] ?? []) ? 'checked' : '' }}>
                                                    <label class="form-check-label small" for="time_chieu_{{ $index }}">
                                                        <i class="bi bi-sunset text-warning-emphasis me-1"></i>Chiều (17:30)
                                                    </label>
                                                </div>
                                                <div class="form-check form-check-inline mb-0">
                                                    <input class="form-check-input" type="checkbox" name="items[{{ $index }}][times][]" value="toi" id="time_toi_{{ $index }}"
                                                        {{ in_array('toi', $item['times'] ?? ['sang', 'toi']) ? 'checked' : '' }}>
                                                    <label class="form-check-label small" for="time_toi_{{ $index }}">
                                                        <i class="bi bi-moon-stars text-primary me-1"></i>Tối (20:00)
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="text-center pt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary px-4" onclick="addMedicineRow()">
                            <i class="bi bi-plus-circle me-1"></i>Thêm loại thuốc khác vào đơn này
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- CỘT PHẢI (4 CỘT): ẢNH TOA THUỐC, CẢNH BÁO DỊ ỨNG & THẨM ĐỊNH AI -->
        <div class="col-12 col-lg-4">
            <!-- ẢNH ĐƠN THUỐC / TOA BÁC SĨ -->
            <div class="content-card mb-3">
                <div class="content-card-header">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-camera-fill text-primary me-2"></i>ẢNH ĐƠN THUỐC GỐC
                    </span>
                </div>
                <div class="content-card-body p-3">
                    <div class="text-center mb-2">
                        <div id="imagePreviewContainer" class="border rounded p-2 bg-light text-center position-relative mb-2" style="min-height: 160px; display: flex; align-items: center; justify-content: center;">
                            @if ($treatmentCourse->prescription_image_path)
                                <img src="{{ $treatmentCourse->prescription_image_url }}" alt="Toa thuốc" id="prescriptionImagePreview" class="img-fluid rounded" style="max-height: 220px;">
                            @else
                                <img src="" alt="Toa thuốc" id="prescriptionImagePreview" class="img-fluid rounded d-none" style="max-height: 220px;">
                                <div id="imagePlaceholderText" class="text-muted small">
                                    <i class="bi bi-cloud-arrow-up fs-1 text-primary d-block mb-1"></i>
                                    Chụp hoặc tải ảnh toa thuốc của bác sĩ<br>
                                    <span class="text-secondary" style="font-size: 11px;">(Định dạng JPG, PNG, WEBP tối đa 10MB)</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <input type="file" name="prescription_image" id="prescription_image" class="form-control form-control-sm" accept="image/*" onchange="previewPrescriptionImage(this)">
                    <div class="form-text small text-muted mt-1">
                        Lưu ảnh toa giấy giúp bạn dễ dàng đối chiếu khi tái khám hoặc khi mua thêm thuốc tại nhà thuốc.
                    </div>
                </div>
            </div>

            <!-- CẢNH BÁO Y TẾ THÀNH VIÊN THEO THỜI GIAN THỰC -->
            <div class="content-card mb-3 border-danger border-opacity-50" id="patientAlertCard" style="display: none;">
                <div class="content-card-header bg-danger bg-opacity-10 py-2">
                    <span class="fw-bold text-danger d-flex align-items-center gap-2">
                        <i class="bi bi-shield-exclamation fs-5"></i> CẢNH BÁO TIỀN SỬ Y KHOA
                    </span>
                </div>
                <div class="content-card-body p-3">
                    <div id="patientAllergyAlert" class="alert alert-danger py-2 px-3 small mb-2 d-none">
                        <strong><i class="bi bi-exclamation-octagon-fill me-1"></i>TIỀN SỬ DỊ ỨNG:</strong>
                        <div id="patientAllergyText" class="fw-bold fs-6 mt-1"></div>
                        <div class="text-muted mt-1" style="font-size: 11px;">
                            Hệ thống AI sẽ tự động kiểm tra xem đơn thuốc này có chứa hoạt chất gây dị ứng không.
                        </div>
                    </div>

                    <div id="patientDiseaseAlert" class="alert alert-warning py-2 px-3 small mb-0 d-none">
                        <strong><i class="bi bi-clipboard2-pulse-fill me-1"></i>BỆNH NỀN GIA ĐÌNH:</strong>
                        <div id="patientDiseaseText" class="fw-semibold mt-1"></div>
                    </div>
                </div>
            </div>

            <!-- HƯỚNG DẪN KÊ ĐƠN & AN TOÀN Y TẾ -->
            <div class="content-card mb-3">
                <div class="content-card-header bg-light py-2">
                    <span class="fw-bold text-dark small">
                        <i class="bi bi-shield-check text-success me-1"></i>THẨM ĐỊNH AN TOÀN TỰ ĐỘNG
                    </span>
                </div>
                <div class="content-card-body p-3 small text-muted">
                    <ul class="ps-3 mb-0" style="font-size: 12px; line-height: 1.6;">
                        <li><strong>Chống trùng lặp Paracetamol:</strong> AI sẽ tự động rà soát phát hiện nếu bạn cho uống nhiều biệt dược chứa Paracetamol cùng lúc (như Panadol + Decolgen/Hapacol).</li>
                        <li><strong>Cảnh báo Dị ứng:</strong> Cảnh báo nguy cơ nếu bệnh nhân dị ứng NSAID/Aspirin hoặc Penicillin.</li>
                        <li><strong>Lịch uống thông minh:</strong> Tự động đồng bộ giờ uống để nhắc nhở thành viên uống thuốc đúng giờ.</li>
                    </ul>
                </div>
            </div>

            <!-- NÚT LƯU & THAO TÁC -->
            <div class="content-card">
                <div class="content-card-body p-3">
                    <button type="submit" class="btn btn-erp-primary w-100 py-2 fw-bold mb-2">
                        <i class="bi bi-check2-circle me-1"></i>
                        {{ $editing ? 'Lưu cập nhật đợt điều trị' : 'Lưu đợt điều trị & Kiểm tra an toàn' }}
                    </button>
                    <a href="{{ $editing ? route('treatment-courses.show', $treatmentCourse) : route('treatment-courses.index') }}" class="btn btn-sm btn-outline-secondary w-100">
                        Hủy bỏ
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- TEMPLATE CHO HÀNG THUỐC MỚI (DYNAMIC ROW SCRIPT) -->
<template id="medicineRowTemplate">
    <div class="medicine-row card mb-3 border shadow-none" id="med-row-INDEX">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark medicine-number">
                <i class="bi bi-tag-fill text-primary me-1"></i>Thuốc số INDEX_PLUS_ONE
            </span>
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeMedicineRow('INDEX')" title="Xóa thuốc này">
                <i class="bi bi-trash"></i> Xóa
            </button>
        </div>
        <div class="card-body p-3">
            <div class="row g-2">
                <div class="col-12 col-md-7">
                    <label class="form-label small fw-semibold">
                        Tên thuốc & Hàm lượng <span class="text-danger">*</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="items[INDEX][medicine_name]" class="form-control med-name-input" placeholder="Ví dụ: Augmentin 1g, Panadol Extra..." required>
                        <input type="hidden" name="items[INDEX][medicine_id]" class="med-id-input" value="">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Chọn sẵn
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="max-height: 250px; overflow-y: auto; font-size: 12px;">
                            <li class="dropdown-header fw-bold text-primary">Thuốc trong Tủ:</li>
                            @forelse ($cabinetItems as $ci)
                                <li>
                                    <a class="dropdown-item py-1" href="javascript:void(0)" onclick="selectMedicineQuickly('INDEX', '{{ addslashes($ci->medicine?->name ?? $ci->custom_medicine_name) }}', '{{ $ci->medicine_id }}', '{{ $ci->unit }}')">
                                        <i class="bi bi-box-seam me-1 text-success"></i>{{ $ci->medicine?->name ?? $ci->custom_medicine_name }}
                                    </a>
                                </li>
                            @empty
                                <li><span class="dropdown-item text-muted">Tủ thuốc chưa có thuốc</span></li>
                            @endforelse
                            <li><hr class="dropdown-divider"></li>
                            <li class="dropdown-header fw-bold text-primary">Danh mục thuốc:</li>
                            @foreach ($availableMedicines->take(15) as $stdMed)
                                <li>
                                    <a class="dropdown-item py-1" href="javascript:void(0)" onclick="selectMedicineQuickly('INDEX', '{{ addslashes($stdMed->name) }}', '{{ $stdMed->id }}', '{{ $stdMed->default_unit }}')">
                                        <i class="bi bi-capsule me-1 text-primary"></i>{{ $stdMed->name }} ({{ $stdMed->concentration }})
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold">Liều mỗi lần <span class="text-danger">*</span></label>
                    <input type="text" name="items[INDEX][dosage]" class="form-control form-control-sm" value="1 viên" placeholder="1 viên / 2 gói..." required>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold">Đơn vị</label>
                    <input type="text" name="items[INDEX][unit]" class="form-control form-control-sm med-unit-input" value="viên" placeholder="viên/gói">
                </div>

                <div class="col-12 col-md-7">
                    <label class="form-label small fw-semibold">Tần suất & Cách dùng</label>
                    <input type="text" name="items[INDEX][frequency]" class="form-control form-control-sm" value="2 lần/ngày (Sáng - Tối)" placeholder="VD: 2 lần/ngày sau ăn no...">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold">Tổng số lượng</label>
                    <input type="number" step="1" min="0" name="items[INDEX][total_prescribed_quantity]" class="form-control form-control-sm" value="10">
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold">Thời điểm ăn</label>
                    <select name="items[INDEX][meal_timing]" class="form-select form-select-sm">
                        <option value="sau_an" selected>Sau khi ăn no</option>
                        <option value="truoc_an">Trước khi ăn 30p</option>
                        <option value="trong_khi_an">Trong bữa ăn</option>
                        <option value="khong_phu_thuoc">Không phụ thuộc</option>
                    </select>
                </div>

                <div class="col-12 pt-1 border-top mt-2">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="small fw-semibold text-muted">Cữ uống trong ngày:</span>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="checkbox" name="items[INDEX][times][]" value="sang" id="time_sang_INDEX" checked>
                            <label class="form-check-label small" for="time_sang_INDEX">
                                <i class="bi bi-brightness-high text-warning me-1"></i>Sáng (07:30)
                            </label>
                        </div>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="checkbox" name="items[INDEX][times][]" value="trua" id="time_trua_INDEX">
                            <label class="form-check-label small" for="time_trua_INDEX">
                                <i class="bi bi-sun text-info me-1"></i>Trưa (12:00)
                            </label>
                        </div>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="checkbox" name="items[INDEX][times][]" value="chieu" id="time_chieu_INDEX">
                            <label class="form-check-label small" for="time_chieu_INDEX">
                                <i class="bi bi-sunset text-warning-emphasis me-1"></i>Chiều (17:30)
                            </label>
                        </div>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="checkbox" name="items[INDEX][times][]" value="toi" id="time_toi_INDEX" checked>
                            <label class="form-check-label small" for="time_toi_INDEX">
                                <i class="bi bi-moon-stars text-primary me-1"></i>Tối (20:00)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

@push('scripts')
<script>
let rowIndex = {{ count($items) }};

function addMedicineRow() {
    const template = document.getElementById('medicineRowTemplate').innerHTML;
    const newHtml = template
        .replace(/INDEX_PLUS_ONE/g, rowIndex + 1)
        .replace(/INDEX/g, rowIndex);

    const container = document.getElementById('medicinesContainer');
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = newHtml;
    container.appendChild(tempDiv.firstElementChild);

    rowIndex++;
    updateMedicineCount();
}

function removeMedicineRow(idx) {
    const row = document.getElementById('med-row-' + idx);
    const totalRows = document.querySelectorAll('.medicine-row').length;
    if (totalRows <= 1) {
        alert('Đơn thuốc phải có ít nhất 1 loại thuốc!');
        return;
    }
    if (row) {
        row.remove();
        updateMedicineNumbers();
        updateMedicineCount();
    }
}

function updateMedicineNumbers() {
    const rows = document.querySelectorAll('.medicine-row');
    rows.forEach((row, i) => {
        const numLabel = row.querySelector('.medicine-number');
        if (numLabel) {
            numLabel.innerHTML = `<i class="bi bi-tag-fill text-primary me-1"></i>Thuốc số ${i + 1}`;
        }
    });
}

function updateMedicineCount() {
    const count = document.querySelectorAll('.medicine-row').length;
    document.getElementById('medicineCountBadge').textContent = `${count} loại`;
}

function selectMedicineQuickly(idx, name, medId, unit) {
    const row = document.getElementById('med-row-' + idx);
    if (!row) return;
    const nameInput = row.querySelector('.med-name-input');
    const idInput = row.querySelector('.med-id-input');
    const unitInput = row.querySelector('.med-unit-input');

    if (nameInput) nameInput.value = name;
    if (idInput) idInput.value = medId || '';
    if (unitInput && unit) unitInput.value = unit;
}

function previewPrescriptionImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('prescriptionImagePreview');
            const placeholder = document.getElementById('imagePlaceholderText');
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            if (placeholder) placeholder.classList.add('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function handleMemberChange(select) {
    const selectedOption = select.options[select.selectedIndex];
    const alertCard = document.getElementById('patientAlertCard');
    const allergyAlert = document.getElementById('patientAllergyAlert');
    const allergyText = document.getElementById('patientAllergyText');
    const diseaseAlert = document.getElementById('patientDiseaseAlert');
    const diseaseText = document.getElementById('patientDiseaseText');

    if (!selectedOption || !selectedOption.value) {
        alertCard.style.display = 'none';
        return;
    }

    const allergies = selectedOption.getAttribute('data-allergies');
    const diseases = selectedOption.getAttribute('data-diseases');

    let hasAlert = false;

    if (allergies && allergies.trim() !== '') {
        allergyText.textContent = allergies;
        allergyAlert.classList.remove('d-none');
        hasAlert = true;
    } else {
        allergyAlert.classList.add('d-none');
    }

    if (diseases && diseases.trim() !== '') {
        diseaseText.textContent = diseases;
        diseaseAlert.classList.remove('d-none');
        hasAlert = true;
    } else {
        diseaseAlert.classList.add('d-none');
    }

    if (hasAlert) {
        alertCard.style.display = 'block';
    } else {
        alertCard.style.display = 'none';
    }
}

// Kích hoạt khi tải trang nếu đã chọn thành viên sẵn
document.addEventListener('DOMContentLoaded', function() {
    const memberSelect = document.getElementById('family_member_id');
    if (memberSelect && memberSelect.value) {
        handleMemberChange(memberSelect);
    }
    updateMedicineCount();
});
</script>
@endpush
@endsection
