<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Medicine;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AiAssistantController extends Controller
{
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Bảng điều khiển Trợ lý Y tế AI Độc lập
     */
    public function index(Request $request)
    {
        $family = $this->ensureFamily($request);

        $members = $family->members()->orderBy('name')->get();
        $cabinetItems = $family->cabinetItems()->with('medicine.category')->where('quantity', '>', 0)->get();

        $suggestedQuestions = [
            'Quên uống 1 cữ thuốc thì nên xử lý thế nào cho an toàn?',
            'Uống thuốc kháng sinh và thuốc hạ sốt trước hay sau bữa ăn?',
            'Bảo quản thuốc dạng viên sủi và thuốc nhỏ mắt như thế nào?',
            'Người lớn và trẻ em sốt trên 38.5°C uống Paracetamol liều lượng ra sao?',
            'Có được bẻ đôi hoặc nghiền nát viên thuốc nén bao phim không?',
            'Tại sao không nên uống thuốc cùng sữa, nước ngọt hoặc nước trà?',
        ];

        return view('ai-assistant.index', compact(
            'family',
            'members',
            'cabinetItems',
            'suggestedQuestions'
        ));
    }

    /**
     * Quét nhanh đơn thuốc / vỏ hộp thuốc qua Vision OCR
     */
    public function scanPrescription(Request $request)
    {
        $family = $this->ensureFamily($request);

        $request->validate([
            'prescription_image' => 'required|file|mimes:jpeg,png,jpg,webp|max:10240',
            'family_member_id' => 'nullable|exists:family_members,id',
        ], [
            'prescription_image.required' => 'Vui lòng chọn ảnh chụp đơn thuốc hoặc vỏ hộp thuốc.',
            'prescription_image.mimes' => 'Hình ảnh phải có định dạng JPEG, PNG, JPG hoặc WEBP.',
            'prescription_image.max' => 'Dung lượng ảnh tối đa không quá 10MB.',
        ]);

        $file = $request->file('prescription_image');
        $storedPath = $file->store('ai_prescriptions', 'public');
        $fullPath = storage_path('app/public/' . $storedPath);

        // 1. Gọi Vision OCR bóc tách dữ liệu
        $ocrResult = $this->geminiService->analyzePrescriptionImage($fullPath, $file->getMimeType());

        // 2. Lấy hồ sơ thành viên nếu có chọn để kiểm tra tương tác thuốc
        $patientProfile = [];
        if ($memberId = $request->input('family_member_id')) {
            $member = FamilyMember::where('family_id', $family->id)->find($memberId);
            if ($member) {
                $patientProfile = [
                    'name' => $member->name,
                    'age' => $member->age,
                    'allergies' => $member->allergies ?? '',
                    'underlying_diseases' => $member->underlying_diseases ?? '',
                ];
            }
        }

        // 3. Kiểm tra an toàn tương tác thuốc nếu bóc tách được danh sách thuốc
        $safetyReport = null;
        if (!empty($ocrResult['medicines']) && is_array($ocrResult['medicines'])) {
            $medicinesList = array_map(function ($item) {
                return [
                    'name' => $item['name'] ?? '',
                    'active_ingredient' => $item['active_ingredient'] ?? '',
                    'dosage' => $item['dosage'] ?? '',
                    'frequency' => $item['frequency'] ?? '',
                ];
            }, $ocrResult['medicines']);

            $safetyReport = $this->geminiService->checkDrugInteractions($medicinesList, $patientProfile);
        }

        return response()->json([
            'success' => true,
            'image_url' => asset('storage/' . $storedPath),
            'ocr_data' => $ocrResult,
            'safety_report' => $safetyReport,
            'patient_profile' => $patientProfile,
        ]);
    }

    /**
     * Xử lý hỏi đáp tư vấn y tế gia đình AI qua trò chuyện
     */
    public function chat(Request $request)
    {
        $family = $this->ensureFamily($request);

        $request->validate([
            'question' => 'required|string|min:3|max:1000',
            'family_member_id' => 'nullable|exists:family_members,id',
        ], [
            'question.required' => 'Vui lòng nhập câu hỏi y tế cần tư vấn.',
            'question.min' => 'Câu hỏi phải có ít nhất 3 ký tự.',
        ]);

        $question = trim($request->input('question'));

        // Xây dựng ngữ cảnh gia đình
        $familyContext = [
            'family_name' => $family->name,
            'members_count' => $family->members()->count(),
            'cabinet_medicines' => $family->cabinetItems()->with('medicine')
                ->where('quantity', '>', 0)
                ->take(10)
                ->get()
                ->map(fn ($item) => $item->display_name . ' (' . ($item->medicine?->active_ingredient ?: 'N/A') . ')')
                ->toArray(),
        ];

        if ($memberId = $request->input('family_member_id')) {
            $member = FamilyMember::where('family_id', $family->id)->find($memberId);
            if ($member) {
                $familyContext['target_member'] = [
                    'name' => $member->name,
                    'age' => $member->age,
                    'gender' => $member->gender_label,
                    'allergies' => $member->allergies,
                    'underlying_diseases' => $member->underlying_diseases,
                ];
            }
        }

        $result = $this->geminiService->askMedicalAssistant($question, $familyContext);

        return response()->json([
            'success' => true,
            'question' => $question,
            'response' => $result,
            'answered_at' => Carbon::now()->format('H:i d/m/Y'),
        ]);
    }

    /**
     * Đảm bảo người dùng thuộc về hộ gia đình
     */
    protected function ensureFamily(Request $request)
    {
        $family = $request->user()->family;
        if (!$family) {
            abort(403, 'Tài khoản chưa thuộc về hộ gia đình.');
        }
        return $family;
    }
}
