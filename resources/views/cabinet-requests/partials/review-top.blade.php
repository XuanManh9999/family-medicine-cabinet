{{-- Phần đầu trang duyệt đề nghị: ảnh vỏ hộp, thông tin người gửi, gợi ý danh mục, cảnh báo an toàn --}}
@php
    $photos = $cabinetRequest->box_image_paths ?? [];
    $hasWarnings = $safety['same_items']->isNotEmpty() || !empty($safety['duplicate_conflicts']) || !empty($safety['member_conflicts']);
@endphp

<div class="row g-3 mb-3">
    <!-- ẢNH VỎ HỘP & THÔNG TIN NGƯỜI GỬI -->
    <div class="col-12 col-lg-7">
        <div class="content-card h-100 border-warning">
            <div class="content-card-header bg-warning-subtle d-flex justify-content-between align-items-center">
                <span class="fw-bold text-warning-emphasis"><i class="bi bi-camera me-2"></i>Ảnh vỏ hộp & thông tin người nhà gửi</span>
                <span class="badge bg-white text-dark border">{{ count($photos) }} ảnh</span>
            </div>
            <div class="content-card-body p-3">
                @if ($photos)
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach ($photos as $index => $path)
                            <a href="{{ route('cabinet.requests.photo', [$cabinetRequest, $index]) }}" target="_blank" title="Mở ảnh gốc">
                                <img src="{{ route('cabinet.requests.photo', [$cabinetRequest, $index]) }}" alt="Ảnh vỏ hộp {{ $index + 1 }}"
                                     class="rounded border" style="width: 160px; height: 160px; object-fit: cover;">
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-secondary py-2 px-3 small mb-3">
                        <i class="bi bi-camera-video-off me-1"></i>Người gửi không kèm ảnh vỏ hộp. Nếu không chắc thông tin, hãy từ chối và yêu cầu chụp ảnh.
                    </div>
                @endif

                <div class="row g-2 small">
                    <div class="col-sm-6">Người gửi: <strong>{{ $cabinetRequest->requester?->name ?? 'Tài khoản đã gỡ' }}</strong>
                        <span class="text-muted">({{ $cabinetRequest->requester?->role_name }})</span></div>
                    <div class="col-sm-6">Gửi lúc: <strong>{{ $cabinetRequest->created_at->format('H:i d/m/Y') }}</strong></div>
                    <div class="col-sm-6">Mua cho: <strong>{{ $cabinetRequest->familyMember?->name ?? 'Dùng chung cả nhà' }}</strong></div>
                    <div class="col-sm-6">
                        @if ($cabinetRequest->record_expense)
                            <i class="bi bi-cash-coin text-success me-1"></i>Tự ghi chi phí khi duyệt
                        @else
                            <i class="bi bi-cash text-muted me-1"></i>Không ghi chi phí tự động
                        @endif
                    </div>
                    @if ($cabinetRequest->active_ingredient)
                        <div class="col-12">Hoạt chất AI đọc từ vỏ hộp: <strong>{{ $cabinetRequest->active_ingredient }}</strong></div>
                    @endif
                    @if ($cabinetRequest->requester_note)
                        <div class="col-12">
                            <div class="p-2 rounded bg-light border"><i class="bi bi-chat-left-quote me-1 text-primary"></i>{{ $cabinetRequest->requester_note }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- GỢI Ý DANH MỤC & CẢNH BÁO AN TOÀN -->
    <div class="col-12 col-lg-5">
        <div class="content-card mb-3">
            <div class="content-card-header bg-light">
                <span class="fw-bold text-dark"><i class="bi bi-search text-primary me-2"></i>Thuốc khớp trong danh mục dược phẩm</span>
            </div>
            <div class="content-card-body p-3 small">
                @forelse ($suggestions as $s)
                    <div class="d-flex justify-content-between align-items-center gap-2 py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <div class="fw-semibold">
                                {{ $s['medicine']->name }}
                                @if ($s['exact'])
                                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px;">Khớp tên</span>
                                @endif
                            </div>
                            <div class="text-muted">{{ $s['medicine']->active_ingredient }} · {{ $s['medicine']->category?->name ?? 'Chưa phân nhóm' }}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 btn-link-medicine" data-medicine-id="{{ $s['medicine']->id }}">Chọn</button>
                    </div>
                @empty
                    <div class="text-muted">Không tìm thấy thuốc tương tự trong danh mục. Có thể giữ "Thuốc tự nhập" hoặc chọn thủ công ở ô bên dưới.</div>
                @endforelse
            </div>
        </div>

        <div class="content-card {{ $hasWarnings ? 'border-danger' : 'border-success' }}">
            <div class="content-card-header {{ $hasWarnings ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                <span class="fw-bold"><i class="bi bi-shield-exclamation me-2"></i>Kiểm tra an toàn tự động</span>
            </div>
            <div class="content-card-body p-3 small">
                @foreach ($safety['same_items'] as $item)
                    <div class="mb-2"><i class="bi bi-box-seam text-warning me-1"></i>
                        Tủ đã có <strong>{{ $item->display_name }}</strong>: còn {{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} {{ $item->unit }}, HSD {{ $item->expiry_date?->format('d/m/Y') }}.
                    </div>
                @endforeach
                @foreach ($safety['duplicate_conflicts'] as $c)
                    <div class="mb-2 text-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i>{{ $c['description'] ?? '' }}</div>
                @endforeach
                @foreach ($safety['member_conflicts'] as $mc)
                    <div class="mb-2 text-danger"><i class="bi bi-person-exclamation me-1"></i>
                        <strong>{{ $mc['member']->name }}:</strong> {{ $mc['conflict']['description'] ?? '' }}
                    </div>
                @endforeach
                @unless ($hasWarnings)
                    <div class="text-success"><i class="bi bi-check-circle me-1"></i>
                        Không thấy trùng thuốc, trùng hoạt chất hay dị ứng đã khai báo ({{ $safety['members_checked'] }} hồ sơ được kiểm tra).
                    </div>
                @endunless
                <div class="text-muted mt-2" style="font-size: 11px;">Bộ luật tự động chỉ hỗ trợ sàng lọc, quyết định cuối cùng thuộc về người duyệt.</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Chọn thuốc gợi ý → gán vào ô "Chọn thuốc có sẵn từ danh mục" của form bên dưới
    document.querySelectorAll('.btn-link-medicine').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const select = document.getElementById('medicine_id');
            select.value = btn.dataset.medicineId;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            select.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });
</script>
@endpush
