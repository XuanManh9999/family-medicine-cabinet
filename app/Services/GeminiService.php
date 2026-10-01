<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
        $this->baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
    }

    /**
     * Gemini Vision OCR - Trích xuất đơn thuốc và vỏ hộp thuốc từ hình ảnh
     *
     * @param string $imagePathOrBase64 Đường dẫn file ảnh trên server hoặc chuỗi base64
     * @param string|null $mimeType MIME type (mặc định image/jpeg)
     * @return array Kết quả trích xuất có cấu trúc JSON
     */
    public function analyzePrescriptionImage(string $imagePathOrBase64, ?string $mimeType = 'image/jpeg'): array
    {
        $base64Data = $this->prepareImageBase64($imagePathOrBase64, $mimeType);

        $systemInstruction = <<<SYS
Bạn là Chuyên gia Y tế & Bác sĩ Lâm sàng AI MedCabinet (Việt Nam), chuyên gia đọc toa thuốc viết tay, đơn thuốc điện tử và vỏ hộp thuốc.
Nhiệm vụ của bạn:
1. Đọc kỹ hình ảnh đơn thuốc hoặc vỏ bao bì dược phẩm được cung cấp.
2. Hiểu rõ các ký hiệu viết tắt y khoa tại Việt Nam:
   - "u." / "c/u": uống / cữ uống
   - "1v x 2l/ng s-c": Uống 1 viên x 2 lần/ngày (Sáng 1 viên, Chiều 1 viên)
   - "p.c" / "sau ăn": Uống sau khi ăn no
   - "a.c" / "trước ăn": Uống trước khi ăn 30-60 phút
   - "h.s": Uống trước khi đi ngủ
   - "p.r.n": Uống khi cần (khi sốt >= 38.5 độ hoặc đau nhiều)
   - "g." / "v." / "ống": gói / viên / ống
3. Chuẩn hóa tên Biệt dược (Brand name) và Hoạt chất chính (Active ingredient) theo Dược thư Quốc gia Việt Nam.
4. Trích xuất chính xác liều lượng, số lượng và tần suất.
5. Chỉ trả về JSON thuần chuẩn theo schema, tuyệt đối không kèm markdown block ```json hay lời mở đầu/kết bài.
SYS;

        $prompt = <<<PROMPT
Hãy phân tích hình ảnh đính kèm (toa thuốc bác sĩ hoặc vỏ thuốc). Trích xuất toàn bộ dữ liệu chính xác theo cấu trúc JSON sau:
{
  "hospital_doctor": "Tên bệnh viện hoặc phòng khám, bác sĩ kê đơn (nếu có)",
  "diagnosis": "Chẩn đoán bệnh (nếu có)",
  "date_prescribed": "YYYY-MM-DD (nếu đọc được)",
  "medicines": [
    {
      "name": "Tên thương mại của thuốc (VD: Panadol Extra, Augmentin 1g)",
      "active_ingredient": "Hoạt chất chính (VD: Paracetamol 500mg, Caffeine 65mg)",
      "concentration": "Hàm lượng (vd: 500mg, 1000mg, 10ml)",
      "dosage": "Liều dùng mỗi lần (vd: 1 viên, 2 gói)",
      "frequency": "Tần suất uống (vd: 2 lần/ngày, sáng - tối sau ăn)",
      "instructions": "Cách dùng chi tiết (vd: uống sau ăn 30 phút, uống với nhiều nước lọc)",
      "total_prescribed_quantity": 10,
      "unit": "viên/gói/chai/hộp",
      "expiry_date": "YYYY-MM-DD (nếu là vỏ thuốc đọc được HSD)"
    }
  ],
  "plain_language_summary": "Tóm tắt ngắn gọn đơn thuốc bằng ngôn ngữ dễ hiểu, thân thiện cho người bệnh",
  "precautions": [
    "Cảnh báo hoặc lưu ý đặc biệt khi dùng thuốc này (uống trọn liều, không bẻ viên, kiêng cữ...)"
  ]
}
PROMPT;

        if (empty($this->apiKey)) {
            Log::warning('Gemini API key is not configured. Returning fallback mock response.');
            return $this->getMockPrescriptionAnalysis();
        }

        try {
            $payload = [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemInstruction],
                    ],
                ],
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Data,
                                ],
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json',
                ],
            ];

            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", $payload);

            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text');
                return $this->cleanAndParseJson($rawText);
            }

            Log::error('Gemini OCR API request failed', ['status' => $response->status(), 'body' => $response->body()]);
            return $this->getMockPrescriptionAnalysis();
        } catch (Exception $e) {
            Log::error('Gemini OCR Exception: ' . $e->getMessage());
            return $this->getMockPrescriptionAnalysis();
        }
    }

    /**
     * Gemini Drug Interaction Safety Checker - Kiểm tra tương tác thuốc, dị ứng & quá liều hoạt chất
     *
     * @param array $medicines Danh sách thuốc đang dùng (mỗi phần tử có tên, hoạt chất, liều lượng)
     * @param array $patientHealthProfile Thông tin sức khỏe bệnh nhân (tuổi, bệnh nền, tiền sử dị ứng)
     * @return array Kết quả kiểm tra tương tác và mức độ rủi ro
     */
    public function checkDrugInteractions(array $medicines, array $patientHealthProfile = []): array
    {
        $medicinesJson = json_encode($medicines, JSON_UNESCAPED_UNICODE);
        $patientJson = json_encode($patientHealthProfile, JSON_UNESCAPED_UNICODE);

        $systemInstruction = <<<SYS
Bạn là Dược sĩ Lâm sàng Trưởng MedCabinet (Việt Nam), chuyên gia hàng đầu về an toàn dược lý, tương tác thuốc và phòng ngừa biến cố bất lợi của thuốc (ADR).
Bạn tuân thủ nghiêm ngặt Dược thư Quốc gia Việt Nam, Stockley's Drug Interactions và các hướng dẫn của Bộ Y tế Việt Nam.

Nhiệm vụ kiểm định 4 trục an toàn:
1. TRÙNG LẶP HOẠT CHẤT (Duplicate Active Ingredient):
   - Nguy cơ ngộ độc gan cấp tính do dùng đồng thời nhiều chế phẩm chứa Paracetamol (Panadol, Hapacol, Efferalgan, Decolgen, Tiffy, Coldacmin, Ultracet, Pamin...). Tổng liều Paracetamol người lớn tối đa 4000mg/ngày.
   - Nguy cơ loét xuất huyết tiêu hóa do dùng đồng thời 2 thuốc kháng viêm không steroid NSAID (Aspirin, Ibuprofen, Diclofenac, Meloxicam, Celecoxib...).
2. TƯƠNG TÁC THUỐC - THUỐC (Drug-Drug Interaction):
   - Kháng sinh Quinolone (Ciprofloxacin, Levofloxacin) hoặc Tetracycline phối hợp Antacid/Sắt/Canxi làm mất tác dụng kháng sinh.
   - Thuốc huyết áp nhóm ACEI/ARB (Enalapril, Losartan) phối hợp thuốc lợi tiểu giữ Kali hoặc viên bổ sung Kali gây tăng Kali máu nguy hiểm.
   - Kháng đông (Warfarin) phối hợp NSAID làm tăng nguy cơ xuất huyết ồ ạt.
   - Clarithromycin/Erythromycin dùng cùng Statin (Simvastatin, Atorvastatin) gây tiêu cơ vân cấp.
3. CHỐNG CHỈ ĐỊNH DỊ ỨNG (Allergy Conflict):
   - Bệnh nhân dị ứng Penicillin/Beta-lactam dùng Amoxicillin, Augmentin, Cephalexin.
   - Bệnh nhân dị ứng Aspirin/NSAID có nguy cơ co thắt phế quản cấp, sốc phản vệ.
4. BỆNH NỀN & ĐỘ TUỔI (Disease/Age Contraindications):
   - Bệnh nhân loét dạ dày tá tràng dùng NSAID.
   - Bệnh nhân tăng huyết áp dùng thuốc hạ sốt cảm cúm có chứa Pseudoephedrine/Phenylephrine (làm tăng vọt huyết áp).
   - Bệnh nhân suy thận dùng NSAID hoặc Metformin liều cao.
   - Trẻ em dưới 16 tuổi sốt siêu vi dùng Aspirin (nguy cơ Hội chứng Reye tử vong).

Yêu cầu xuất JSON thuần chuẩn, không kèm markdown.
SYS;

        $prompt = <<<PROMPT
Dữ liệu thuốc cần kiểm tra:
{$medicinesJson}

Hồ sơ sức khỏe người bệnh:
{$patientJson}

Trả về kết quả dưới cấu trúc JSON chuẩn:
{
  "has_conflict": true/false,
  "risk_level": "safe" / "warning" / "danger",
  "summary": "Tóm tắt đánh giá an toàn cho người bệnh trong 1-2 câu",
  "conflicts": [
    {
      "type": "duplicate_active_ingredient" / "drug_drug_interaction" / "allergy_conflict" / "disease_contraindication",
      "severity": "danger" / "warning" / "info",
      "medicines_involved": ["Thuốc A", "Thuốc B"],
      "active_ingredient": "Tên hoạt chất",
      "description": "Giải thích chi tiết nguy cơ y khoa",
      "recommendation": "Lời khuyên xử trí dứt khoát, an toàn"
    }
  ],
  "recommendations": "Lời khuyên tổng thể dặn dò bệnh nhân và người chăm sóc"
}
PROMPT;

        if (empty($this->apiKey)) {
            Log::warning('Gemini API key is not configured. Evaluating drug interactions via rule engine.');
            return $this->evaluateLocalRuleInteractions($medicines, $patientHealthProfile);
        }

        try {
            $payload = [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemInstruction],
                    ],
                ],
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json',
                ],
            ];

            $response = Http::timeout(45)
                ->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", $payload);

            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text');
                return $this->cleanAndParseJson($rawText);
            }

            Log::error('Gemini Drug Interaction API error', ['status' => $response->status(), 'body' => $response->body()]);
            return $this->evaluateLocalRuleInteractions($medicines, $patientHealthProfile);
        } catch (Exception $e) {
            Log::error('Gemini Drug Interaction Exception: ' . $e->getMessage());
            return $this->evaluateLocalRuleInteractions($medicines, $patientHealthProfile);
        }
    }

    /**
     * Plain-Language Medical Explainer - Trợ lý giải thích đơn thuốc bình dân dễ hiểu
     *
     * @param array $prescriptionData Thông tin đơn thuốc
     * @param array $patientHealthProfile Hồ sơ bệnh nhân
     * @return array Lời dặn dò chi tiết, thời điểm uống thuốc, kiêng khem
     */
    public function explainPrescription(array $prescriptionData, array $patientHealthProfile = []): array
    {
        $prescriptionJson = json_encode($prescriptionData, JSON_UNESCAPED_UNICODE);
        $patientJson = json_encode($patientHealthProfile, JSON_UNESCAPED_UNICODE);

        $systemInstruction = <<<SYS
Bạn là Bác sĩ Gia đình Trưởng MedCabinet. Hãy giải thích đơn thuốc cho bệnh nhân và người nhà bằng ngôn ngữ bình dân, ấm áp, thấu cảm, tránh thuật ngữ y khoa khó hiểu.
Cung cấp hướng dẫn dùng thuốc theo nguyên tắc:
1. Giải thích rõ từng thuốc dùng để làm gì (diệt vi khuẩn, hạ nhiệt giảm đau, bảo vệ niêm mạc bao tử...).
2. Thời điểm uống thuốc chính xác: uống trước ăn 30 phút, ngay sau ăn no, hay trước khi ngủ.
3. Tư thế uống thuốc: đứng hoặc ngồi thẳng lưng, uống cùng 1 cốc nước lọc đầy (150-200ml), không nằm ngay sau khi uống để tránh viên thuốc dính loét thực quản.
4. Tuyệt đối không bẻ đôi hoặc nghiền nát viên nén bao phim, viên giải phóng kéo dài (kèm ký hiệu XR, ER, CR, MR).
5. Kiêng cữ thực phẩm: kiêng rượu bia, không uống cùng nước trà, sữa hoặc nước ép bưởi chùm.
6. Cảnh báo rõ ràng các dấu hiệu nguy hiểm (Cờ đỏ) cần quay lại viện ngay.
Chỉ trả về JSON thuần chuẩn theo schema.
SYS;

        $prompt = <<<PROMPT
Đơn thuốc cần giải thích:
{$prescriptionJson}

Hồ sơ người bệnh:
{$patientJson}

Trả về JSON thuần:
{
  "overview": "Mục đích tổng thể của đợt điều trị này",
  "guidelines_per_medicine": [
    {
      "medicine_name": "Tên thuốc",
      "purpose": "Thuốc này dùng để chữa trị triệu chứng gì",
      "meal_timing": "Uống lúc nào (trước ăn 30p, ngay sau ăn no, lúc đói...)",
      "special_instructions": "Lưu ý quan trọng khi uống (uống nhiều nước, tư thế uống...)"
    }
  ],
  "dietary_precautions": [
    "Những thức ăn, đồ uống cần kiêng cữ trong đợt điều trị"
  ],
  "common_side_effects": [
    {
      "symptom": "Tác dụng phụ thường gặp (nhẹ, có thể gặp)",
      "is_normal": true,
      "how_to_handle": "Cách xử trí an toàn tại nhà"
    }
  ],
  "when_to_see_doctor": [
    "Dấu hiệu cảnh báo nguy hiểm (Cờ đỏ) cần đến cơ sở y tế tái khám ngay"
  ]
}
PROMPT;

        if (empty($this->apiKey)) {
            return $this->getMockPrescriptionExplanation($prescriptionData);
        }

        try {
            $payload = [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemInstruction],
                    ],
                ],
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                ],
            ];

            $response = Http::timeout(45)
                ->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", $payload);

            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text');
                return $this->cleanAndParseJson($rawText);
            }

            return $this->getMockPrescriptionExplanation($prescriptionData);
        } catch (Exception $e) {
            Log::error('Gemini Explainer Exception: ' . $e->getMessage());
            return $this->getMockPrescriptionExplanation($prescriptionData);
        }
    }

    /**
     * Tư vấn y tế gia đình AI qua trò chuyện (Medical AI Chat Assistant)
     *
     * @param string $question Câu hỏi của người dùng
     * @param array $familyContext Ngữ cảnh tủ thuốc và các thành viên gia đình
     * @return array Kết quả câu trả lời và các lưu ý an toàn
     */
    public function askMedicalAssistant(string $question, array $familyContext = []): array
    {
        $contextJson = json_encode($familyContext, JSON_UNESCAPED_UNICODE);

        $systemInstruction = <<<SYS
Bạn là Dược sĩ Lâm sàng và Bác sĩ Gia đình AI MedCabinet (Việt Nam) - thông thái, ân cần, đáng tin cậy và tận tâm.
Nhiệm vụ của bạn là tư vấn chăm sóc sức khỏe gia đình, hướng dẫn dùng thuốc an toàn, kiểm tra tủ thuốc và giải đáp các câu hỏi y tế thường thức.

QUY TẮC VÀNG VỀ AN TOÀN Y TẾ:
1. Nhận thức sâu sắc về Tủ thuốc gia đình:
   - Nếu trong ngữ cảnh có danh sách thuốc trong tủ (`cabinet_medicines`), hãy chủ động đối chiếu xem loại thuốc người dùng hỏi có sẵn trong tủ thuốc gia đình họ hay không để đưa ra chỉ dẫn cụ thể.
2. Cá nhân hóa theo Người bệnh (`target_member`):
   - Chú ý độ tuổi (trẻ sơ sinh, trẻ nhỏ, người trưởng thành, phụ nữ có thai/cho con bú, người cao tuổi).
   - Kiểm tra tiền sử dị ứng và bệnh nền (tăng huyết áp, tiểu đường, hen suyễn, bệnh gan thận) để đưa ra lời khuyên phù hợp, không gây tương tác nguy hiểm.
3. Hướng dẫn liều lượng chuẩn xác:
   - Với Paracetamol: Người lớn 500-650mg/lần, cách nhau 4-6 giờ, tối đa 4000mg/ngày. Trẻ em: 10-15mg/kg/lần.
   - Với Kháng sinh: Nhấn mạnh phải dùng đủ số ngày bác sĩ kê, không được tự ý dừng thuốc khi vừa thấy đỡ.
   - Nhắc nhở uống thuốc với nước lọc ấm (150-200ml), không uống với trà đặc, sữa, rượu bia hay nước ngọt có ga.
4. Nhận diện Dấu hiệu Cờ đỏ (Red Flags):
   - Khi có triệu chứng nặng (sốt cao co giật, khó thở, nôn ra máu, phát ban phù mặt cấp, đau ngực thắt), hướng dẫn gọi cấp cứu 115 hoặc đi viện ngay lập tức.
5. Cấu trúc phản hồi: Trả về JSON thuần chuẩn theo schema được yêu cầu.
SYS;

        $prompt = <<<PROMPT
Câu hỏi từ người dùng:
"{$question}"

Ngữ cảnh hồ sơ sức khỏe & danh mục tủ thuốc gia đình:
{$contextJson}

Hãy trả lời dưới định dạng JSON thuần:
{
  "answer": "Câu trả lời chuyên môn chi tiết, ân cần, ấm áp và dễ hiểu của bạn",
  "cabinet_match": "Nếu có thuốc liên quan trong tủ thuốc gia đình thì chỉ rõ tên và lưu ý, nếu không có thì để trống",
  "key_points": [
    "Ý cốt lõi cần nhớ 1",
    "Ý cốt lõi cần nhớ 2",
    "Ý cốt lõi cần nhớ 3"
  ],
  "safety_warning": "Cảnh báo an toàn y tế tối quan trọng",
  "suggested_actions": [
    "Hành động nên làm 1",
    "Hành động nên làm 2"
  ]
}
PROMPT;

        if (empty($this->apiKey)) {
            return $this->getLocalRuleChatResponse($question, $familyContext);
        }

        try {
            $payload = [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemInstruction],
                    ],
                ],
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.25,
                    'responseMimeType' => 'application/json',
                ],
            ];

            $response = Http::timeout(45)
                ->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", $payload);

            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text');
                return $this->cleanAndParseJson($rawText);
            }

            Log::error('Gemini Chat API error', ['status' => $response->status(), 'body' => $response->body()]);
            return $this->getLocalRuleChatResponse($question, $familyContext);
        } catch (Exception $e) {
            Log::error('Gemini Chat Exception: ' . $e->getMessage());
            return $this->getLocalRuleChatResponse($question, $familyContext);
        }
    }

    /**
     * Phản hồi thông minh nội bộ qua Bộ quy tắc Lâm sàng (Clinical Expert Knowledge Engine)
     */
    protected function getLocalRuleChatResponse(string $question, array $familyContext = []): array
    {
        $q = mb_strtolower($question, 'UTF-8');
        $cabinetMeds = $familyContext['cabinet_medicines'] ?? [];
        $targetMember = $familyContext['target_member'] ?? null;

        // 1. Quên liều thuốc
        if (str_contains($q, 'quên') && (str_contains($q, 'liều') || str_contains($q, 'cữ') || str_contains($q, 'uống'))) {
            return [
                'answer' => 'Khi quên uống 1 liều thuốc: Hãy uống ngay khi nhớ ra nếu thời điểm nhớ ra còn xa liều kế tiếp. Nếu đã gần đến giờ uống liều tiếp theo (dưới 2-3 tiếng), hãy bỏ qua liều đã quên và tiếp tục uống liều tiếp theo đúng theo lịch trình quy định. TUYỆT ĐỐI KHÔNG UỐNG GẤP ĐÔI LIỀU để bù liều đã quên vì có thể gây ngộ độc hoạt chất hoặc tụt huyết áp/hạ đường huyết đột ngột.',
                'cabinet_match' => !empty($cabinetMeds) ? 'Vui lòng kiểm tra lại đơn thuốc và số lượng thuốc còn trong tủ thuốc của bạn.' : '',
                'key_points' => [
                    'Uống ngay liều quên nếu còn cách xa giờ uống liều kế tiếp',
                    'Bỏ qua liều quên nếu đã gần đến giờ của liều tiếp theo',
                    'Tuyệt đối không uống dồn 2 liều cùng lúc để bù',
                ],
                'safety_warning' => 'Đặc biệt cẩn trọng đối với thuốc huyết áp, tim mạch, thuốc tiểu đường và kháng sinh: việc uống gấp đôi liều có thể gây biến chứng cấp cứu!',
                'suggested_actions' => [
                    'Cài đặt nhắc nhở lịch uống thuốc trên ứng dụng MedCabinet',
                    'Đánh dấu vào sổ nhật ký liều thuốc đã uống',
                ],
            ];
        }

        // 2. Hạ sốt, Paracetamol, liều lượng theo cân nặng
        if (str_contains($q, 'sốt') || str_contains($q, 'paracetamol') || str_contains($q, 'hạ sốt') || str_contains($q, 'panadol') || str_contains($q, 'hapacol')) {
            $isChild = $targetMember && ($targetMember['age'] ?? 99) < 12;
            $childAdvice = $isChild ? 'Vì đang tư vấn cho trẻ em: Liều Paracetamol chuẩn xác là 10 - 15mg/kg cân nặng mỗi lần, khoảng cách tối thiểu 4 - 6 tiếng giữa 2 lần uống (tối đa 4-5 lần/24h).' : 'Liều chuẩn cho người lớn là 500mg - 650mg/lần, khoảng cách tối thiểu từ 4 đến 6 tiếng giữa 2 lần uống. Không uống quá 4000mg/ngày.';

            $cabinetParacetamol = array_filter($cabinetMeds, fn ($m) => stripos($m, 'paracetamol') !== false || stripos($m, 'panadol') !== false || stripos($m, 'hapacol') !== false || stripos($m, 'efferalgan') !== false);
            $matchText = !empty($cabinetParacetamol) ? 'Tủ thuốc nhà bạn hiện có: ' . implode(', ', $cabinetParacetamol) : '';

            return [
                'answer' => "Hướng dẫn dùng thuốc hạ sốt an toàn: Paracetamol là hoạt chất đầu tay an toàn và hiệu quả nhất. {$childAdvice} Khi sốt từ 38.5°C trở lên mới cần dùng thuốc. Kết hợp mặc quần áo thoáng mát, uống nhiều nước ấm hoặc bù dung dịch điện giải Oresol pha đúng tỷ lệ.",
                'cabinet_match' => $matchText,
                'key_points' => [
                    'Chỉ uống thuốc khi nhiệt độ cơ thể từ 38.5°C trở lên',
                    'Khoảng cách giữa 2 lần uống bắt buộc từ 4 - 6 tiếng',
                    'Không uống cùng lúc nhiều loại thuốc chứa Paracetamol (tránh ngộ độc gan)',
                    'Uống đủ nước lọc ấm và nước bù điện giải Oresol',
                ],
                'safety_warning' => 'CẢNH BÁO QUÁ LIỀU: Rất nhiều thuốc cảm cúm (Decolgen, Tiffy, Coldacmin) đều có sẵn Paracetamol. Uống trùng lặp sẽ gây hoại tử tế bào gan cấp tính!',
                'suggested_actions' => [
                    'Đo lại nhiệt độ sau khi uống thuốc 45 - 60 phút',
                    'Nếu sốt trên 39.5°C không hạ sau 2 liều hoặc sốt kéo dài trên 3 ngày, cần đến bệnh viện ngay',
                ],
            ];
        }

        // 3. Kháng sinh & Men vi sinh
        if (str_contains($q, 'kháng sinh') || str_contains($q, 'augmentin') || str_contains($q, 'amoxicillin') || str_contains($q, 'cefixime') || str_contains($q, 'men vi sinh')) {
            return [
                'answer' => 'Nguyên tắc vàng khi dùng thuốc Kháng sinh: 1. Kháng sinh chỉ tiêu diệt vi khuẩn, KHÔNG có tác dụng trên virus (cảm cúm, sốt siêu vi thông thường). 2. Phải uống ĐỦ LIỀU và ĐỦ SỐ NGÀY được bác sĩ kê (thường 5 - 7 ngày), tuyệt đối không tự ý ngừng thuốc khi vừa thấy bớt bệnh để tránh tạo ra vi khuẩn kháng thuốc. 3. Nếu có dùng thêm men vi sinh (probiotic), phải uống cách cữ kháng sinh ít nhất 2 tiếng để men không bị kháng sinh tiêu diệt.',
                'cabinet_match' => !empty($cabinetMeds) ? 'Kiểm tra thuốc kháng sinh trong tủ để đảm bảo đúng hạn sử dụng.' : '',
                'key_points' => [
                    'Uống trọn vẹn số ngày quy định trong đơn, không bỏ dở giữa chừng',
                    'Uống men vi sinh cách xa kháng sinh ít nhất 2 giờ đồng hồ',
                    'Uống cùng nhiều nước lọc để bảo vệ chức năng thận',
                ],
                'safety_warning' => 'Nếu xuất hiện dị ứng: nổi mề đay, ngứa đỏ, phù mi mắt hoặc khó thở sau khi uống kháng sinh, hãy NGỪNG THUỐC NGAY và đến cấp cứu!',
                'suggested_actions' => [
                    'Ghi chú giờ uống cố định hàng ngày để duy trì nồng độ kháng sinh ổn định trong máu',
                ],
            ];
        }

        // 4. Bẻ viên thuốc / Nghiền nát thuốc
        if (str_contains($q, 'bẻ') || str_contains($q, 'nghiền') || str_contains($q, 'nhai') || str_contains($q, 'viên sủi')) {
            return [
                'answer' => 'Quy tắc bẻ và nghiền thuốc: 1. Chỉ được bẻ những viên thuốc có RÃNH KHẮC ở giữa (như viên nén thông thường). 2. TUYỆT ĐỐI KHÔNG BẺ, KHÔNG NGHIỀN các dạng thuốc: Viên nén bao tan trong ruột (để bảo vệ dạ dày), Viên phóng thích kéo dài (có đuôi XR, ER, CR, MR, LA, Retard) vì nghiền ra sẽ giải phóng toàn bộ hoạt chất 24h cùng một lúc, gây ngộ độc cấp tính. 3. Viên nang (nhộng) không được mở vỏ bọc ra trừ khi có chỉ định bác sĩ. 4. Viên sủi phải hòa tan hoàn toàn trong nước mới được uống, không nuốt trực tiếp.',
                'cabinet_match' => '',
                'key_points' => [
                    'Chỉ bẻ viên khi có vạch chia rãnh sẵn của nhà sản xuất',
                    'Không bao giờ nghiền viên bao tan trong ruột hoặc phóng thích kéo dài (XR/ER)',
                    'Viên sủi bắt buộc hòa tan hết bọt khí trước khi uống',
                ],
                'safety_warning' => 'Viên sủi chứa hàm lượng muối Natri cao, người bị tăng huyết áp hoặc suy thận cần hạn chế sử dụng.',
                'suggested_actions' => [
                    'Hỏi dược sĩ trước nếu người bệnh (trẻ nhỏ/người già) khó nuốt viên thuốc to',
                ],
            ];
        }

        // 5. Thời điểm uống trước ăn hay sau ăn / Uống cùng đồ uống gì
        if (str_contains($q, 'trước ăn') || str_contains($q, 'sau ăn') || str_contains($q, 'sữa') || str_contains($q, 'nước ngọt') || str_contains($q, 'trà')) {
            return [
                'answer' => 'Hướng dẫn thời điểm uống thuốc và đồ uống chuẩn y tế: 1. Thuốc uống trước ăn (30-60 phút): Nhóm thuốc bảo vệ dạ dày (Omeprazole, Esomeprazole), thuốc trị loãng xương hoặc thuốc cần hấp thu khi đói. 2. Thuốc uống ngay sau ăn no: Thuốc giảm đau hạ sốt (Paracetamol, Ibuprofen), kháng sinh, vitamin tan trong dầu (A, D, E, K). 3. ĐỒ UỐNG CHUẨN: Chỉ uống thuốc bằng NƯỚC LỌC ĐUN SÔI ĐỂ NGUỘI HOẶC NƯỚC ẤM (khoảng 150-200ml). Không uống thuốc với sữa (canxi gắn kết làm mất tác dụng kháng sinh), không uống với nước trà đặc, nước ngọt có ga hoặc rượu bia.',
                'cabinet_match' => '',
                'key_points' => [
                    'Trước ăn 30-60p: Thuốc bao tử, thuốc hấp thu tốt lúc đói',
                    'Sau ăn no: Kháng sinh, thuốc giảm đau kháng viêm để tránh kích ứng dạ dày',
                    'Chỉ uống cùng nước lọc ấm, tối thiểu 150-200ml',
                ],
                'safety_warning' => 'Uống thuốc cùng rượu bia có thể gây độc gan cấp (với Paracetamol) hoặc gây tụt huyết áp, xuất huyết dạ dày nghiêm trọng.',
                'suggested_actions' => [
                    'Kiểm tra lại hướng dẫn sử dụng trong chi tiết từng thuốc trên ứng dụng MedCabinet',
                ],
            ];
        }

        // 6. Bảo quản thuốc & Nhiệt độ
        if (str_contains($q, 'bảo quản') || str_contains($q, 'nhiệt độ') || str_contains($q, 'ngăn mát') || str_contains($q, 'hạn dùng') || str_contains($q, 'mở nắp')) {
            return [
                'answer' => 'Quy chuẩn bảo quản tủ thuốc gia đình: 1. Nhiệt độ phòng chuẩn: Dưới 30°C, nơi khô ráo, tránh ánh nắng chiếu trực tiếp, tránh ẩm ướt (không để tủ thuốc trong phòng tắm). 2. Ngăn mát tủ lạnh (2 - 8°C): Chỉ để các thuốc đặc thù như Insulin chưa mở nắp, một số men vi sinh dạng nước, thuốc đạn hạ sốt nhét hậu môn. 3. Thuốc sau khi mở nắp: Thuốc nhỏ mắt chỉ dùng trong 15 - 30 ngày sau mở nắp; Siro hạ sốt/ho cho trẻ chỉ nên dùng trong vòng 1 - 3 tháng sau mở nắp. 4. Luôn đặt tủ thuốc trên cao ngoài tầm với của trẻ nhỏ.',
                'cabinet_match' => !empty($cabinetMeds) ? 'Hãy thường xuyên bấm nút "Quét cảnh báo" trên ứng dụng để kiểm tra thuốc hết hạn.' : '',
                'key_points' => [
                    'Nơi khô mát < 30°C, tránh ánh nắng và độ ẩm phòng tắm',
                    'Ghi ngày mở nắp lên vỏ chai siro và lọ thuốc nhỏ mắt',
                    'Bỏ ngay thuốc bị đổi màu, chảy nước, mốc hoặc biến dạng',
                ],
                'safety_warning' => 'Thuốc nhỏ mắt quá hạn sau mở nắp có thể nhiễm khuẩn nặng gây viêm loét giác mạc!',
                'suggested_actions' => [
                    'Rà soát tủ thuốc gia đình định kỳ mỗi tháng một lần',
                ],
            ];
        }

        // 7. Thuốc Huyết áp & Tim mạch
        if (str_contains($q, 'huyết áp') || str_contains($q, 'tim mạch') || str_contains($q, 'amlodipine') || str_contains($q, 'losartan')) {
            return [
                'answer' => 'Lưu ý sống còn với bệnh Tăng huyết áp: Thuốc huyết áp phải được uống ĐỀU ĐẶN HÀNG NGÀY vào một giờ cố định (thường vào buổi sáng sau ăn), ngay cả khi đo thấy huyết áp đã về mức bình thường. TUYỆT ĐỐI KHÔNG TỰ Ý NGỪNG THUỐC vì sẽ gây hiện tượng tăng vọt huyết áp dội ngược, dẫn đến nguy cơ đột quỵ tai biến mạch máu não hoặc nhồi máu cơ tim.',
                'cabinet_match' => !empty($cabinetMeds) ? 'Đảm bảo trong tủ thuốc luôn có sẵn thuốc huyết áp đủ cho cả tháng, không để bị hết thuốc đột xuất.' : '',
                'key_points' => [
                    'Uống cố định vào một khung giờ mỗi sáng',
                    'Không tự ý ngừng thuốc khi thấy huyết áp đã ổn định',
                    'Đo và ghi nhật ký huyết áp tại nhà 2 lần/ngày',
                ],
                'safety_warning' => 'Nếu huyết áp đo trên 180/120 mmHg kèm đau đầu dữ dội, mờ mắt hoặc yếu liệt nửa người, phải gọi cấp cứu 115 ngay!',
                'suggested_actions' => [
                    'Tạo lịch uống thuốc định kỳ hàng ngày trên ứng dụng MedCabinet',
                ],
            ];
        }

        // Mặc định phản hồi y tế tổng quát chất lượng cao
        return [
            'answer' => 'Chào bạn! Về câu hỏi y tế: "' . $question . '". Là Trợ lý Dược sĩ & Bác sĩ Gia đình AI MedCabinet, tôi khuyến nghị bạn luôn kiểm tra kỹ tên thuốc, nồng độ, hạn sử dụng và đọc kỹ đơn thuốc trước khi dùng. Hãy đảm bảo uống thuốc đúng liều lượng, đúng thời điểm và duy trì nếp sinh hoạt lành mạnh.',
            'cabinet_match' => !empty($cabinetMeds) ? 'Tủ thuốc gia đình bạn hiện có ' . count($cabinetMeds) . ' loại thuốc đang lưu trữ. Hãy tra cứu kỹ trước khi phối hợp thuốc mới.' : '',
            'key_points' => [
                'Uống đúng liều lượng và thời gian được bác sĩ chỉ định',
                'Uống cùng 1 ly nước lọc ấm đầy (khoảng 200ml)',
                'Theo dõi các phản ứng khác thường của cơ thể sau khi dùng thuốc',
            ],
            'safety_warning' => 'Nếu người bệnh có biểu hiện cấp tính: sốt cao liên tục, khó thở, co giật, đau ngực hoặc nổi mề đay dị ứng, hãy đưa người bệnh đến bệnh viện cấp cứu gần nhất.',
            'suggested_actions' => [
                'Kiểm tra lại tủ thuốc gia đình trên MedCabinet',
                'Hỏi ý kiến bác sĩ điều trị trước khi thay đổi bất kỳ liều dùng nào',
            ],
        ];
    }

    /**
     * Chuyển đổi file ảnh sang chuỗi Base64
     */
    protected function prepareImageBase64(string $imagePathOrBase64, ?string &$mimeType): string
    {
        if (str_starts_with($imagePathOrBase64, 'data:image/')) {
            if (preg_match('/^data:(image\/[a-zA-Z0-9]+);base64,(.+)$/', $imagePathOrBase64, $matches)) {
                $mimeType = $matches[1];
                return $matches[2];
            }
        }

        if (file_exists($imagePathOrBase64)) {
            $fileContent = file_get_contents($imagePathOrBase64);
            $detectedMime = mime_content_type($imagePathOrBase64);
            if ($detectedMime) {
                $mimeType = $detectedMime;
            }
            return base64_encode($fileContent);
        }

        return $imagePathOrBase64;
    }

    /**
     * Dọn dẹp phản hồi JSON từ AI (loại bỏ markdown block nếu có)
     */
    protected function cleanAndParseJson(string $text): array
    {
        $text = trim($text);
        if (str_starts_with($text, '```json')) {
            $text = preg_replace('/^```json\s*/', '', $text);
            $text = preg_replace('/\s*```$/', '', $text);
        } elseif (str_starts_with($text, '```')) {
            $text = preg_replace('/^```\s*/', '', $text);
            $text = preg_replace('/\s*```$/', '', $text);
        }

        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : ['raw_text' => $text];
    }

    /**
     * Thuật toán Rule-engine kiểm tra tương tác thuốc nội bộ khi offline hoặc chưa nhập API Key
     */
    protected function evaluateLocalRuleInteractions(array $medicines, array $patientHealthProfile): array
    {
        $conflicts = [];

        // 1. Kiểm tra trùng lặp hoạt chất Paracetamol
        $paracetamolMeds = [];
        foreach ($medicines as $item) {
            $ing = strtolower($item['active_ingredient'] ?? '');
            $name = $item['name'] ?? 'Thuốc';
            $lowerName = strtolower($name);

            if (str_contains($ing, 'paracetamol') || 
                str_contains($lowerName, 'panadol') || 
                str_contains($lowerName, 'hapacol') || 
                str_contains($lowerName, 'efferalgan') || 
                str_contains($lowerName, 'decolgen') ||
                str_contains($lowerName, 'tiffy') ||
                str_contains($lowerName, 'coldacmin') ||
                str_contains($lowerName, 'ultracet')) {
                $paracetamolMeds[] = $name;
            }
        }

        if (count($paracetamolMeds) > 1) {
            $uniqueMeds = array_values(array_unique($paracetamolMeds));
            $conflicts[] = [
                'type' => 'duplicate_active_ingredient',
                'severity' => 'danger',
                'medicines_involved' => $uniqueMeds,
                'active_ingredient' => 'Paracetamol',
                'description' => 'CẢNH BÁO QUÁ LIỀU: Các thuốc ' . implode(' và ', $uniqueMeds) . ' đều chứa hoạt chất Paracetamol. Uống đồng thời có nguy cơ ngộ độc gan cấp tính, suy gan và hoại tử tế bào gan.',
                'recommendation' => 'Chỉ chọn uống duy nhất một loại thuốc hạ sốt giảm đau. Khoảng cách giữa 2 lần uống tối thiểu 4 - 6 tiếng, tổng liều không quá 4000mg/ngày cho người lớn.',
            ];
        }

        // 2. Kiểm tra trùng lặp thuốc kháng viêm NSAID kép (Aspirin, Ibuprofen, Diclofenac, Meloxicam)
        $nsaidMeds = [];
        foreach ($medicines as $item) {
            $ing = strtolower($item['active_ingredient'] ?? '');
            $name = strtolower($item['name'] ?? '');
            if (str_contains($ing, 'ibuprofen') || str_contains($name, 'ibuprofen') ||
                str_contains($ing, 'aspirin') || str_contains($name, 'aspirin') ||
                str_contains($ing, 'diclofenac') || str_contains($name, 'diclofenac') ||
                str_contains($ing, 'meloxicam') || str_contains($name, 'meloxicam')) {
                $nsaidMeds[] = $item['name'];
            }
        }
        if (count($nsaidMeds) > 1) {
            $uniqueNsaid = array_values(array_unique($nsaidMeds));
            $conflicts[] = [
                'type' => 'drug_drug_interaction',
                'severity' => 'danger',
                'medicines_involved' => $uniqueNsaid,
                'active_ingredient' => 'NSAID (Kháng viêm không steroid)',
                'description' => 'NGUY HIỂM XUẤT HUYẾT: Dùng phối hợp 2 thuốc kháng viêm NSAID (' . implode(' và ', $uniqueNsaid) . ') làm tăng vọt nguy cơ loét thủng dạ dày tá tràng và suy thận cấp.',
                'recommendation' => 'Chỉ sử dụng 1 loại NSAID theo đúng đơn của bác sĩ, uống ngay sau khi ăn no và có thuốc bảo vệ niêm mạc dạ dày đi kèm.',
            ];
        }

        // 3. Kiểm tra dị ứng thuốc theo hồ sơ bệnh nhân
        $allergies = strtolower($patientHealthProfile['allergies'] ?? '');
        if (!empty($allergies)) {
            foreach ($medicines as $item) {
                $medName = strtolower($item['name'] ?? '');
                $ingName = strtolower($item['active_ingredient'] ?? '');

                if ((str_contains($allergies, 'aspirin') || str_contains($allergies, 'nsaid')) &&
                    (str_contains($medName, 'aspirin') || str_contains($medName, 'ibuprofen') || str_contains($ingName, 'ibuprofen'))) {
                    $conflicts[] = [
                        'type' => 'allergy_conflict',
                        'severity' => 'danger',
                        'medicines_involved' => [$item['name']],
                        'active_ingredient' => $item['active_ingredient'] ?? '',
                        'description' => 'NGUY HIỂM DỊ ỨNG: Bệnh nhân có tiền sử dị ứng với NSAID/Aspirin, thuốc này có thể gây co thắt phế quản cấp, phù nề thanh quản hoặc sốc phản vệ đe dọa tính mạng.',
                        'recommendation' => 'Ngừng ngay thuốc này và liên hệ bác sĩ để đổi sang nhóm giảm đau an toàn hơn như Paracetamol đơn chất.',
                    ];
                }

                if (str_contains($allergies, 'penicillin') &&
                    (str_contains($medName, 'amoxicillin') || str_contains($medName, 'augmentin') || str_contains($ingName, 'amoxicillin') || str_contains($ingName, 'penicillin'))) {
                    $conflicts[] = [
                        'type' => 'allergy_conflict',
                        'severity' => 'danger',
                        'medicines_involved' => [$item['name']],
                        'active_ingredient' => $item['active_ingredient'] ?? 'Penicillin / Amoxicillin',
                        'description' => 'CẢNH BÁO DỊ ỨNG KHÁNG SINH: Người bệnh dị ứng nhóm Penicillin/Beta-lactam, thuốc này có nguy cơ cao gây sốc phản vệ, mề đay cấp hoặc hội chứng Stevens-Johnson.',
                        'recommendation' => 'Không được uống thuốc này. Báo ngay cho bác sĩ để đổi sang nhóm kháng sinh thay thế như Macrolid (Azithromycin, Clarithromycin).',
                    ];
                }
            }
        }

        $hasConflict = count($conflicts) > 0;
        $highestSeverity = 'safe';
        foreach ($conflicts as $c) {
            if ($c['severity'] === 'danger') {
                $highestSeverity = 'danger';
                break;
            } elseif ($c['severity'] === 'warning') {
                $highestSeverity = 'warning';
            }
        }

        return [
            'has_conflict' => $hasConflict,
            'risk_level' => $highestSeverity,
            'summary' => $hasConflict
                ? 'Phát hiện có cảnh báo nguy cơ tương tác thuốc cần lưu ý đặc biệt!'
                : 'Đơn thuốc hiện tại an toàn, không phát hiện xung đột hoạt chất hay chống chỉ định.',
            'conflicts' => $conflicts,
            'recommendations' => $hasConflict
                ? 'Hãy tham khảo ý kiến bác sĩ hoặc dược sĩ trước khi cho người bệnh uống đồng thời các loại thuốc trên.'
                : 'Uống thuốc đúng liều lượng và giờ giấc đã được chỉ định.',
        ];
    }

    /**
     * Dữ liệu giả lập mẫu cho tính năng Vision OCR
     */
    protected function getMockPrescriptionAnalysis(): array
    {
        return [
            'hospital_doctor' => 'Bệnh viện Bạch Mai - BS. Nguyễn Văn Tuấn',
            'diagnosis' => 'Viêm phế quản cấp tính / Sốt siêu vi',
            'date_prescribed' => date('Y-m-d'),
            'medicines' => [
                [
                    'name' => 'Augmentin 1g',
                    'active_ingredient' => 'Amoxicillin 875mg + Acid Clavulanic 125mg',
                    'concentration' => '1000mg',
                    'dosage' => '1 viên',
                    'frequency' => '2 lần/ngày (Sáng - Tối)',
                    'instructions' => 'Uống ngay đầu bữa ăn no với nhiều nước ấm',
                    'total_prescribed_quantity' => 14,
                    'unit' => 'viên',
                    'expiry_date' => '2027-08-15',
                ],
                [
                    'name' => 'Panadol Extra',
                    'active_ingredient' => 'Paracetamol 500mg, Caffeine 65mg',
                    'concentration' => '565mg',
                    'dosage' => '1 viên',
                    'frequency' => 'Khi sốt trên 38.5°C hoặc đau đầu nhiều',
                    'instructions' => 'Uống sau ăn no, khoảng cách giữa 2 lần tối thiểu 4 - 6 tiếng',
                    'total_prescribed_quantity' => 10,
                    'unit' => 'viên',
                    'expiry_date' => '2027-12-30',
                ],
                [
                    'name' => 'Acemuc 200mg',
                    'active_ingredient' => 'Acetylcysteine',
                    'concentration' => '200mg',
                    'dosage' => '1 gói',
                    'frequency' => '3 lần/ngày',
                    'instructions' => 'Hòa tan vào nửa cốc nước ấm uống sau bữa ăn',
                    'total_prescribed_quantity' => 15,
                    'unit' => 'gói',
                    'expiry_date' => '2027-05-20',
                ],
            ],
            'plain_language_summary' => 'Đơn thuốc điều trị đợt viêm đường hô hấp gồm 1 loại kháng sinh trị nhiễm khuẩn, 1 loại thuốc hạ sốt giảm đau khi cần và 1 loại thuốc làm loãng đờm dễ ho khạc.',
            'precautions' => [
                'Cần uống kháng sinh Augmentin đủ 7 ngày liên tục, không tự ý ngừng thuốc ngay cả khi đã hết sốt để tránh vi khuẩn kháng thuốc.',
                'Không uống thêm bất kỳ loại thuốc cảm cúm hay hạ sốt nào khác có chứa Paracetamol.',
            ],
        ];
    }

    /**
     * Dữ liệu giả lập mẫu cho tính năng Plain-Language Medical Explainer
     */
    protected function getMockPrescriptionExplanation(array $prescriptionData): array
    {
        return [
            'overview' => 'Đợt điều trị này nhằm kiểm soát nhanh tình trạng viêm nhiễm và hạ sốt an toàn cho người bệnh.',
            'guidelines_per_medicine' => [
                [
                    'medicine_name' => 'Augmentin 1g',
                    'purpose' => 'Kháng sinh tiêu diệt vi khuẩn gây viêm phế quản',
                    'meal_timing' => 'Uống ngay khi vừa bắt đầu ăn cơm',
                    'special_instructions' => 'Giúp giảm kích ứng dạ dày và hấp thu thuốc tốt nhất. Uống trọn vẹn 7 ngày.',
                ],
                [
                    'medicine_name' => 'Panadol Extra',
                    'purpose' => 'Hạ nhiệt khi sốt và làm dịu cơn đau nhức mình mẩy',
                    'meal_timing' => 'Uống sau khi ăn no',
                    'special_instructions' => 'Chỉ uống khi nhiệt độ cơ thể từ 38.5°C trở lên. Tránh dùng chung với cà phê đặc.',
                ],
            ],
            'dietary_precautions' => [
                'Uống nhiều nước ấm (1.5 - 2 lít/ngày) để giúp thuốc tan đờm phát huy tác dụng tối đa.',
                'Kiêng tuyệt đối rượu bia và đồ uống có cồn trong suốt thời gian dùng thuốc.',
                'Hạn chế đồ ăn quá cay nóng hoặc nước đá lạnh làm kích thích cổ họng.',
            ],
            'common_side_effects' => [
                [
                    'symptom' => 'Đi ngoài phân hơi lỏng hoặc khó chịu bụng nhẹ do kháng sinh',
                    'is_normal' => true,
                    'how_to_handle' => 'Bổ sung thêm men vi sinh hoặc sữa chua cách cữ uống kháng sinh 2 tiếng.',
                ],
            ],
            'when_to_see_doctor' => [
                'Sốt cao liên tục trên 39.5°C không hạ sau khi uống thuốc 2 tiếng.',
                'Khó thở, thở rít hoặc nổi mề đay, sưng mí mắt, môi (dấu hiệu dị ứng thuốc cấp tính).',
            ],
        ];
    }
}
