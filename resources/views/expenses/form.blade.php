@extends('layouts.app')

@section('title', $editing ? 'Cập nhật hóa đơn chi phí - ' . $expense->title : 'Ghi nhận chi phí thuốc & Viện phí mới')

@section('content')
<!-- BREADCRUMB & HEADER -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('expenses.index') }}" class="text-decoration-none text-muted small d-inline-flex align-items-center mb-1">
            <i class="bi bi-arrow-left me-1"></i> Quay lại sổ chi phí y tế
        </a>
        <h1 class="h4 fw-bold text-dark mb-0">
            <i class="bi {{ $editing ? 'bi-pencil-square text-primary' : 'bi-plus-circle-fill text-success' }} me-1"></i>
            {{ $editing ? 'Cập nhật hóa đơn: ' . $expense->title : 'Ghi nhận hóa đơn thuốc & Viện phí' }}
        </h1>
        <div class="text-muted small">
            Lưu lại chi tiêu mua thuốc, đơn thuốc ngoại trú hoặc viện phí điều trị để kiểm soát tài chính gia đình chuẩn xác.
        </div>
    </div>
</div>

<form method="POST" 
      action="{{ $editing ? route('expenses.update', $expense) : route('expenses.store') }}" 
      enctype="multipart/form-data" 
      id="expenseForm">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="row g-3">
        <!-- CỘT CHÍNH (8 CỘT) -->
        <div class="col-12 col-lg-8">
            <!-- THÔNG TIN KHOẢN CHI TIÊU -->
            <div class="content-card mb-3">
                <div class="content-card-header bg-light d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-cash-coin text-success me-2"></i>1. Thông tin hóa đơn & Chi phí
                    </span>
                    <span class="badge bg-success-subtle text-success">Bắt buộc</span>
                </div>
                <div class="content-card-body p-3">
                    <div class="row g-3">
                        <!-- Tên khoản chi -->
                        <div class="col-12">
                            <label for="title" class="form-label fw-semibold text-dark mb-1">
                                Nội dung chi tiêu / Tên hóa đơn <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="title" name="title" required maxlength="255"
                                   value="{{ old('title', $expense->title) }}"
                                   class="form-control form-control-lg @error('title') is-invalid @enderror"
                                   placeholder="Ví dụ: Đơn thuốc trị viêm họng BV Nhi Đồng, Mua thuốc cảm cúm & Vitamin C...">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Ghi rõ tên đợt mua, tên nhà thuốc hoặc bệnh viện để tiện tra cứu sau này.</div>
                        </div>

                        <!-- Số tiền chi tiêu -->
                        <div class="col-12 col-md-7">
                            <label for="amount" class="form-label fw-semibold text-dark mb-1">
                                Số tiền chi tiêu (VNĐ) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" id="amount" name="amount" required min="0" step="1000"
                                       value="{{ old('amount', $expense->amount ?? 0) }}"
                                       class="form-control form-control-lg fw-bold text-success @error('amount') is-invalid @enderror"
                                       placeholder="0">
                                <span class="input-group-text fw-bold text-muted bg-light">VNĐ</span>
                                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <span class="text-muted small me-1 align-self-center">Chọn nhanh:</span>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setAmount(50000)">50.000đ</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setAmount(100000)">100.000đ</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setAmount(200000)">200.000đ</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setAmount(500000)">500.000đ</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setAmount(1000000)">1.000.000đ</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="setAmount(2000000)">2.000.000đ</button>
                            </div>
                        </div>

                        <!-- Ngày chi tiêu -->
                        <div class="col-12 col-md-5">
                            <label for="purchase_date" class="form-label fw-semibold text-dark mb-1">
                                Ngày chi tiêu / Mua thuốc <span class="text-danger">*</span>
                            </label>
                            <input type="date" id="purchase_date" name="purchase_date" required
                                   max="{{ date('Y-m-d') }}"
                                   value="{{ old('purchase_date', $expense->purchase_date ? $expense->purchase_date->format('Y-m-d') : date('Y-m-d')) }}"
                                   class="form-control form-control-lg @error('purchase_date') is-invalid @enderror">
                            @error('purchase_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- ĐỐI TƯỢNG THỤ HƯỞNG & ĐỢT ĐIỀU TRỊ LIÊN KẾT -->
            <div class="content-card mb-3">
                <div class="content-card-header bg-light d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-people text-primary me-2"></i>2. Người thụ hưởng & Đợt điều trị
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary">Tùy chọn</span>
                </div>
                <div class="content-card-body p-3">
                    <div class="row g-3">
                        <!-- Chọn thành viên -->
                        <div class="col-12 col-md-6">
                            <label for="family_member_id" class="form-label fw-semibold text-dark mb-1">
                                Người thân thụ hưởng khoản chi
                            </label>
                            <select id="family_member_id" name="family_member_id" class="form-select @error('family_member_id') is-invalid @enderror">
                                <option value="">-- Chi phí chung (Tủ thuốc gia đình) --</option>
                                @foreach ($members as $m)
                                    <option value="{{ $m->id }}" 
                                        @selected(old('family_member_id', $preselectedMemberId ?? $expense->family_member_id) == $m->id)>
                                        {{ $m->name }} ({{ $m->relationship_label ?? 'Người thân' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('family_member_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Để trống nếu là thuốc dự phòng chung cho cả gia đình (Panadol, Bông gòn, Cồn sát khuẩn...).</div>
                        </div>

                        <!-- Chọn đợt điều trị liên kết -->
                        <div class="col-12 col-md-6">
                            <label for="treatment_course_id" class="form-label fw-semibold text-dark mb-1">
                                Đợt điều trị / Đơn thuốc liên kết
                            </label>
                            <select id="treatment_course_id" name="treatment_course_id" class="form-select @error('treatment_course_id') is-invalid @enderror">
                                <option value="">-- Không liên kết đợt điều trị --</option>
                                @foreach ($treatmentCourses as $course)
                                    <option value="{{ $course->id }}" 
                                        data-member-id="{{ $course->family_member_id }}"
                                        @selected(old('treatment_course_id', $preselectedCourseId ?? $expense->treatment_course_id) == $course->id)>
                                        {{ $course->title }} {{ $course->familyMember ? '(' . $course->familyMember->name . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('treatment_course_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Gắn kết để tính tổng chi phí điều trị trọn gói cho từng đợt bệnh.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CHỨNG TỪ & HÓA ĐƠN ĐÍNH KÈM -->
            <div class="content-card mb-3">
                <div class="content-card-header bg-light d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-file-earmark-image text-warning me-2"></i>3. Ảnh chụp Hóa đơn / Đơn thuốc / Biên lai
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary">Tùy chọn</span>
                </div>
                <div class="content-card-body p-3">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-md-7">
                            <label for="receipt_image" class="form-label fw-semibold text-dark mb-1">
                                Tải lên ảnh chụp hóa đơn (JPG, PNG, WEBP)
                            </label>
                            <input type="file" id="receipt_image" name="receipt_image" accept="image/*"
                                   class="form-control @error('receipt_image') is-invalid @enderror"
                                   onchange="previewReceipt(this)">
                            @error('receipt_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Dung lượng tối đa 10MB. Chụp rõ số hóa đơn, danh mục thuốc và tổng tiền.</div>

                            @if ($editing && $expense->receipt_image_path)
                                <div class="mt-2 text-success small d-flex align-items-center gap-1">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Hóa đơn hiện tại đã được lưu trữ an toàn. Chọn file mới nếu muốn thay thế.</span>
                                </div>
                            @endif
                        </div>

                        <!-- KHUNG XEM TRƯỚC HÌNH ẢNH (PREVIEW) -->
                        <div class="col-12 col-md-5 text-center">
                            <div class="border rounded p-2 bg-light d-flex flex-column align-items-center justify-content-center" 
                                 style="min-height: 150px; position: relative;">
                                
                                <img id="receiptPreview" 
                                     src="{{ $editing && $expense->receipt_image_url ? $expense->receipt_image_url : '' }}" 
                                     alt="Xem trước hóa đơn" 
                                     class="img-fluid rounded shadow-sm {{ ($editing && $expense->receipt_image_url) ? '' : 'd-none' }}" 
                                     style="max-height: 160px; object-fit: contain;">

                                <div id="receiptPlaceholder" class="{{ ($editing && $expense->receipt_image_url) ? 'd-none' : '' }} text-muted py-3">
                                    <i class="bi bi-receipt fs-1 text-secondary opacity-50 d-block mb-1"></i>
                                    <span class="small">Chưa có ảnh hóa đơn</span>
                                </div>

                                <button type="button" id="clearPreviewBtn" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 d-none" 
                                        onclick="clearReceiptInput()" title="Bỏ chọn ảnh">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GHI CHÚ BỔ SUNG -->
            <div class="content-card mb-3">
                <div class="content-card-header bg-light">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-chat-left-text text-info me-2"></i>4. Ghi chú & Chi tiết các loại thuốc đã mua
                    </span>
                </div>
                <div class="content-card-body p-3">
                    <textarea id="notes" name="notes" rows="3" maxlength="1000"
                              class="form-control @error('notes') is-invalid @enderror"
                              placeholder="Ghi chú thêm: Danh sách chi tiết thuốc, bác sĩ phụ trách, nhà thuốc Long Châu / An Khang, phương thức thanh toán...">{{ old('notes', $expense->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <!-- CỘT PHỤ BÊN PHẢI (4 CỘT) -->
        <div class="col-12 col-lg-4">
            <!-- TỔNG KẾT & LƯU TRỮ -->
            <div class="content-card mb-3">
                <div class="content-card-header bg-light">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-shield-check text-primary me-2"></i>Xác nhận & Lưu trữ
                    </span>
                </div>
                <div class="content-card-body p-3">
                    <div class="p-3 bg-light rounded mb-3 text-center border">
                        <div class="text-muted small mb-1">Tổng tiền ghi nhận</div>
                        <div class="fs-3 fw-bold text-success" id="summaryAmount">
                            {{ number_format((float)old('amount', $expense->amount ?? 0), 0, ',', '.') }} đ
                        </div>
                        <div class="small text-muted" id="summaryMember">
                            {{ $expense->familyMember ? $expense->familyMember->name : 'Chi phí chung' }}
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-erp-primary btn-lg shadow-sm" id="btnSubmit">
                            <i class="bi bi-save me-1"></i>
                            <span>{{ $editing ? 'Lưu cập nhật hóa đơn' : 'Ghi nhận chi phí ngay' }}</span>
                        </button>
                        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">
                            Hủy bỏ
                        </a>
                    </div>
                </div>
            </div>

            <!-- MẸO HỮU ÍCH -->
            <div class="content-card">
                <div class="content-card-header bg-light">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-lightbulb text-warning me-2"></i>Mẹo quản lý viện phí
                    </span>
                </div>
                <div class="content-card-body p-3">
                    <ul class="list-unstyled mb-0 small text-muted d-flex flex-column gap-2">
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-check-circle text-success mt-1"></i>
                            <span><strong>Chụp ảnh hóa đơn đỏ/biên lai:</strong> Rất hữu ích khi cần tra cứu lại liều dùng hoặc làm thủ tục bồi thường bảo hiểm y tế tư nhân.</span>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-check-circle text-success mt-1"></i>
                            <span><strong>Gắn với Đợt điều trị:</strong> Giúp bạn tính toán chính xác tổng chi phí cho một đợt chữa trị cụ thể từ đầu đến cuối.</span>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-check-circle text-success mt-1"></i>
                            <span><strong>Xuất file Excel:</strong> Cuối năm bạn có thể xuất toàn bộ sổ chi tiêu ra file CSV để tổng kết ngân sách gia đình.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    function setAmount(val) {
        const input = document.getElementById('amount');
        input.value = val;
        updateSummary();
    }

    function updateSummary() {
        const amount = document.getElementById('amount').value;
        const formatted = new Intl.NumberFormat('vi-VN').format(amount || 0);
        document.getElementById('summaryAmount').innerText = formatted + ' đ';

        const memberSelect = document.getElementById('family_member_id');
        const selectedText = memberSelect.options[memberSelect.selectedIndex]?.text || 'Chi phí chung';
        document.getElementById('summaryMember').innerText = selectedText.replace(/--/g, '').trim();
    }

    document.getElementById('amount').addEventListener('input', updateSummary);
    document.getElementById('family_member_id').addEventListener('change', updateSummary);

    // Auto-select member when treatment course changes if member not selected
    document.getElementById('treatment_course_id').addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const memberId = selected.getAttribute('data-member-id');
        if (memberId) {
            const memberSelect = document.getElementById('family_member_id');
            memberSelect.value = memberId;
            updateSummary();
        }
    });

    // Preview receipt image
    function previewReceipt(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('receiptPreview');
                img.src = e.target.result;
                img.classList.remove('d-none');
                document.getElementById('receiptPlaceholder').classList.add('d-none');
                document.getElementById('clearPreviewBtn').classList.remove('d-none');
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function clearReceiptInput() {
        const input = document.getElementById('receipt_image');
        input.value = '';
        const img = document.getElementById('receiptPreview');
        img.src = '';
        img.classList.add('d-none');
        document.getElementById('receiptPlaceholder').classList.remove('d-none');
        document.getElementById('clearPreviewBtn').classList.add('d-none');
    }

    // Initial sync
    updateSummary();
</script>
@endpush
