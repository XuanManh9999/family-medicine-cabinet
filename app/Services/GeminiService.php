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

        $prompt = <<<PROMPT
Bạn là chuyên gia y tế AI hỗ trợ đọc đơn thuốc và vỏ thuốc tại Việt Nam.
Hãy phân tích hình ảnh đính kèm (là toa thuốc viết tay/in của bác sĩ hoặc bao bì vỏ hộp thuốc).
Trích xuất toàn bộ thông tin chính xác theo cấu trúc JSON chuẩn dưới đây (chỉ trả về JSON thuần, không kèm markdown hay giải thích thêm):
{
  "hospital_doctor": "Tên bệnh viện hoặc phòng khám, bác sĩ kê đơn (nếu có)",
  "diagnosis": "Chẩn đoán bệnh (nếu có)",
  "date_prescribed": "YYYY-MM-DD (nếu đọc được)",
  "medicines": [
    {
      "name": "Tên thương mại của thuốc",
      "active_ingredient": "Hoạt chất chính (nếu có)",
      "concentration": "Hàm lượng (vd: 500mg, 10ml)",
      "dosage": "Liều dùng mỗi lần (vd: 1 viên, 2 gói)",
      "frequency": "Tần suất uống (vd: 2 lần/ngày, sáng - tối)",
      "instructions": "Cách dùng chi tiết (vd: uống sau ăn 30 phút, uống nhiều nước)",
      "total_prescribed_quantity": 10,
      "unit": "viên/gói/chai/hộp",
      "expiry_date": "YYYY-MM-DD (nếu là vỏ thuốc đọc được HSD)"
    }
  ],
  "plain_language_summary": "Tóm tắt ngắn gọn đơn thuốc bằng ngôn ngữ dễ hiểu cho người bệnh",
  "precautions": [
    "Cảnh báo hoặc lưu ý đặc biệt khi dùng thuốc này"
  ]
}
PROMPT;

        if (empty($this->apiKey)) {
            Log::warning('Gemini API key is not configured. Returning fallback mock response.');
            return $this->getMockPrescriptionAnalysis();
        }

        try {
            $payload = [
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
                    'temperature' => 0.2,
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
     * Gemini Drug Interaction Safety Checker - Kiểm tra tương tác thuốc & quá liều hoạt chất
     *
     * @param array $medicines Danh sách thuốc đang dùng (mỗi phần tử có tên, hoạt chất, liều lượng)
     * @param array $patientHealthProfile Thông tin sức khỏe bệnh nhân (tuổi, bệnh nền, tiền sử dị ứng)
     * @return array Kết quả kiểm tra tương tác và mức độ rủi ro
     */
    public function checkDrugInteractions(array $medicines, array $patientHealthProfile = []): array
    {
        $medicinesJson = json_encode($medicines, JSON_UNESCAPED_UNICODE);
        $patientJson = json_encode($patientHealthProfile, JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Bạn là chuyên gia Dược lâm sàng AI. Hãy kiểm tra tương tác thuốc và độ an toàn cho bệnh nhân dựa trên dữ liệu sau:

Danh sách các thuốc bệnh nhân đang/sẽ uống cùng lúc:
{$medicinesJson}

Hồ sơ sức khỏe của bệnh nhân (bệnh nền, dị ứng, độ tuổi):
{$patientJson}

Nhiệm vụ kiểm tra:
1. Phát hiện trùng lặp hoạt chất gây nguy cơ quá liều (Ví dụ: uống cùng lúc Panadol và Hapacol/Decolgen đều chứa Paracetamol).
2. Tương tác bất lợi giữa các nhóm thuốc (vd: kháng sinh làm mất tác dụng men vi sinh, tương tác thuốc huyết áp và NSAID).
3. Chống chỉ định với tiền sử dị ứng hoặc bệnh nền của bệnh nhân (vd: dị ứng Aspirin/NSAID, suy gan, suy thận, loét dạ dày).

Chỉ trả về JSON thuần theo cấu trúc:
{
  "has_conflict": true/false,
  "risk_level": "safe" / "warning" / "danger",
  "summary": "Tóm tắt mức độ an toàn cho người bệnh trong 1-2 câu",
  "conflicts": [
    {
      "type": "duplicate_active_ingredient" / "drug_drug_interaction" / "allergy_conflict" / "disease_contraindication",
      "severity": "danger" / "warning" / "info",
      "medicines_involved": ["Thuốc A", "Thuốc B"],
      "active_ingredient": "Tên hoạt chất (nếu trùng lặp)",
      "description": "Giải thích chi tiết nguy cơ (ví dụ: Nguy cơ ngộ độc gan cấp do tổng liều Paracetamol vượt quá 4000mg/ngày)",
      "recommendation": "Lời khuyên xử lý cho bệnh nhân hoặc người nhà"
    }
  ],
  "recommendations": "Lời khuyên tổng thể cho đợt uống thuốc này"
}
PROMPT;

        if (empty($this->apiKey)) {
            Log::warning('Gemini API key is not configured. Evaluating drug interactions via rule engine.');
            return $this->evaluateLocalRuleInteractions($medicines, $patientHealthProfile);
        }

        try {
            $payload = [
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

        $prompt = <<<PROMPT
Bạn là bác sĩ gia đình tận tâm. Hãy giải thích đơn thuốc sau đây cho bệnh nhân và gia đình bằng ngôn ngữ bình dân, ấm áp, dễ hiểu, tránh các thuật ngữ chuyên môn gây khó hiểu:

Đơn thuốc:
{$prescriptionJson}

Hồ sơ người bệnh:
{$patientJson}

Chỉ trả về JSON thuần:
{
  "overview": "Mục đích tổng thể của đợt điều trị này",
  "guidelines_per_medicine": [
    {
      "medicine_name": "Tên thuốc",
      "purpose": "Thuốc này dùng để chữa triệu chứng gì (vd: hạ sốt giảm đau, diệt vi khuẩn nhiễm trùng)",
      "meal_timing": "Uống lúc nào (trước ăn 30p, ngay sau ăn no, lúc đói...)",
      "special_instructions": "Lưu ý khi uống (uống nhiều nước, không nằm ngay...)"
    }
  ],
  "dietary_precautions": [
    "Những thức ăn, đồ uống cần kiêng cữ trong đợt điều trị (vd: không uống rượu bia, hạn chế sữa cách 2 tiếng với kháng sinh...)"
  ],
  "common_side_effects": [
    {
      "symptom": "Tác dụng phụ thường gặp (vd: buồn nôn nhẹ, nước tiểu vàng)",
      "is_normal": true,
      "how_to_handle": "Cách xử lý tại nhà"
    }
  ],
  "when_to_see_doctor": [
    "Dấu hiệu cảnh báo nguy hiểm cần tái khám ngay lập tức"
  ]
}
PROMPT;

        if (empty($this->apiKey)) {
            return $this->getMockPrescriptionExplanation($prescriptionData);
        }

        try {
            $payload = [
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

        $prompt = <<<PROMPT
Bạn là Trợ lý Y tế & Dược sĩ Gia đình AI thông thái, tận tụy và đáng tin cậy.
Hãy trả lời câu hỏi y tế sau của người dùng bằng tiếng Việt chuẩn mực, rõ ràng, dễ hiểu cho mọi lứa tuổi:

Câu hỏi của người dùng:
"{$question}"

Ngữ cảnh hồ sơ sức khỏe & tủ thuốc gia đình (nếu có):
{$contextJson}

Yêu cầu trả lời:
1. Giải thích cụ thể, chính xác theo hướng dẫn dược lâm sàng và y khoa thực hành.
2. Dặn dò các lưu ý quan trọng, kiêng khem, hoặc cách xử trí an toàn tại nhà.
3. Luôn đưa ra lời khuyến cáo rõ ràng khi nào cần đến cơ sở y tế / bác sĩ thăm khám.

Trả lời dưới định dạng JSON thuần:
{
  "answer": "Câu trả lời chi tiết, ấm áp và chuyên nghiệp của bạn",
  "key_points": [
    "Ý chính cần ghi nhớ 1",
    "Ý chính cần ghi nhớ 2"
  ],
  "safety_warning": "Khuyến cáo an toàn y tế quan trọng (nếu có)",
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
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
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
     * Phản hồi thông minh nội bộ khi chưa có API Key
     */
    protected function getLocalRuleChatResponse(string $question, array $familyContext = []): array
    {
        $q = mb_strtolower($question, 'UTF-8');

        if (str_contains($q, 'quên') && str_contains($q, 'liều')) {
            return [
                'answer' => 'Khi quên uống 1 liều thuốc: Hãy uống ngay khi nhớ ra nếu thời điểm nhớ ra còn xa liều kế tiếp. Nếu đã gần đến giờ uống liều tiếp theo (dưới 2-3 tiếng), hãy bỏ qua liều đã quên và uống liều tiếp theo đúng lịch. TUYỆT ĐỐI KHÔNG UỐNG GẤP ĐÔI LIỀU để bù liều đã quên vì có thể gây ngộ độc hoặc tác dụng phụ nguy hiểm.',
                'key_points' => [
                    'Uống ngay nếu thời gian còn xa cữ tiếp theo',
                    'Bỏ qua nếu gần đến giờ uống cữ kế tiếp',
                    'Không bao giờ uống gấp đôi liều để bù',
                ],
                'safety_warning' => 'Đặc biệt cẩn trọng với các nhóm thuốc tim mạch, huyết áp, kháng sinh và thuốc tiểu đường.',
                'suggested_actions' => [
                    'Kiểm tra lại lịch uống trên ứng dụng MedCabinet',
                    'Bật thông báo cữ uống để tránh quên lần sau',
                ],
            ];
        }

        if (str_contains($q, 'bảo quản') || str_contains($q, 'nhiệt độ')) {
            return [
                'answer' => 'Quy tắc bảo quản thuốc gia đình chuẩn: Hầu hết các loại thuốc nên để ở nơi khô ráo, thoáng mát (dưới 30°C), tránh ánh nắng mặt trời trực tiếp và tránh ẩm ướt (không để trong nhà tắm). Một số thuốc đặc biệt như men vi sinh dạng lỏng, insulin hoặc thuốc nhỏ mắt sau mở nắp cần bảo quản ngăn mát tủ lạnh (2-8°C). Luôn để tủ thuốc ở vị trí cao khỏi tầm với của trẻ nhỏ.',
                'key_points' => [
                    'Nhiệt độ phòng < 30°C, khô thoáng',
                    'Tránh ánh nắng mặt trời và nơi ẩm ướt',
                    'Đặt tủ thuốc trên cao an toàn với trẻ nhỏ',
                ],
                'safety_warning' => 'Không sử dụng thuốc đã đổi màu, biến chất, viên nang bị chảy dính hoặc viên nén bị vỡ nát.',
                'suggested_actions' => [
                    'Kiểm tra định kỳ ngăn tủ trên ứng dụng',
                    'Bỏ các vỏ thuốc không còn rõ hạn sử dụng',
                ],
            ];
        }

        if (str_contains($q, 'trước ăn') || str_contains($q, 'sau ăn') || str_contains($q, 'uống lúc nào')) {
            return [
                'answer' => 'Thời điểm uống thuốc phụ thuộc vào từng nhóm hoạt chất: Thuốc uống trước ăn (30-60 phút) thường là thuốc bảo vệ niêm mạc dạ dày (như Omeprazole, Esomeprazole) hoặc thuốc bị thức ăn cản trở hấp thu. Thuốc uống sau ăn (ngay sau ăn no hoặc 15-30 phút) là các thuốc kháng viêm giảm đau (Ibuprofen, Paracetamol), kháng sinh nhằm tránh kích ứng viêm loét dạ dày.',
                'key_points' => [
                    'Trước ăn 30-60 phút: Thuốc bao tử, một số thuốc tuyến giáp',
                    'Sau ăn no: Kháng sinh, thuốc giảm đau hạ sốt, vitamin tan trong dầu (A, D, E, K)',
                    'Uống với nhiều nước lọc ấm (khoảng 150-200ml)',
                ],
                'safety_warning' => 'Không uống thuốc cùng nước trà đặc, sữa, nước ngọt có ga hoặc rượu bia.',
                'suggested_actions' => [
                    'Kiểm tra cột "Cách dùng" trong đơn thuốc trên ứng dụng',
                ],
            ];
        }

        if (str_contains($q, 'sốt') || str_contains($q, 'paracetamol') || str_contains($q, 'hạ sốt')) {
            return [
                'answer' => 'Hướng dẫn dùng thuốc hạ sốt an toàn: Paracetamol là lựa chọn đầu tay an toàn nhất. Liều dùng chuẩn cho người lớn là 500mg - 650mg/lần; cho trẻ em là 10-15mg/kg cân nặng/lần. Khoảng cách giữa 2 lần uống tối thiểu từ 4 đến 6 tiếng. Người lớn không uống quá 4000mg/ngày. Nếu sốt trên 39.5°C không hạ sau 2 liều hoặc kéo dài quá 3 ngày, cần đưa người bệnh đến bệnh viện kiểm tra.',
                'key_points' => [
                    'Khoảng cách tối thiểu 4 - 6 tiếng giữa 2 lần uống',
                    'Uống đủ nước, chườm ấm các vùng bẹn nách',
                    'Không uống cùng lúc nhiều chế phẩm có chứa Paracetamol',
                ],
                'safety_warning' => 'Uống quá liều Paracetamol có thể gây viêm gan và hoại tử tế bào gan cấp tính.',
                'suggested_actions' => [
                    'Kiểm tra các thuốc hạ sốt hiện có trong tủ thuốc',
                    'Đo lại thân nhiệt sau khi uống thuốc 45-60 phút',
                ],
            ];
        }

        // Mặc định câu trả lời tư vấn y tế
        return [
            'answer' => 'Chào bạn! Về câu hỏi y tế: "' . $question . '". Trong quá trình chăm sóc sức khỏe gia đình, bạn nên tuân thủ chặt chẽ chỉ định của bác sĩ hoặc hướng dẫn trên tờ toa thuốc đính kèm. Hãy đảm bảo uống đúng liều lượng, đúng thời điểm (trước/sau ăn) và kiểm tra hạn sử dụng của thuốc trong tủ thuốc gia đình trước khi dùng.',
            'key_points' => [
                'Đọc kỹ hướng dẫn sử dụng và hạn dùng trước khi uống',
                'Uống đủ lượng nước lọc và duy trì giờ giấc đều đặn',
                'Theo dõi phản ứng bất thường của cơ thể sau khi dùng thuốc',
            ],
            'safety_warning' => 'Nếu người bệnh có triệu chứng nặng như khó thở, phát ban dị ứng, đau ngực hoặc sốt li bì, hãy gọi cấp cứu hoặc đến cơ sở y tế gần nhất.',
            'suggested_actions' => [
                'Kiểm tra lại danh mục thuốc trong tủ thuốc gia đình',
                'Hỏi ý kiến dược sĩ/bác sĩ khi phối hợp nhiều loại thuốc mới',
            ],
        ];
    }

    /**
     * Chuyển đổi file ảnh sang chuỗi Base64
     */
    protected function prepareImageBase64(string $imagePathOrBase64, ?string &$mimeType): string
    {
        // Nếu đã là chuỗi base64 có data uri
        if (str_starts_with($imagePathOrBase64, 'data:image/')) {
            if (preg_match('/^data:(image\/[a-zA-Z0-9]+);base64,(.+)$/', $imagePathOrBase64, $matches)) {
                $mimeType = $matches[1];
                return $matches[2];
            }
        }

        // Nếu là đường dẫn file cục bộ
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

        // 1. Kiểm tra trùng lặp hoạt chất Paracetamol (kể cả khi nồng độ/thành phần phối hợp khác nhau)
        $paracetamolMeds = [];
        foreach ($medicines as $item) {
            $ing = strtolower($item['active_ingredient'] ?? '');
            $name = $item['name'] ?? 'Thuốc';
            $lowerName = strtolower($name);

            if (str_contains($ing, 'paracetamol') || 
                str_contains($lowerName, 'panadol') || 
                str_contains($lowerName, 'hapacol') || 
                str_contains($lowerName, 'efferalgan') || 
                str_contains($lowerName, 'decolgen')) {
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
                'description' => 'CẢNH BÁO QUÁ LIỀU: Các thuốc ' . implode(' và ', $uniqueMeds) . ' đều chứa hoạt chất Paracetamol. Uống cùng lúc có nguy cơ gây độc tính gan cấp tính và hoại tử tế bào gan.',
                'recommendation' => 'Chỉ chọn uống một loại thuốc hạ sốt giảm đau. Khoảng cách giữa 2 lần uống tối thiểu 4 - 6 tiếng, tổng liều không quá 4000mg/ngày cho người lớn.',
            ];
        }

        // Kiểm tra dị ứng thuốc theo hồ sơ bệnh nhân
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
                        'description' => 'NGUY HIỂM DỊ ỨNG: Bệnh nhân có tiền sử dị ứng với NSAID/Aspirin, thuốc này có thể gây co thắt phế quản, phát ban hoặc sốc phản vệ.',
                        'recommendation' => 'Ngừng ngay thuốc này và liên hệ bác sĩ để đổi sang nhóm giảm đau an toàn hơn như Paracetamol đơn chất.',
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
                    'active_ingredient' => 'Amoxicillin + Clavulanic Acid',
                    'concentration' => '1000mg',
                    'dosage' => '1 viên',
                    'frequency' => '2 lần/ngày (Sáng - Tối)',
                    'instructions' => 'Uống ngay đầu bữa ăn với nhiều nước',
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
                    'instructions' => 'Uống sau ăn, cách nhau ít nhất 4 - 6 tiếng',
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
                'Cần uống kháng sinh Augmentin đủ 7 ngày, không tự ý ngừng thuốc ngay cả khi đã hết sốt để tránh vi khuẩn kháng thuốc.',
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
