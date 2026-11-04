<?php

namespace App\Http\Controllers;

use App\Models\CabinetItem;
use App\Models\CabinetRequest;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Medicine;
use App\Models\MedicineExpense;
use App\Models\SmartAlert;
use App\Models\User;
use App\Services\AlertScannerService;
use App\Services\GeminiService;
use App\Support\CabinetItemValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Luồng Đề nghị nhập thuốc — mỗi người làm phần mình giỏi:
 * - Chủ hộ / Thành viên (người cầm hộp thuốc): chụp ảnh vỏ hộp, nhập tên, HSD, số lượng, nơi cất (AI điền hộ nếu có)
 * - Bác sĩ (người có chuyên môn): đối chiếu ảnh, liên kết danh mục dược phẩm, xem cảnh báo dị ứng / trùng hoạt chất, duyệt
 * - Gia đình chưa có Bác sĩ: Quản trị viên hệ thống duyệt thay
 */
class CabinetRequestController extends Controller
{
    /** Ảnh vỏ hộp lưu trên ổ riêng tư (không public), chỉ xem qua route có kiểm tra quyền */
    private const PHOTO_DISK = 'local';
    private const MAX_PHOTOS = 3;

    public function index(Request $request)
    {
        $family = $this->ensureFamily($request);
        $user = $request->user();

        $status = $request->query('status');
        $query = CabinetRequest::where('family_id', $family->id)
            ->with(['requester', 'reviewer', 'medicine', 'familyMember'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest();

        if (in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $requests = $query->paginate(15)->withQueryString();
        $pendingCount = CabinetRequest::where('family_id', $family->id)->pending()->count();
        $hasDoctor = $this->familyHasDoctor($family);

        return view('cabinet-requests.index', compact('requests', 'pendingCount', 'status', 'user', 'hasDoctor'));
    }

    public function create(Request $request)
    {
        $family = $this->ensureRequester($request);

        $prefill = [];
        if ($medicineId = $request->query('medicine_id')) {
            $medicine = Medicine::find($medicineId);
            if ($medicine) {
                $prefill = [
                    'custom_name' => $medicine->name,
                    'active_ingredient' => $medicine->active_ingredient,
                    'unit' => $medicine->default_unit ?: 'viên',
                ];
            }
        }

        return view('cabinet-requests.create', [
            'prefill' => $prefill,
            'members' => $family->members()->orderBy('name')->get(),
            'hasDoctor' => $this->familyHasDoctor($family),
            'aiConfigured' => !empty(config('services.gemini.api_key')),
        ]);
    }

    public function store(Request $request)
    {
        $family = $this->ensureRequester($request);

        $input = $request->except(['medicine_id', 'notes', 'manufacturing_date']);
        $validator = CabinetItemValidator::make($input, false);
        $validator->addRules([
            'expiry_date' => ['after_or_equal:today'],
            'active_ingredient' => ['nullable', 'string', 'max:255'],
            'family_member_id' => ['nullable', 'integer', Rule::exists('family_members', 'id')->where('family_id', $family->id)],
            'requester_note' => ['nullable', 'string', 'max:1000'],
            'box_photos' => ['nullable', 'array', 'max:' . self::MAX_PHOTOS],
            'box_photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);
        $validator->setCustomMessages(array_merge($validator->customMessages, [
            'expiry_date.after_or_equal' => 'Thuốc đã quá hạn sử dụng — không nên đưa vào tủ thuốc. Hãy kiểm tra lại dòng HSD/EXP trên vỏ hộp.',
            'box_photos.max' => 'Chỉ gửi tối đa ' . self::MAX_PHOTOS . ' ảnh vỏ hộp.',
            'box_photos.*.image' => 'Ảnh vỏ hộp phải là hình ảnh (JPG, PNG, WEBP).',
            'box_photos.*.max' => 'Mỗi ảnh vỏ hộp tối đa 5MB.',
            'family_member_id.exists' => 'Thành viên được chọn không thuộc gia đình của bạn.',
        ]));
        $data = $validator->validate();

        $photoPaths = [];
        foreach ($request->file('box_photos', []) as $photo) {
            $photoPaths[] = $photo->store("cabinet-requests/{$family->id}", self::PHOTO_DISK);
        }

        $cabinetRequest = CabinetRequest::create([
            'family_id' => $family->id,
            'requested_by' => $request->user()->id,
            'family_member_id' => $data['family_member_id'] ?? null,
            'custom_name' => $data['custom_name'],
            'active_ingredient' => $data['active_ingredient'] ?? null,
            'batch_number' => $data['batch_number'] ?? null,
            'expiry_date' => $data['expiry_date'],
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'storage_location' => $data['storage_location'] ?? null,
            'price_per_unit' => $data['price_per_unit'] ?? null,
            'requester_note' => $data['requester_note'] ?? null,
            'box_image_paths' => $photoPaths ?: null,
            'record_expense' => $request->user()->isFamilyAdmin() && $request->boolean('record_expense'),
            'status' => CabinetRequest::STATUS_PENDING,
        ]);

        $hasDoctor = $this->familyHasDoctor($family);

        // Thông báo trong Trung tâm cảnh báo để Bác sĩ thấy có đề nghị chờ duyệt
        SmartAlert::create([
            'family_id' => $family->id,
            'user_id' => $request->user()->id,
            'type' => 'cabinet_request',
            'level' => 'info',
            'title' => "Đề nghị nhập thuốc chờ duyệt: {$cabinetRequest->display_name}",
            'content' => "{$request->user()->name} đề nghị nhập {$this->quantityText($cabinetRequest)} \"{$cabinetRequest->display_name}\" (HSD {$cabinetRequest->expiry_date->format('d/m/Y')})"
                . ($photoPaths ? ' kèm ' . count($photoPaths) . ' ảnh vỏ hộp' : '') . '. '
                . ($hasDoctor ? 'Bác sĩ phụ trách vui lòng kiểm tra và duyệt.' : 'Gia đình chưa có Bác sĩ — Quản trị viên hệ thống sẽ duyệt thay.'),
            'is_read' => false,
            'action_url' => $this->reviewPath($cabinetRequest),
        ]);

        $message = "Đã gửi đề nghị nhập \"{$cabinetRequest->display_name}\". ";
        $message .= $hasDoctor
            ? 'Bác sĩ sẽ kiểm tra và duyệt trước khi thuốc được đưa vào tủ.'
            : 'Gia đình chưa có Bác sĩ nên Quản trị viên hệ thống sẽ duyệt thay. Bạn có thể chỉ định Bác sĩ tại trang Hồ sơ.';

        return redirect()->route('cabinet.requests.index')->with('success', $message);
    }

    /**
     * AI đọc ảnh vỏ hộp → trả về các trường để điền sẵn vào form (người gửi vẫn phải kiểm tra lại)
     */
    public function scanBox(Request $request, GeminiService $gemini)
    {
        $this->ensureRequester($request);
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ], [
            'photo.required' => 'Vui lòng chọn ảnh vỏ hộp thuốc.',
            'photo.image' => 'Tệp tải lên phải là hình ảnh.',
            'photo.max' => 'Ảnh tối đa 5MB.',
        ]);

        $file = $request->file('photo');

        return response()->json($gemini->analyzeMedicineBox($file->getRealPath(), $file->getMimeType()));
    }

    /**
     * Xem ảnh vỏ hộp (chỉ người trong gia đình hoặc Quản trị viên hệ thống)
     */
    public function photo(Request $request, CabinetRequest $cabinetRequest, int $index)
    {
        $user = $request->user();
        abort_unless($user->isSystemAdmin() || (int) $user->family_id === (int) $cabinetRequest->family_id, 404);

        $path = ($cabinetRequest->box_image_paths ?? [])[$index] ?? null;
        abort_unless($path && Storage::disk(self::PHOTO_DISK)->exists($path), 404);

        return Storage::disk(self::PHOTO_DISK)->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function review(Request $request, CabinetRequest $cabinetRequest)
    {
        $family = $this->ensureReviewer($request, $cabinetRequest);

        if (!$cabinetRequest->isPending()) {
            return $this->redirectAfterReview($request)->with('info', 'Đề nghị này đã được xử lý trước đó.');
        }

        $cabinetRequest->load(['requester', 'familyMember']);
        $suggestions = $this->suggestMedicines($cabinetRequest);

        // Khớp chính xác tên → chọn sẵn thuốc trong danh mục để Bác sĩ chỉ cần xác nhận
        $item = new CabinetItem($cabinetRequest->itemAttributes());
        $exact = $suggestions->first(fn ($s) => $s['exact']);
        if ($exact && !$item->medicine_id) {
            $item->medicine_id = $exact['medicine']->id;
        }

        return view('cabinet.form', [
            'item' => $item,
            'medicines' => Medicine::with('category')->orderBy('name')->get(),
            'editing' => false,
            'cabinetRequest' => $cabinetRequest,
            'suggestions' => $suggestions,
            'safety' => $this->safetyReport($family, $cabinetRequest, $exact['medicine'] ?? null),
            'pageTitle' => 'Duyệt đề nghị nhập thuốc - Tủ thuốc gia đình',
            'formHeading' => 'Duyệt đề nghị nhập thuốc: ' . $cabinetRequest->display_name,
            'formIntro' => 'Đối chiếu ảnh vỏ hộp với thông tin người nhà nhập, liên kết đúng thuốc trong danh mục và xem cảnh báo an toàn trước khi duyệt.',
            'formAction' => route('cabinet.requests.approve', $cabinetRequest),
            'backUrl' => $request->user()->family_id ? route('cabinet.requests.index') : route('admin.cabinet-requests.index'),
            'submitLabel' => 'Duyệt & nhập vào tủ thuốc',
            'topPartial' => 'cabinet-requests.partials.review-top',
            'afterFormPartial' => 'cabinet-requests.partials.reject-form',
        ]);
    }

    public function approve(Request $request, CabinetRequest $cabinetRequest)
    {
        $family = $this->ensureReviewer($request, $cabinetRequest);
        $data = CabinetItemValidator::make($request->all(), true)->validate();

        $result = DB::transaction(function () use ($cabinetRequest, $data, $family, $request) {
            $locked = CabinetRequest::whereKey($cabinetRequest->id)->lockForUpdate()->first();
            if (!$locked || !$locked->isPending()) {
                return null;
            }

            $data['family_id'] = $family->id;
            $data['status'] = CabinetItem::computeStatus($data['expiry_date'], $data['quantity']);
            $item = CabinetItem::create($data);

            $expense = null;
            $total = (float) $data['quantity'] * (float) ($data['price_per_unit'] ?? 0);
            if ($locked->record_expense && $total > 0) {
                $expense = MedicineExpense::create([
                    'family_id' => $family->id,
                    'family_member_id' => $locked->family_member_id,
                    'title' => 'Mua thuốc: ' . $item->display_name,
                    'amount' => round($total, 2),
                    'purchase_date' => $locked->created_at->toDateString(),
                    'notes' => "Ghi nhận tự động từ đề nghị nhập thuốc #{$locked->id}",
                ]);
            }

            $locked->update(array_merge($data, [
                'status' => CabinetRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'cabinet_item_id' => $item->id,
                'medicine_expense_id' => $expense?->id,
            ]));

            $this->replaceRequestAlert($locked, 'info', "Đã duyệt nhập thuốc: {$item->display_name}",
                "{$request->user()->name} đã duyệt đưa {$this->quantityText($locked)} \"{$item->display_name}\" vào tủ thuốc"
                . ($expense ? ' và ghi nhận chi phí ' . number_format($total, 0, ',', '.') . ' đ.' : '.'),
                route('cabinet.index', [], false));

            return $item;
        });

        if (!$result) {
            return $this->redirectAfterReview($request)->with('warning', 'Đề nghị này đã được xử lý trước đó.');
        }

        $this->refreshAlerts($family);

        return $this->redirectAfterReview($request)
            ->with('success', "Đã duyệt và nhập \"{$result->display_name}\" vào tủ thuốc của {$family->name}.");
    }

    public function reject(Request $request, CabinetRequest $cabinetRequest)
    {
        $this->ensureReviewer($request, $cabinetRequest);
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ], [
            'review_note.required' => 'Vui lòng ghi lý do từ chối để gia đình biết cần bổ sung gì.',
        ]);

        if (!$cabinetRequest->isPending()) {
            return $this->redirectAfterReview($request)->with('warning', 'Đề nghị này đã được xử lý trước đó.');
        }

        $cabinetRequest->update([
            'status' => CabinetRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'],
        ]);

        $this->replaceRequestAlert($cabinetRequest, 'warning', "Từ chối đề nghị nhập thuốc: {$cabinetRequest->display_name}",
            "{$request->user()->name} đã từ chối đề nghị nhập \"{$cabinetRequest->display_name}\". Lý do: {$validated['review_note']}",
            route('cabinet.requests.index', [], false));

        return $this->redirectAfterReview($request)->with('success', 'Đã từ chối đề nghị nhập thuốc.');
    }

    public function cancel(Request $request, CabinetRequest $cabinetRequest)
    {
        $this->ensureFamilyOwns($request, $cabinetRequest);
        abort_unless((int) $cabinetRequest->requested_by === (int) $request->user()->id, 403, 'Bạn chỉ có thể hủy đề nghị do chính mình gửi.');

        if (!$cabinetRequest->isPending()) {
            return back()->with('warning', 'Chỉ hủy được đề nghị đang chờ duyệt.');
        }

        $cabinetRequest->update(['status' => CabinetRequest::STATUS_CANCELLED]);
        SmartAlert::where('family_id', $cabinetRequest->family_id)
            ->where('type', 'cabinet_request')
            ->where('action_url', $this->reviewPath($cabinetRequest))
            ->delete();

        return back()->with('success', 'Đã hủy đề nghị nhập thuốc.');
    }

    /**
     * Gợi ý thuốc trong danh mục khớp với tên in trên vỏ hộp / hoạt chất AI đọc được
     */
    private function suggestMedicines(CabinetRequest $cabinetRequest): Collection
    {
        $needle = $this->normalize($cabinetRequest->custom_name);
        $ingredient = $this->normalize((string) $cabinetRequest->active_ingredient);
        $tokens = collect(preg_split('/[\s\-\/,()+]+/u', $needle . ' ' . $ingredient))
            ->filter(fn ($t) => mb_strlen($t) >= 3 && !preg_match('/^\d+(mg|ml|g|mcg)?$/u', $t))
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return collect();
        }

        return Medicine::with('category')->get()
            ->map(function (Medicine $medicine) use ($needle, $tokens) {
                $name = $this->normalize($medicine->name);
                $haystack = $name . ' ' . $this->normalize((string) $medicine->active_ingredient);
                $hits = $tokens->filter(fn ($t) => str_contains($haystack, $t))->count();

                return [
                    'medicine' => $medicine,
                    'exact' => $name === $needle,
                    'score' => ($name === $needle ? 100 : 0) + $hits,
                ];
            })
            ->filter(fn ($s) => $s['score'] > 0)
            ->sortByDesc('score')
            ->take(5)
            ->values();
    }

    /**
     * Cảnh báo an toàn cho Bác sĩ: trùng thuốc / trùng hoạt chất với tủ hiện có, dị ứng & bệnh nền của thành viên
     */
    private function safetyReport(Family $family, CabinetRequest $cabinetRequest, ?Medicine $matched): array
    {
        $newMedicine = [
            'name' => $cabinetRequest->custom_name,
            'active_ingredient' => trim(($cabinetRequest->active_ingredient ?? '') . ' ' . ($matched?->active_ingredient ?? '')),
        ];

        $inStock = CabinetItem::where('family_id', $family->id)
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '>=', today())
            ->with('medicine')
            ->get();

        // Thuốc này đã có sẵn trong tủ
        $needle = $this->normalize($cabinetRequest->custom_name);
        $sameItems = $inStock->filter(function (CabinetItem $item) use ($needle, $matched) {
            return ($matched && $item->medicine_id === $matched->id)
                || $this->normalize($item->custom_name ?? '') === $needle
                || $this->normalize($item->medicine?->name ?? '') === $needle;
        })->values();

        $gemini = app(GeminiService::class);

        // Trùng hoạt chất (VD: thêm một loại Paracetamol khi tủ đã có)
        $existing = $inStock->map(fn (CabinetItem $item) => [
            'name' => $item->display_name,
            'active_ingredient' => (string) $item->medicine?->active_ingredient,
        ])->all();
        $duplicateConflicts = collect($gemini->checkLocalRules(array_merge([$newMedicine], $existing))['conflicts'] ?? [])
            ->filter(fn ($c) => in_array($cabinetRequest->custom_name, (array) ($c['medicines_involved'] ?? []), true))
            ->values()
            ->all();

        // Dị ứng / chống chỉ định theo hồ sơ từng thành viên (ưu tiên người sẽ dùng nếu có chọn)
        $members = $cabinetRequest->family_member_id
            ? FamilyMember::whereKey($cabinetRequest->family_member_id)->get()
            : FamilyMember::where('family_id', $family->id)->get();

        $memberConflicts = [];
        foreach ($members as $member) {
            if (!$member->allergies && !$member->underlying_diseases) {
                continue;
            }
            $result = $gemini->checkLocalRules([$newMedicine], [
                'age' => $member->age,
                'allergies' => $member->allergies ?? '',
                'underlying_diseases' => $member->underlying_diseases ?? '',
            ]);
            foreach ($result['conflicts'] ?? [] as $conflict) {
                $memberConflicts[] = ['member' => $member, 'conflict' => $conflict];
            }
        }

        return [
            'same_items' => $sameItems,
            'duplicate_conflicts' => $duplicateConflicts,
            'member_conflicts' => $memberConflicts,
            'members_checked' => $members->count(),
        ];
    }

    private function replaceRequestAlert(CabinetRequest $cabinetRequest, string $level, string $title, string $content, string $actionUrl): void
    {
        SmartAlert::where('family_id', $cabinetRequest->family_id)
            ->where('type', 'cabinet_request')
            ->where('action_url', $this->reviewPath($cabinetRequest))
            ->delete();

        SmartAlert::create([
            'family_id' => $cabinetRequest->family_id,
            'user_id' => $cabinetRequest->requested_by,
            'type' => 'cabinet_request',
            'level' => $level,
            'title' => $title,
            'content' => $content,
            'is_read' => false,
            'action_url' => $actionUrl,
        ]);
    }

    private function familyHasDoctor(Family $family): bool
    {
        return User::where('family_id', $family->id)->where('role', 'doctor_caregiver')->exists();
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', Str::lower($value)));
    }

    private function reviewPath(CabinetRequest $cabinetRequest): string
    {
        return route('cabinet.requests.review', $cabinetRequest, false);
    }

    private function quantityText(CabinetRequest $cabinetRequest): string
    {
        return rtrim(rtrim(number_format((float) $cabinetRequest->quantity, 2, ',', '.'), '0'), ',') . ' ' . $cabinetRequest->unit;
    }

    private function redirectAfterReview(Request $request)
    {
        return $request->user()->family_id
            ? redirect()->route('cabinet.requests.index')
            : redirect()->route('admin.cabinet-requests.index');
    }

    private function refreshAlerts(Family $family): void
    {
        try {
            app(AlertScannerService::class)->scanFamily($family);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function ensureFamily(Request $request): Family
    {
        $family = $request->user()->family;
        abort_unless($family, 403, 'Tài khoản chưa thuộc hộ gia đình.');

        return $family;
    }

    private function ensureRequester(Request $request): Family
    {
        $family = $this->ensureFamily($request);
        abort_unless($request->user()->canRequestCabinetItems(), 403, 'Bác sĩ / Quản trị viên nhập thuốc trực tiếp vào tủ, không cần gửi đề nghị.');

        return $family;
    }

    private function ensureFamilyOwns(Request $request, CabinetRequest $cabinetRequest): Family
    {
        $family = $this->ensureFamily($request);
        abort_unless((int) $cabinetRequest->family_id === (int) $family->id, 404);

        return $family;
    }

    /**
     * Người duyệt: Bác sĩ / Người chăm sóc của chính gia đình đó, hoặc Quản trị viên hệ thống (duyệt thay mọi gia đình)
     */
    private function ensureReviewer(Request $request, CabinetRequest $cabinetRequest): Family
    {
        $user = $request->user();
        if ($user->isSystemAdmin()) {
            return $cabinetRequest->family()->firstOrFail();
        }

        $family = $this->ensureFamilyOwns($request, $cabinetRequest);
        abort_unless($user->canManageCabinet(), 403, 'Chỉ Bác sĩ / Người chăm sóc y tế hoặc Quản trị viên mới được duyệt đề nghị nhập thuốc.');

        return $family;
    }
}
