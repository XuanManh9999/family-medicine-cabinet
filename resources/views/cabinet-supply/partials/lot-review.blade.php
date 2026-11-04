{{-- Một lô thuốc gia đình đã mua, chờ Bác sĩ xác nhận. Biến: $lot, $canReview --}}
@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $course = $lot->prescriptionItem?->treatmentCourse;
@endphp
<div class="p-3" id="lot-{{ $lot->id }}">
    <div class="d-flex flex-wrap gap-3">
        @if ($lot->box_image_paths)
            <div class="d-flex gap-2">
                @foreach ($lot->box_image_paths as $index => $path)
                    <a href="{{ route('cabinet.lots.photo', [$lot->id, $index]) }}" target="_blank" title="Mở ảnh gốc">
                        <img src="{{ route('cabinet.lots.photo', [$lot->id, $index]) }}" alt="Ảnh vỏ hộp {{ $index + 1 }}"
                             class="rounded border" style="width: 96px; height: 96px; object-fit: cover;">
                    </a>
                @endforeach
            </div>
        @endif
        <div class="flex-grow-1 small">
            <div class="fw-bold fs-6">{{ $lot->display_name }}
                <span class="badge bg-warning text-dark ms-1">Chờ Bác sĩ xác nhận</span>
            </div>
            <div>{{ $qty($lot->quantity) }} {{ $lot->unit }} · HSD <strong>{{ $lot->expiry_date?->format('d/m/Y') }}</strong>
                @if ($lot->batch_number) · Lô {{ $lot->batch_number }} @endif
                @if ($lot->storage_location) · Cất: {{ $lot->storage_location }} @endif
            </div>
            <div class="text-muted">
                @if ($course)
                    Mua theo đơn <strong>{{ $course->title }}</strong> ({{ $lot->prescriptionItem->dosage }} · {{ $lot->prescriptionItem->frequency }}) cho {{ $course->familyMember?->name ?? 'gia đình' }}
                @else
                    Mua bổ sung theo yêu cầu đã được đồng ý
                @endif
                · {{ $lot->purchaser?->name ?? '—' }}, {{ $lot->created_at->format('H:i d/m/Y') }}
            </div>
            @unless ($lot->box_image_paths)
                <div class="text-muted"><i class="bi bi-camera-video-off me-1"></i>Không kèm ảnh vỏ hộp</div>
            @endunless
        </div>
    </div>

    @if ($canReview)
        <div class="row g-2 mt-1">
            <div class="col-12 col-lg-7">
                <form method="POST" action="{{ route('cabinet.lots.confirm', $lot->id) }}" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="review_note" maxlength="1000" class="form-control form-control-sm" placeholder="Dặn dò khi dùng (không bắt buộc)">
                    <button type="submit" class="btn btn-sm btn-success text-nowrap"><i class="bi bi-check2-circle me-1"></i>Xác nhận đúng thuốc</button>
                </form>
            </div>
            <div class="col-12 col-lg-5">
                <form method="POST" action="{{ route('cabinet.lots.return', $lot->id) }}" class="d-flex gap-2"
                      onsubmit="return confirm('Trả lại thuốc này? Gia đình sẽ phải đổi / mua lại.')">
                    @csrf
                    <input type="text" name="review_note" maxlength="1000" required class="form-control form-control-sm" placeholder="Lý do, VD: sai hàm lượng">
                    <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">Trả lại</button>
                </form>
            </div>
        </div>
    @endif
</div>
