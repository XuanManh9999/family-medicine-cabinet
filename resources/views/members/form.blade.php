@extends('layouts.app')

@section('title', $editing ? 'Cập nhật hồ sơ sức khỏe - ' . $member->name : 'Thêm thành viên mới - Tủ thuốc gia đình')

@section('content')
<!-- Header và Điều hướng -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('members.index') }}" class="text-decoration-none text-muted small d-inline-flex align-items-center mb-1">
            <i class="bi bi-arrow-left me-1"></i> Quay lại danh sách thành viên
        </a>
        <h1 class="h4 fw-bold text-dark mb-0">
            <i class="bi {{ $editing ? 'bi-person-gear text-primary' : 'bi-person-plus text-success' }} me-1"></i>
            {{ $editing ? 'Cập nhật hồ sơ sức khỏe: ' . $member->name : 'Khai báo thành viên & Hồ sơ sức khỏe mới' }}
        </h1>
        <div class="text-muted small">
            Lập hồ sơ y tế, theo dõi tiền sử dị ứng thuốc và bệnh lý mạn tính để hệ thống tự động kiểm tra an toàn khi dùng thuốc.
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- CỘT TRÁI: FORM NHẬP HỒ SƠ Y TẾ (8 CỘT) -->
    <div class="col-12 col-lg-8">
        <form method="POST" action="{{ $editing ? route('members.update', $member) : route('members.store') }}" enctype="multipart/form-data" id="memberForm">
            @csrf
            @if ($editing) @method('PUT') @endif

            <!-- 1. THÔNG TIN CÁ NHÂN & QUAN HỆ -->
            <div class="content-card mb-3">
                <div class="content-card-header bg-light d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-person-vcard text-primary me-2"></i>1. Thông tin cá nhân & Nhân khẩu
                    </span>
                    <span class="badge bg-primary-subtle text-primary">Bắt buộc</span>
                </div>
                <div class="content-card-body p-3">
                    <div class="row g-3">
                        <div class="col-12 col-md-7">
                            <label for="name" class="form-label fw-semibold text-dark mb-1">
                                Họ và tên người thân <span class="text-danger">*</span>
                            </label>
                            <input id="name" name="name" required maxlength="255"
                                   value="{{ old('name', $member->name) }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   placeholder="Ví dụ: Nguyễn Văn Thịnh">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-5">
                            <label for="relationship" class="form-label fw-semibold text-dark mb-1">
                                Mối quan hệ trong nhà <span class="text-danger">*</span>
                            </label>
                            <select id="relationship" name="relationship" required class="form-select @error('relationship') is-invalid @enderror">
                                <option value="">-- Chọn mối quan hệ --</option>
                                <option value="ong_ba" @selected(old('relationship', $member->relationship) === 'ong_ba')>Ông / Bà</option>
                                <option value="bo_me" @selected(old('relationship', $member->relationship) === 'bo_me')>Bố / Mẹ</option>
                                <option value="vo_chong" @selected(old('relationship', $member->relationship) === 'vo_chong')>Vợ / Chồng</option>
                                <option value="con_cai" @selected(old('relationship', $member->relationship) === 'con_cai')>Con cái</option>
                                <option value="ban_than" @selected(old('relationship', $member->relationship) === 'ban_than')>Bản thân (Tôi)</option>
                                <option value="khac" @selected(old('relationship', $member->relationship) === 'khac')>Người thân khác</option>
                            </select>
                            @error('relationship')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="dob" class="form-label fw-semibold text-dark mb-1">Ngày tháng năm sinh</label>
                            <input id="dob" name="dob" type="date" max="{{ today()->format('Y-m-d') }}"
                                   value="{{ old('dob', $member->dob?->format('Y-m-d')) }}"
                                   class="form-control @error('dob') is-invalid @enderror">
                            @error('dob')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div id="ageNotice" class="small text-muted mt-1"></div>
                        </div>

                        <div class="col-6 col-md-4">
                            <label for="gender" class="form-label fw-semibold text-dark mb-1">Giới tính <span class="text-danger">*</span></label>
                            <select id="gender" name="gender" required class="form-select @error('gender') is-invalid @enderror">
                                <option value="nam" @selected(old('gender', $member->gender) === 'nam')>Nam</option>
                                <option value="nu" @selected(old('gender', $member->gender) === 'nu')>Nữ</option>
                                <option value="khac" @selected(old('gender', $member->gender) === 'khac')>Khác</option>
                            </select>
                            @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-6 col-md-4">
                            <label for="blood_type" class="form-label fw-semibold text-dark mb-1">Nhóm máu</label>
                            <select id="blood_type" name="blood_type" class="form-select @error('blood_type') is-invalid @enderror">
                                <option value="">-- Chưa rõ --</option>
                                @foreach (['O', 'A', 'B', 'AB', 'O+', 'A+', 'B+', 'AB+', 'O-', 'A-', 'B-', 'AB-'] as $bt)
                                    <option value="{{ $bt }}" @selected(old('blood_type', $member->blood_type) === $bt)>Nhóm máu {{ $bt }}</option>
                                @endforeach
                            </select>
                            @error('blood_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="user_id" class="form-label fw-semibold text-dark mb-1">
                                Liên kết tài khoản App <span class="text-muted fw-normal">(Không bắt buộc)</span>
                            </label>
                            <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror">
                                <option value="">Không liên kết (Người già, trẻ em không dùng smartphone)</option>
                                @foreach ($availableUsers as $u)
                                    <option value="{{ $u->id }}" @selected((string)old('user_id', $member->user_id) === (string)$u->id)>
                                        {{ $u->name }} ({{ $u->email }})
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="avatar_file" class="form-label fw-semibold text-dark mb-1">Ảnh đại diện</label>
                            <input id="avatar_file" name="avatar_file" type="file" accept="image/*" class="form-control @error('avatar_file') is-invalid @enderror">
                            @error('avatar_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. TIỀN SỬ DỊ ỨNG THUỐC (AN TOÀN Y TẾ TỐI QUAN TRỌNG) -->
            <div class="content-card mb-3 border-danger border-opacity-50">
                <div class="content-card-header bg-danger bg-opacity-10 d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-danger">
                        <i class="bi bi-shield-slash-fill me-2"></i>2. Tiền sử Dị ứng thuốc (Quan trọng nhất)
                    </span>
                    <span class="badge bg-danger text-white">An toàn y tế</span>
                </div>
                <div class="content-card-body p-3">
                    <label for="allergies" class="form-label fw-semibold text-dark mb-1">
                        Chi tiết các hoạt chất / loại thuốc từng bị dị ứng:
                    </label>
                    <textarea id="allergies" name="allergies" rows="3" maxlength="3000"
                              class="form-control @error('allergies') is-invalid @enderror"
                              placeholder="Ví dụ: Dị ứng Penicillin (nổi mề đay, ngứa), dị ứng Aspirin & Ibuprofen (khó thở, phù mạch)...">{!! old('allergies', $member->allergies) !!}</textarea>
                    @error('allergies')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <!-- Nút gợi ý nhanh dị ứng thuốc phổ biến -->
                    <div class="mt-2">
                        <div class="small text-muted mb-1"><i class="bi bi-lightning-charge text-danger me-1"></i>Bấm để thêm nhanh thuốc hay gây dị ứng:</div>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-allergy-sugg" style="font-size: 11.5px;">Dị ứng Penicillin / Amoxicillin</button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-allergy-sugg" style="font-size: 11.5px;">Dị ứng nhóm NSAID (Aspirin, Ibuprofen)</button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-allergy-sugg" style="font-size: 11.5px;">Dị ứng Cephalosporin</button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-allergy-sugg" style="font-size: 11.5px;">Dị ứng kháng sinh Sulfamid</button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-allergy-sugg" style="font-size: 11.5px;">Dị ứng Paracetamol</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. BỆNH LÝ NỀN & MÃN TÍNH -->
            <div class="content-card mb-3 border-warning border-opacity-50">
                <div class="content-card-header bg-warning bg-opacity-10 d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-heart-pulse-fill text-warning me-2"></i>3. Bệnh lý nền mạn tính & Tình trạng sức khỏe
                    </span>
                    <span class="badge bg-warning text-dark">Phác đồ dài hạn</span>
                </div>
                <div class="content-card-body p-3">
                    <label for="underlying_diseases" class="form-label fw-semibold text-dark mb-1">
                        Danh sách các bệnh lý nền đang mắc phải:
                    </label>
                    <textarea id="underlying_diseases" name="underlying_diseases" rows="3" maxlength="3000"
                              class="form-control @error('underlying_diseases') is-invalid @enderror"
                              placeholder="Ví dụ: Tăng huyết áp vô căn độ 2, Đái tháo đường type 2 (10 năm), Viêm loét dạ dày tá tràng...">{!! old('underlying_diseases', $member->underlying_diseases) !!}</textarea>
                    @error('underlying_diseases')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <!-- Nút gợi ý nhanh bệnh lý nền phổ biến -->
                    <div class="mt-2">
                        <div class="small text-muted mb-1"><i class="bi bi-lightning-charge text-warning me-1"></i>Bấm để thêm nhanh bệnh mạn tính:</div>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-disease-sugg" style="font-size: 11.5px;">Tăng huyết áp</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-disease-sugg" style="font-size: 11.5px;">Đái tháo đường type 2</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-disease-sugg" style="font-size: 11.5px;">Viêm loét dạ dày tá tràng</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-disease-sugg" style="font-size: 11.5px;">Bệnh mạch vành / Tim mạch</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-disease-sugg" style="font-size: 11.5px;">Hen phế quản (Suyễn)</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-disease-sugg" style="font-size: 11.5px;">Rối loạn mỡ máu (Lipid máu)</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-disease-sugg" style="font-size: 11.5px;">Bệnh Gút (Gout)</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nút bấm hành động -->
            <div class="content-card">
                <div class="content-card-body p-3 d-flex justify-content-between align-items-center">
                    <a href="{{ route('members.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i>Hủy bỏ
                    </a>
                    <button type="submit" class="btn btn-erp-primary px-4">
                        <i class="bi bi-check2-circle me-1"></i>{{ $editing ? 'Lưu cập nhật hồ sơ' : 'Tạo hồ sơ thành viên' }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- CỘT PHẢI: LIVE PREVIEW THẺ Y TẾ GIA ĐÌNH & LỢI ÍCH (4 CỘT) -->
    <div class="col-12 col-lg-4">
        <!-- CARD LIVE PREVIEW THẺ Y TẾ -->
        <div class="content-card mb-3 sticky-top" style="top: 15px; z-index: 10;">
            <div class="content-card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold small"><i class="bi bi-credit-card-2-front-fill me-1"></i>THẺ Y TẾ GIA ĐÌNH</span>
                <span class="badge bg-white text-primary" style="font-size: 10px;">Live Card</span>
            </div>
            <div class="content-card-body p-3">
                <div class="border rounded p-3 bg-light shadow-sm position-relative">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-5 shadow-sm" id="previewAvatarCircle" style="width: 52px; height: 52px;">
                            ?
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0" id="previewName">Họ và tên</h5>
                            <div class="text-muted small" id="previewRel">Mối quan hệ: —</div>
                        </div>
                    </div>

                    <div class="row g-2 small border-top pt-2 mt-2">
                        <div class="col-6">
                            <span class="text-muted d-block" style="font-size: 11px;">Độ tuổi & Giới tính:</span>
                            <strong class="text-dark" id="previewAgeGender">—</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block" style="font-size: 11px;">Nhóm máu:</span>
                            <span class="badge bg-danger-subtle text-danger" id="previewBlood">Chưa rõ</span>
                        </div>
                    </div>

                    <!-- Khu vực dị ứng -->
                    <div class="mt-2" id="previewAllergyContainer" style="display: none;">
                        <div class="alert alert-danger py-1 px-2 mb-0" style="font-size: 11.5px;">
                            <strong class="d-block text-danger"><i class="bi bi-shield-slash-fill me-1"></i>Dị ứng thuốc:</strong>
                            <span id="previewAllergyText"></span>
                        </div>
                    </div>

                    <!-- Khu vực bệnh nền -->
                    <div class="mt-2" id="previewDiseaseContainer" style="display: none;">
                        <div class="alert alert-warning py-1 px-2 mb-0" style="font-size: 11.5px;">
                            <strong class="d-block text-warning-emphasis"><i class="bi bi-heart-pulse-fill me-1"></i>Bệnh mạn tính:</strong>
                            <span id="previewDiseaseText"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CẨM NĂNG Y TẾ: Ý NGHĨA HỒ SƠ BỆNH ÁN -->
        <div class="content-card">
            <div class="content-card-header bg-light py-2">
                <span class="fw-bold text-dark small"><i class="bi bi-lightbulb-fill text-warning me-1"></i>TẠI SAO CẦN HỒ SƠ Y TẾ?</span>
            </div>
            <div class="content-card-body p-3 small text-muted">
                <div class="mb-2">
                    <strong class="text-dark"><i class="bi bi-shield-check text-success me-1"></i>Ngăn ngừa tai biến dị ứng:</strong>
                    <div>Hệ thống AI sẽ tự động chặn các đơn thuốc chứa hoạt chất mà người thân có tiền sử dị ứng, tránh sốc phản vệ nguy hiểm.</div>
                </div>
                <div class="mb-2">
                    <strong class="text-dark"><i class="bi bi-heart-pulse text-danger me-1"></i>Theo dõi bệnh mạn tính:</strong>
                    <div>Giúp người nhà nắm rõ phác đồ thuốc huyết áp, tiểu đường định kỳ của ông bà, cha mẹ để nhắc uống đúng giờ.</div>
                </div>
                <div>
                    <strong class="text-dark"><i class="bi bi-hospital text-primary me-1"></i>Tiện lợi khi đi khám:</strong>
                    <div>Cung cấp nhanh danh mục thuốc đang dùng và bệnh nền cho bác sĩ tại phòng khám / bệnh viện.</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const nameIn = document.getElementById('name');
    const relIn = document.getElementById('relationship');
    const dobIn = document.getElementById('dob');
    const genderIn = document.getElementById('gender');
    const bloodIn = document.getElementById('blood_type');
    const allergyIn = document.getElementById('allergies');
    const diseaseIn = document.getElementById('underlying_diseases');

    const pName = document.getElementById('previewName');
    const pRel = document.getElementById('previewRel');
    const pAgeGender = document.getElementById('previewAgeGender');
    const pBlood = document.getElementById('previewBlood');
    const pAvatarCircle = document.getElementById('previewAvatarCircle');
    const pAllergyContainer = document.getElementById('previewAllergyContainer');
    const pAllergyText = document.getElementById('previewAllergyText');
    const pDiseaseContainer = document.getElementById('previewDiseaseContainer');
    const pDiseaseText = document.getElementById('previewDiseaseText');
    const ageNotice = document.getElementById('ageNotice');

    function updatePreview() {
        const nameVal = nameIn.value.trim();
        pName.innerText = nameVal || 'Họ và tên';
        pAvatarCircle.innerText = nameVal ? nameVal.charAt(0).toUpperCase() : '?';

        const relOpt = relIn.selectedOptions[0];
        pRel.innerText = relOpt && relOpt.value ? relOpt.text : 'Mối quan hệ: —';

        // Tuổi và giới tính
        const genderOpt = genderIn.selectedOptions[0];
        const genderTxt = genderOpt ? genderOpt.text : 'Nam';

        if (dobIn.value) {
            const birthDate = new Date(dobIn.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            pAgeGender.innerText = (age >= 0 ? age + ' tuổi' : '—') + ' · ' + genderTxt;
            ageNotice.innerText = 'Tuổi hiện tại: ' + age + ' tuổi';
        } else {
            pAgeGender.innerText = '— · ' + genderTxt;
            ageNotice.innerText = '';
        }

        // Nhóm máu
        const bloodVal = bloodIn.value;
        pBlood.innerText = bloodVal ? 'Nhóm máu ' + bloodVal : 'Chưa rõ';

        // Dị ứng
        const allergyVal = allergyIn.value.trim();
        if (allergyVal) {
            pAllergyContainer.style.display = 'block';
            pAllergyText.innerText = allergyVal;
        } else {
            pAllergyContainer.style.display = 'none';
        }

        // Bệnh nền
        const diseaseVal = diseaseIn.value.trim();
        if (diseaseVal) {
            pDiseaseContainer.style.display = 'block';
            pDiseaseText.innerText = diseaseVal;
        } else {
            pDiseaseContainer.style.display = 'none';
        }
    }

    nameIn.addEventListener('input', updatePreview);
    relIn.addEventListener('change', updatePreview);
    dobIn.addEventListener('change', updatePreview);
    genderIn.addEventListener('change', updatePreview);
    bloodIn.addEventListener('change', updatePreview);
    allergyIn.addEventListener('input', updatePreview);
    diseaseIn.addEventListener('input', updatePreview);

    // Gợi ý nhanh dị ứng
    document.querySelectorAll('.btn-allergy-sugg').forEach(btn => {
        btn.addEventListener('click', function () {
            const current = allergyIn.value.trim();
            const textToAdd = this.innerText.trim();
            if (current) {
                if (!current.includes(textToAdd)) {
                    allergyIn.value = current + ', ' + textToAdd;
                }
            } else {
                allergyIn.value = textToAdd;
            }
            updatePreview();
        });
    });

    // Gợi ý nhanh bệnh lý nền
    document.querySelectorAll('.btn-disease-sugg').forEach(btn => {
        btn.addEventListener('click', function () {
            const current = diseaseIn.value.trim();
            const textToAdd = this.innerText.trim();
            if (current) {
                if (!current.includes(textToAdd)) {
                    diseaseIn.value = current + ', ' + textToAdd;
                }
            } else {
                diseaseIn.value = textToAdd;
            }
            updatePreview();
        });
    });

    document.addEventListener('DOMContentLoaded', updatePreview);
</script>
@endpush
