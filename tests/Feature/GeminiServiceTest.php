<?php

namespace Tests\Feature;

use App\Services\GeminiService;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    public function test_gemini_service_detects_duplicate_paracetamol_overdose(): void
    {
        $service = new GeminiService();

        $medicines = [
            [
                'name' => 'Panadol Extra',
                'active_ingredient' => 'Paracetamol 500mg, Caffeine 65mg',
                'dosage' => '2 viên',
            ],
            [
                'name' => 'Decolgen Forte',
                'active_ingredient' => 'Paracetamol 500mg, Pseudoephedrine',
                'dosage' => '1 viên',
            ],
        ];

        $result = $service->checkDrugInteractions($medicines, []);

        $this->assertTrue($result['has_conflict']);
        $this->assertEquals('danger', $result['risk_level']);
        $this->assertNotEmpty($result['conflicts']);
        $this->assertEquals('duplicate_active_ingredient', $result['conflicts'][0]['type']);
        $this->assertStringContainsString('Paracetamol', $result['conflicts'][0]['description']);
    }

    public function test_gemini_service_detects_patient_allergy_conflict(): void
    {
        $service = new GeminiService();

        $medicines = [
            [
                'name' => 'Ibuprofen 400mg',
                'active_ingredient' => 'Ibuprofen',
                'dosage' => '1 viên',
            ],
        ];

        $patientHealth = [
            'name' => 'Trần Thị Hoa',
            'allergies' => 'Dị ứng thuốc nhóm NSAID (Aspirin, Ibuprofen)',
        ];

        $result = $service->checkDrugInteractions($medicines, $patientHealth);

        $this->assertTrue($result['has_conflict']);
        $this->assertEquals('danger', $result['risk_level']);
        $this->assertEquals('allergy_conflict', $result['conflicts'][0]['type']);
    }

    public function test_gemini_service_plain_language_explainer(): void
    {
        $service = new GeminiService();

        $prescription = [
            'medicines' => [
                ['name' => 'Augmentin 1g', 'dosage' => '1 viên x 2 lần/ngày'],
                ['name' => 'Panadol Extra', 'dosage' => '1 viên khi sốt'],
            ],
        ];

        $result = $service->explainPrescription($prescription);

        $this->assertArrayHasKey('overview', $result);
        $this->assertArrayHasKey('guidelines_per_medicine', $result);
        $this->assertArrayHasKey('dietary_precautions', $result);
        $this->assertNotEmpty($result['guidelines_per_medicine']);
    }

    public function test_gemini_service_vision_ocr_mock(): void
    {
        $service = new GeminiService();

        $result = $service->analyzePrescriptionImage('dummy_image_data');

        $this->assertArrayHasKey('hospital_doctor', $result);
        $this->assertArrayHasKey('diagnosis', $result);
        $this->assertArrayHasKey('medicines', $result);
        $this->assertNotEmpty($result['medicines']);
    }

    public function test_gemini_service_medical_chat_assistant(): void
    {
        $service = new GeminiService();

        $result = $service->askMedicalAssistant('Quên uống 1 liều thuốc huyết áp thì phải làm sao?');

        $this->assertArrayHasKey('answer', $result);
        $this->assertArrayHasKey('key_points', $result);
        $this->assertArrayHasKey('safety_warning', $result);
        $this->assertNotEmpty($result['key_points']);
    }
}

