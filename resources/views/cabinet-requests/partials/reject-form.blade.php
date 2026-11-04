{{-- Từ chối đề nghị nhập thuốc (form riêng, đặt sau form duyệt) --}}
<div class="content-card mt-3 border-danger">
    <div class="content-card-header bg-danger-subtle">
        <span class="fw-bold text-danger"><i class="bi bi-x-octagon me-2"></i>Từ chối đề nghị</span>
    </div>
    <div class="content-card-body p-3">
        <form method="POST" action="{{ route('cabinet.requests.reject', $cabinetRequest) }}"
              onsubmit="return confirm('Từ chối đề nghị nhập thuốc này?')">
            @csrf
            <label for="review_note" class="form-label small fw-semibold">Lý do từ chối <span class="text-danger">*</span></label>
            <textarea id="review_note" name="review_note" rows="2" maxlength="1000" required
                      class="form-control form-control-sm @error('review_note') is-invalid @enderror"
                      placeholder="Ví dụ: Thuốc đã quá hạn, sai số lô, không nên dùng cho trẻ nhỏ...">{{ old('review_note') }}</textarea>
            @error('review_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="text-end mt-2">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-x-lg me-1"></i>Từ chối đề nghị
                </button>
            </div>
        </form>
    </div>
</div>
