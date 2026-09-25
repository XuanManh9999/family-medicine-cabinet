<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Family $family;
    protected FamilyMember $member;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->family = Family::create([
            'name' => 'Gia đình Lê Quang Khải',
            'code' => 'FAMTEST2',
        ]);

        $this->user = User::create([
            'family_id' => $this->family->id,
            'name' => 'Lê Quang Khải',
            'email' => 'khai@example.com',
            'password' => bcrypt('password123'),
            'role' => 'family_admin',
        ]);

        $this->member = FamilyMember::create([
            'family_id' => $this->family->id,
            'user_id' => $this->user->id,
            'name' => 'Lê Quang Khải',
            'relationship' => 'chong',
            'dob' => '1995-03-25',
            'gender' => 'nam',
            'allergies' => 'Aspirin',
            'underlying_diseases' => 'Viêm xoang',
        ]);
    }

    public function test_user_can_view_ai_assistant_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('ai-assistant.index'));

        $response->assertStatus(200);
        $response->assertSee('Trợ lý Y tế', false);
        $response->assertSee('Vision OCR', false);
        $response->assertSee('Trò chuyện Tư vấn Y tế', false);
    }

    public function test_user_can_send_chat_question_and_get_advice(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('ai-assistant.chat'), [
            'question' => 'Quên uống 1 liều thuốc huyết áp thì phải làm sao?',
            'family_member_id' => $this->member->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'question' => 'Quên uống 1 liều thuốc huyết áp thì phải làm sao?',
        ]);
        $response->assertJsonStructure([
            'success',
            'question',
            'response' => [
                'answer',
                'key_points',
                'safety_warning',
                'suggested_actions',
            ],
            'answered_at',
        ]);
    }

    public function test_user_can_scan_prescription_image(): void
    {
        $file = UploadedFile::fake()->create('prescription.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->user)->postJson(route('ai-assistant.scan'), [
            'prescription_image' => $file,
            'family_member_id' => $this->member->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'image_url',
            'ocr_data' => [
                'hospital_doctor',
                'diagnosis',
                'medicines',
            ],
        ]);
    }
}
