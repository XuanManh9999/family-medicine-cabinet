@extends('layouts.app')

@section('title', 'Trợ lý Y tế AI Thông minh - MedCabinet')

@section('content')
<!-- HEADER -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <span class="badge bg-primary px-2 py-1 fs-6"><i class="bi bi-robot me-1"></i>AI</span>
            Trợ lý Y tế & Dược sĩ Gia đình AI
        </h5>
        <div class="text-muted small">
            Được trang bị công nghệ Gemini Vision OCR đọc ảnh đơn thuốc, kiểm tra trùng hoạt chất & tương tác thuốc, cùng Trợ lý tư vấn dược lâm sàng an toàn.
        </div>
    </div>
    <div>
        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle p-2">
            <i class="bi bi-shield-check me-1"></i>Hỗ trợ Rule-engine nội bộ & Trí tuệ nhân tạo Gemini
        </span>
    </div>
</div>

<!-- NAVIGATION TABS -->
<div class="card mb-3 border shadow-sm">
    <div class="card-header bg-white p-2 d-flex justify-content-between align-items-center">
        <ul class="nav nav-pills card-header-pills small" id="aiTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold d-flex align-items-center gap-2" id="ocr-tab" data-bs-toggle="tab" data-bs-target="#ocr-pane" type="button" role="tab">
                    <i class="bi bi-camera-fill"></i>Quét ảnh Đơn thuốc / Vỏ hộp (Vision OCR)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold d-flex align-items-center gap-2" id="chat-tab" data-bs-toggle="tab" data-bs-target="#chat-pane" type="button" role="tab">
                    <i class="bi bi-chat-dots-fill"></i>Trò chuyện Tư vấn Y tế Gia đình AI
                </button>
            </li>
        </ul>
        <div class="d-none d-md-block text-muted small pe-2">
            <i class="bi bi-info-circle me-1"></i>Thông tin AI mang tính chất tham khảo
        </div>
    </div>
</div>

<div class="tab-content" id="aiTabContent">
    <!-- TAB 1: VISION OCR SCANNER -->
    <div class="tab-pane fade show active" id="ocr-pane" role="tabpanel">
        <div class="row g-3">
            <!-- LEFT COLUMN: UPLOAD & CONTROLS -->
            <div class="col-lg-5">
                <div class="content-card">
                    <div class="content-card-header bg-light">
                        <span class="fw-bold text-dark"><i class="bi bi-upload text-primary me-2"></i>Tải lên ảnh toa thuốc / vỏ thuốc</span>
                    </div>
                    <div class="content-card-body">
                        <form id="ocrUploadForm" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">
                                    Người thụ hưởng (đối chiếu dị ứng & bệnh nền):
                                </label>
                                <select name="family_member_id" id="ocrMemberSelect" class="form-select form-select-sm">
                                    <option value="">-- Kiểm tra chung cho gia đình --</option>
                                    @foreach ($members as $mem)
                                        <option value="{{ $mem->id }}">
                                            {{ $mem->name }} ({{ $mem->relationship_label }}{{ $mem->allergies ? ' - Dị ứng: ' . $mem->allergies : '' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">
                                    Chọn ảnh đơn thuốc hoặc vỏ bao bì thuốc <span class="text-danger">*</span>:
                                </label>
                                <div class="border border-2 border-dashed rounded p-3 text-center bg-light position-relative" id="dropArea" style="cursor: pointer;">
                                    <i class="bi bi-cloud-arrow-up text-primary fs-1 d-block mb-1"></i>
                                    <div class="fw-semibold small text-dark mb-1">Nhấp hoặc kéo thả ảnh vào đây</div>
                                    <div class="text-muted" style="font-size: 11px;">Hỗ trợ định dạng JPG, PNG, WEBP tối đa 10MB</div>
                                    <input type="file" name="prescription_image" id="prescriptionImageInput" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" accept="image/*" required style="cursor: pointer;">
                                </div>
                            </div>

                            <!-- PREVIEW IMAGE -->
                            <div id="imagePreviewContainer" class="mb-3 d-none text-center">
                                <div class="fw-semibold small text-secondary mb-1">Ảnh đã chọn:</div>
                                <img id="imagePreview" src="#" alt="Xem trước đơn thuốc" class="img-fluid rounded border shadow-sm" style="max-height: 240px; object-fit: contain;">
                            </div>

                            <button type="submit" id="btnScanOcr" class="btn btn-erp-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-stars"></i>
                                <span id="scanBtnText">Quét & Bóc tách thông tin bằng AI</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- TIPS CARD -->
                <div class="card border bg-light shadow-sm">
                    <div class="card-body p-3 small text-muted">
                        <div class="fw-bold text-dark mb-2"><i class="bi bi-lightbulb text-warning me-1"></i>Mẹo chụp ảnh để AI đọc chính xác nhất:</div>
                        <ul class="ps-3 mb-0" style="line-height: 1.6;">
                            <li>Chụp nơi đủ ánh sáng, tránh sấp bóng hoặc bóng lóa đèn.</li>
                            <li>Để vuông góc thẳng với mặt giấy đơn thuốc hoặc tên vỏ hộp.</li>
                            <li>Ảnh chụp rõ nét tên thuốc, nồng độ/hàm lượng và cách dùng của bác sĩ.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: AI SCAN RESULTS -->
            <div class="col-lg-7">
                <!-- EMPTY PLACEHOLDER -->
                <div id="ocrEmptyState" class="content-card text-center py-5">
                    <div class="content-card-body">
                        <i class="bi bi-card-image text-muted fs-1 d-block mb-2 opacity-50"></i>
                        <h6 class="fw-bold text-secondary">Chưa có kết quả bóc tách</h6>
                        <p class="text-muted small mb-0" style="max-width: 400px; margin: 0 auto;">
                            Vui lòng chọn hoặc chụp ảnh đơn thuốc ở khung bên trái và bấm <strong>"Quét & Bóc tách thông tin bằng AI"</strong> để xem kết quả.
                        </p>
                    </div>
                </div>

                <!-- LOADING SPINNER -->
                <div id="ocrLoadingState" class="content-card text-center py-5 d-none">
                    <div class="content-card-body">
                        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                            <span class="visually-hidden">Đang phân tích...</span>
                        </div>
                        <h6 class="fw-bold text-primary">Gemini Vision AI đang đọc và xử lý đơn thuốc...</h6>
                        <p class="text-muted small mb-0">Hệ thống đang trích xuất tên thuốc, liều dùng và kiểm tra nguy cơ tương tác dược lý.</p>
                    </div>
                </div>

                <!-- RESULT CONTAINER -->
                <div id="ocrResultContainer" class="d-none">
                    <!-- SUMMARY BANNER -->
                    <div class="card border-primary mb-3 shadow-sm">
                        <div class="card-body p-3 bg-primary-subtle border-start border-4 border-primary">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="badge bg-primary text-white mb-1"><i class="bi bi-check-circle me-1"></i>Đã quét thành công</div>
                                    <h6 class="fw-bold text-dark mb-1" id="resDiagnosis">Chẩn đoán: --</h6>
                                    <div class="small text-muted" id="resDoctor">Bác sĩ / Nơi khám: --</div>
                                </div>
                                <a href="{{ route('treatment-courses.create') }}" class="btn btn-sm btn-erp-primary text-nowrap">
                                    <i class="bi bi-journal-plus me-1"></i>Tạo đợt điều trị mới
                                </a>
                            </div>
                            <div class="small text-dark mt-2 border-top pt-2" id="resSummary">
                                Tóm tắt: --
                            </div>
                        </div>
                    </div>

                    <!-- SAFETY CHECK ALERT -->
                    <div id="safetyAlertBox" class="alert mb-3 py-2 px-3 small d-none" role="alert">
                        <!-- Populated by JS -->
                    </div>

                    <!-- MEDICINES EXTRACTED TABLE -->
                    <div class="content-card mb-3">
                        <div class="content-card-header bg-light d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark"><i class="bi bi-capsule text-primary me-2"></i>Danh sách thuốc bóc tách được</span>
                            <span class="badge bg-secondary" id="resMedsCount">0 thuốc</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-erp mb-0">
                                <thead>
                                    <tr>
                                        <th>Tên thuốc</th>
                                        <th>Hoạt chất / Hàm lượng</th>
                                        <th>Liều dùng</th>
                                        <th>Tần suất & Cách dùng</th>
                                        <th>Hạn dùng (EXP)</th>
                                    </tr>
                                </thead>
                                <tbody id="resMedsTableBody">
                                    <!-- Populated by JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PRECAUTIONS -->
                    <div class="content-card mb-3">
                        <div class="content-card-header bg-warning-subtle text-warning-emphasis">
                            <span class="fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Lưu ý & Dặn dò y tế đặc biệt</span>
                        </div>
                        <div class="content-card-body p-3">
                            <ul class="mb-0 ps-3 small text-dark" id="resPrecautionsList">
                                <!-- Populated by JS -->
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: MEDICAL ADVISORY CHAT -->
    <div class="tab-pane fade" id="chat-pane" role="tabpanel">
        <div class="row g-3">
            <!-- CHAT MAIN AREA -->
            <div class="col-lg-8">
                <div class="content-card d-flex flex-column" style="height: 600px;">
                    <div class="content-card-header bg-light d-flex justify-content-between align-items-center py-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                <i class="bi bi-robot fs-6"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-6" style="line-height: 1.2;">Dược sĩ & Bác sĩ AI Gia đình</div>
                                <div class="text-success" style="font-size: 11px;"><i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i>Trực tuyến 24/7</div>
                            </div>
                        </div>
                        <div>
                            <select id="chatTargetMember" class="form-select form-select-sm" style="font-size: 12px; width: auto;">
                                <option value="">-- Tư vấn chung cho cả nhà --</option>
                                @foreach ($members as $mem)
                                    <option value="{{ $mem->id }}">
                                        Hỏi riêng cho: {{ $mem->name }} ({{ $mem->relationship_label }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- CHAT MESSAGES CONTAINER -->
                    <div class="content-card-body flex-grow-1 p-3 overflow-y-auto bg-light" id="chatMessages" style="max-height: 480px;">
                        <!-- BOT WELCOME MESSAGE -->
                        <div class="d-flex gap-2 mb-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                                <i class="bi bi-robot"></i>
                            </div>
                            <div class="bg-white border rounded p-3 shadow-sm" style="max-width: 85%;">
                                <div class="fw-bold text-primary small mb-1">Dược sĩ AI MedCabinet:</div>
                                <div class="text-dark small" style="line-height: 1.6;">
                                    Xin chào gia đình <strong>{{ $family->name }}</strong>! Tôi là Trợ lý Dược sĩ Y tế AI của bạn. 
                                    Tôi có thể giải đáp các thắc mắc về cách dùng thuốc, thời điểm uống thuốc (trước hay sau ăn), xử lý khi quên liều, cách bảo quản thuốc trong tủ hoặc kiểm tra tương tác giữa các loại thuốc.
                                </div>
                                <div class="mt-2 text-muted" style="font-size: 11px;">
                                    <i class="bi bi-shield-lock me-1"></i>Bạn có thể bấm vào các câu hỏi gợi ý bên phải hoặc nhập câu hỏi trực tiếp bên dưới!
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CHAT INPUT BAR -->
                    <div class="p-2 border-top bg-white">
                        <form id="chatForm" class="d-flex gap-2">
                            @csrf
                            <input type="text" id="chatInput" class="form-control form-control-sm" 
                                   placeholder="Nhập câu hỏi y tế (VD: Quên uống thuốc huyết áp 1 cữ thì phải làm sao?)..." 
                                   autocomplete="off" required>
                            <button type="submit" id="btnSendChat" class="btn btn-erp-primary btn-sm px-3 d-flex align-items-center gap-1">
                                <i class="bi bi-send-fill"></i>
                                <span class="d-none d-sm-inline">Gửi</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- CHAT RIGHT SIDEBAR: QUICK QUESTIONS & HEALTH CONTEXT -->
            <div class="col-lg-4">
                <!-- SUGGESTED QUESTIONS -->
                <div class="content-card mb-3">
                    <div class="content-card-header bg-light">
                        <span class="fw-bold text-dark small"><i class="bi bi-question-circle-fill text-primary me-2"></i>Câu hỏi thường gặp</span>
                    </div>
                    <div class="content-card-body p-2">
                        <div class="list-group list-group-flush small">
                            @foreach ($suggestedQuestions as $sq)
                                <button type="button" class="list-group-item list-group-item-action border-0 rounded py-2 px-2 text-start mb-1 suggested-q-btn" onclick="sendQuickQuestion('{{ addslashes($sq) }}')">
                                    <i class="bi bi-chat-left-text text-muted me-2"></i>{{ $sq }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- FAMILY HEALTH CONTEXT PREVIEW -->
                <div class="content-card">
                    <div class="content-card-header bg-light">
                        <span class="fw-bold text-dark small"><i class="bi bi-heart-pulse-fill text-danger me-2"></i>Hồ sơ bệnh nền & Dị ứng</span>
                    </div>
                    <div class="content-card-body p-2 small">
                        @php
                            $membersWithAllergies = $members->filter(fn ($m) => !empty($m->allergies) || !empty($m->underlying_diseases));
                        @endphp
                        @if ($membersWithAllergies->isEmpty())
                            <div class="text-muted text-center py-3">Chưa ghi nhận bệnh nền hoặc dị ứng đặc biệt trong gia đình.</div>
                        @else
                            <ul class="list-group list-group-flush mb-0">
                                @foreach ($membersWithAllergies as $mem)
                                    <li class="list-group-item px-2 py-2">
                                        <div class="fw-semibold text-dark">{{ $mem->name }} ({{ $mem->relationship_label }}):</div>
                                        @if ($mem->allergies)
                                            <div class="text-danger" style="font-size: 11px;"><i class="bi bi-exclamation-triangle me-1"></i>Dị ứng: {{ $mem->allergies }}</div>
                                        @endif
                                        @if ($mem->underlying_diseases)
                                            <div class="text-warning-emphasis" style="font-size: 11px;"><i class="bi bi-activity me-1"></i>Bệnh nền: {{ $mem->underlying_diseases }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // 1. VISION OCR HANDLERS
    const fileInput = document.getElementById('prescriptionImageInput');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const imagePreview = document.getElementById('imagePreview');

    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreviewContainer.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        }
    });

    document.getElementById('ocrUploadForm').addEventListener('submit', function(e) {
        e.preventDefault();

        if (!fileInput.files || fileInput.files.length === 0) {
            alert('Vui lòng chọn tệp ảnh đơn thuốc.');
            return;
        }

        const formData = new FormData(this);
        const btnScan = document.getElementById('btnScanOcr');
        const scanText = document.getElementById('scanBtnText');

        // State changes
        btnScan.disabled = true;
        scanText.innerText = 'Đang phân tích ảnh AI...';
        document.getElementById('ocrEmptyState').classList.add('d-none');
        document.getElementById('ocrResultContainer').classList.add('d-none');
        document.getElementById('ocrLoadingState').classList.remove('d-none');

        fetch("{{ route('ai-assistant.scan') }}", {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('ocrLoadingState').classList.add('d-none');
            btnScan.disabled = false;
            scanText.innerText = 'Quét & Bóc tách thông tin bằng AI';

            if (data.success && data.ocr_data) {
                renderOcrResults(data.ocr_data, data.safety_report);
            } else {
                alert('Không thể phân tích ảnh: ' + (data.message || 'Lỗi xử lý.'));
                document.getElementById('ocrEmptyState').classList.remove('d-none');
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('ocrLoadingState').classList.add('d-none');
            btnScan.disabled = false;
            scanText.innerText = 'Quét & Bóc tách thông tin bằng AI';
            alert('Có lỗi xảy ra trong quá trình gọi AI phân tích.');
            document.getElementById('ocrEmptyState').classList.remove('d-none');
        });
    });

    function renderOcrResults(ocr, safety) {
        document.getElementById('resDiagnosis').innerText = 'Chẩn đoán: ' + (ocr.diagnosis || 'Không xác định rõ');
        document.getElementById('resDoctor').innerText = 'Bác sĩ / Nơi khám: ' + (ocr.hospital_doctor || 'Đơn thuốc bệnh viện');
        document.getElementById('resSummary').innerText = 'Tóm tắt: ' + (ocr.plain_language_summary || 'Đơn thuốc điều trị ngoại trú.');

        // Safety report banner
        const safetyBox = document.getElementById('safetyAlertBox');
        if (safety && safety.has_conflict) {
            safetyBox.className = 'alert alert-danger mb-3 py-2 px-3 small';
            safetyBox.innerHTML = `<strong><i class="bi bi-shield-fill-x me-1"></i>CẢNH BÁO AN TOÀN Y TẾ:</strong> ${safety.summary}
                <div class="mt-1">${safety.recommendations || ''}</div>`;
            safetyBox.classList.remove('d-none');
        } else {
            safetyBox.className = 'alert alert-success mb-3 py-2 px-3 small';
            safetyBox.innerHTML = '<strong><i class="bi bi-shield-check me-1"></i>AN TOÀN:</strong> Không phát hiện trùng hoạt chất Paracetamol hay chống chỉ định dị ứng thuốc.';
            safetyBox.classList.remove('d-none');
        }

        // Medicines table
        const tbody = document.getElementById('resMedsTableBody');
        tbody.innerHTML = '';
        const meds = ocr.medicines || [];
        document.getElementById('resMedsCount').innerText = meds.length + ' thuốc';

        if (meds.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Không tìm thấy danh sách thuốc rõ ràng trong ảnh.</td></tr>';
        } else {
            meds.forEach(m => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="fw-bold text-dark">${m.name || '--'}</td>
                    <td>
                        <div class="text-primary fw-semibold">${m.active_ingredient || '--'}</div>
                        <div class="text-muted small">${m.concentration || ''}</div>
                    </td>
                    <td>${m.dosage || '1 viên'}</td>
                    <td>
                        <div>${m.frequency || '--'}</div>
                        <div class="text-muted small">${m.instructions || ''}</div>
                    </td>
                    <td><span class="badge bg-light text-dark border font-monospace">${m.expiry_date || 'Theo vỏ thuốc'}</span></td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Precautions
        const precList = document.getElementById('resPrecautionsList');
        precList.innerHTML = '';
        const precs = ocr.precautions || [];
        if (precs.length === 0) {
            precList.innerHTML = '<li>Uống thuốc đúng liều và tuân thủ dặn dò của bác sĩ kê đơn.</li>';
        } else {
            precs.forEach(p => {
                const li = document.createElement('li');
                li.innerText = p;
                precList.appendChild(li);
            });
        }

        document.getElementById('ocrResultContainer').classList.remove('d-none');
    }

    // 2. CHAT ASSISTANT HANDLERS
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const chatMessages = document.getElementById('chatMessages');
    const btnSendChat = document.getElementById('btnSendChat');

    function sendQuickQuestion(q) {
        // Switch to chat tab if not active
        const chatTab = new bootstrap.Tab(document.getElementById('chat-tab'));
        chatTab.show();
        chatInput.value = q;
        submitChat();
    }

    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitChat();
    });

    function submitChat() {
        const question = chatInput.value.trim();
        if (!question) return;

        const memberId = document.getElementById('chatTargetMember').value;

        // Append User Message to UI
        appendUserMessage(question);
        chatInput.value = '';
        btnSendChat.disabled = true;

        // Append Bot Typing Indicator
        const typingId = 'typing-' + Date.now();
        appendTypingIndicator(typingId);
        scrollChatToBottom();

        fetch("{{ route('ai-assistant.chat') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                question: question,
                family_member_id: memberId
            })
        })
        .then(res => res.json())
        .then(data => {
            btnSendChat.disabled = false;
            removeTypingIndicator(typingId);

            if (data.success && data.response) {
                appendBotResponse(data.response, data.answered_at);
            } else {
                appendBotErrorMessage('Rất tiếc, đã có sự cố kết nối với Trợ lý AI. Vui lòng thử lại sau.');
            }
            scrollChatToBottom();
        })
        .catch(err => {
            btnSendChat.disabled = false;
            removeTypingIndicator(typingId);
            appendBotErrorMessage('Không thể gửi tin nhắn. Vui lòng kiểm tra kết nối mạng.');
            scrollChatToBottom();
        });
    }

    function appendUserMessage(text) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'd-flex gap-2 mb-3 justify-content-end';
        msgDiv.innerHTML = `
            <div class="bg-primary text-white rounded p-3 shadow-sm" style="max-width: 80%;">
                <div class="small fw-semibold mb-1 text-white-50">Bạn:</div>
                <div class="small" style="line-height: 1.5;">${escapeHtml(text)}</div>
            </div>
            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                <i class="bi bi-person-fill"></i>
            </div>
        `;
        chatMessages.appendChild(msgDiv);
    }

    function appendTypingIndicator(id) {
        const typingDiv = document.createElement('div');
        typingDiv.id = id;
        typingDiv.className = 'd-flex gap-2 mb-3';
        typingDiv.innerHTML = `
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                <i class="bi bi-robot"></i>
            </div>
            <div class="bg-white border rounded p-2 shadow-sm text-muted small d-flex align-items-center gap-2">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                <span>Dược sĩ AI đang suy nghĩ câu trả lời...</span>
            </div>
        `;
        chatMessages.appendChild(typingDiv);
    }

    function removeTypingIndicator(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function appendBotResponse(resp, time) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'd-flex gap-2 mb-3';

        let cabinetMatchHtml = '';
        if (resp.cabinet_match && resp.cabinet_match.trim() !== '') {
            cabinetMatchHtml = `
                <div class="mt-2 p-2 bg-info-subtle text-info-emphasis rounded border border-info-subtle small d-flex align-items-center gap-2">
                    <i class="bi bi-box-seam-fill text-info fs-6"></i>
                    <div>
                        <strong class="d-block" style="font-size: 11.5px;">Đối chiếu Tủ thuốc Nhà bạn:</strong>
                        <span>${escapeHtml(resp.cabinet_match)}</span>
                    </div>
                </div>
            `;
        }

        let keyPointsHtml = '';
        if (resp.key_points && resp.key_points.length > 0) {
            keyPointsHtml = '<div class="mt-2 p-2 bg-light rounded border"><div class="fw-bold text-secondary small mb-1"><i class="bi bi-bookmark-star-fill text-warning me-1"></i>Lưu ý cốt lõi:</div><ul class="mb-0 ps-3 small">';
            resp.key_points.forEach(kp => {
                keyPointsHtml += `<li>${escapeHtml(kp)}</li>`;
            });
            keyPointsHtml += '</ul></div>';
        }

        let actionsHtml = '';
        if (resp.suggested_actions && resp.suggested_actions.length > 0) {
            actionsHtml = '<div class="mt-2 p-2 bg-success-subtle text-success-emphasis rounded border border-success-subtle"><div class="fw-bold small mb-1"><i class="bi bi-check2-circle text-success me-1"></i>Hành động khuyến nghị:</div><ul class="mb-0 ps-3 small">';
            resp.suggested_actions.forEach(act => {
                actionsHtml += `<li>${escapeHtml(act)}</li>`;
            });
            actionsHtml += '</ul></div>';
        }

        let warningHtml = '';
        if (resp.safety_warning) {
            warningHtml = `
                <div class="mt-2 p-2 bg-danger-subtle text-danger rounded border border-danger-subtle small">
                    <i class="bi bi-shield-exclamation me-1"></i><strong>Cảnh báo an toàn:</strong> ${escapeHtml(resp.safety_warning)}
                </div>
            `;
        }

        msgDiv.innerHTML = `
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                <i class="bi bi-robot"></i>
            </div>
            <div class="bg-white border rounded p-3 shadow-sm" style="max-width: 85%;">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold text-primary small">Dược sĩ AI MedCabinet:</span>
                    <span class="text-muted" style="font-size: 10px;">${time || ''}</span>
                </div>
                <div class="text-dark small" style="line-height: 1.6; white-space: pre-line;">${escapeHtml(resp.answer || '')}</div>
                ${cabinetMatchHtml}
                ${keyPointsHtml}
                ${actionsHtml}
                ${warningHtml}
            </div>
        `;
        chatMessages.appendChild(msgDiv);
    }

    function appendBotErrorMessage(msg) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'd-flex gap-2 mb-3';
        msgDiv.innerHTML = `
            <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="bg-danger-subtle text-danger border border-danger-subtle rounded p-2 small">
                ${escapeHtml(msg)}
            </div>
        `;
        chatMessages.appendChild(msgDiv);
    }

    function scrollChatToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.innerText = text;
        return div.innerHTML;
    }
</script>
@endpush
@endsection
