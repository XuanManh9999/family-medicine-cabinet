<?php

namespace Tests\Feature;

use App\Models\CabinetRequest;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CabinetRequestSimplifiedFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $familyAdmin;
    protected User $doctor;
    protected User $systemAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->familyAdmin = User::where('email', 'an.nguyen@gmail.com')->firstOrFail();
        $this->doctor = User::where('email', 'bs.minh@medcabinet.vn')->firstOrFail();
        $this->systemAdmin = User::where('email', 'admin@medcabinet.vn')->firstOrFail();
    }

    /** Ảnh PNG thật 1x1 (máy kiểm thử không có GD để sinh ảnh giả) */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'custom_name' => 'Ibuprofen 400mg',
            'expiry_date' => now()->addYear()->toDateString(),
            'quantity' => 1,
            'unit' => 'hộp',
        ], $overrides);
    }

    public function test_simple_form_only_requires_what_is_printed_on_the_box(): void
    {
        $this->actingAs($this->familyAdmin)->get(route('cabinet.requests.create'))
            ->assertOk()
            ->assertSee('Chụp ảnh vỏ hộp thuốc')
            ->assertSee('Mua cho ai dùng?')
            // Không còn các trường chuyên môn của form Bác sĩ
            ->assertDontSee('Chọn thuốc có sẵn từ danh mục dược phẩm')
            ->assertDontSee('name="manufacturing_date"', false)
            ->assertDontSee('XEM TRƯỚC NHÃN THUỐC');

        // Chỉ 4 ô bắt buộc là đủ để gửi
        $this->actingAs($this->familyAdmin)
            ->post(route('cabinet.requests.store'), $this->payload())
            ->assertRedirect(route('cabinet.requests.index'));
        $this->assertDatabaseHas('cabinet_requests', ['custom_name' => 'Ibuprofen 400mg', 'status' => 'pending']);

        // Thuốc đã hết hạn không được đề nghị nhập
        $this->actingAs($this->familyAdmin)
            ->post(route('cabinet.requests.store'), $this->payload(['expiry_date' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('expiry_date');
    }

    public function test_box_photos_are_private_and_shown_to_reviewer(): void
    {
        Storage::fake('local');

        $this->actingAs($this->familyAdmin)->post(route('cabinet.requests.store'), $this->payload([
            'box_photos' => [
                $this->png('mat-truoc.png'),
                $this->png('hsd.png'),
            ],
        ]))->assertRedirect();

        $request = CabinetRequest::firstOrFail();
        $this->assertCount(2, $request->box_image_paths);
        Storage::disk('local')->assertExists($request->box_image_paths[0]);

        // Bác sĩ cùng gia đình xem được ảnh trên trang duyệt
        $this->actingAs($this->doctor)->get(route('cabinet.requests.review', $request))
            ->assertOk()
            ->assertSee(route('cabinet.requests.photo', [$request, 0]), false);
        $this->actingAs($this->doctor)->get(route('cabinet.requests.photo', [$request, 1]))->assertOk();

        // Người ngoài gia đình không xem được ảnh
        $outsider = User::create([
            'family_id' => Family::create(['name' => 'Nhà khác', 'code' => 'FAM-PHOTO1'])->id,
            'name' => 'Người ngoài',
            'email' => 'outsider.photo@example.com',
            'password' => bcrypt('password123'),
            'role' => 'family_admin',
        ]);
        $this->actingAs($outsider)->get(route('cabinet.requests.photo', [$request, 0]))->assertNotFound();
        $this->actingAs($this->doctor)->get(route('cabinet.requests.photo', [$request, 5]))->assertNotFound();

        // Ảnh không hợp lệ bị từ chối
        $this->actingAs($this->familyAdmin)->post(route('cabinet.requests.store'), $this->payload([
            'box_photos' => [UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')],
        ]))->assertSessionHasErrors('box_photos.0');
    }

    public function test_scan_box_without_ai_key_never_fills_demo_data(): void
    {
        config(['services.gemini.api_key' => null]);

        $this->actingAs($this->familyAdmin)
            ->postJson(route('cabinet.requests.scan-box'), ['photo' => $this->png('box.png')])
            ->assertOk()
            ->assertJson(['success' => false])
            ->assertJsonMissingPath('name')
            ->assertJsonMissingPath('expiry_date');

        // Bác sĩ không cần (và không được) dùng luồng đề nghị
        $this->actingAs($this->doctor)
            ->postJson(route('cabinet.requests.scan-box'), ['photo' => $this->png('box.png')])
            ->assertForbidden();
    }

    public function test_scan_box_with_ai_returns_cleaned_fields(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'name' => 'Panadol Extra',
                    'active_ingredient' => 'Paracetamol 500mg, Caffeine 65mg',
                    'batch_number' => 'LOT-AI-9',
                    'expiry_date' => '2027-02-30',
                    'unit' => 'viên',
                ])]]]]],
            ]),
        ]);

        $this->actingAs($this->familyAdmin)
            ->postJson(route('cabinet.requests.scan-box'), ['photo' => $this->png('box.png')])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'name' => 'Panadol Extra',
                'batch_number' => 'LOT-AI-9',
                'expiry_date' => null, // ngày không tồn tại bị loại bỏ, không điền sai vào form
            ]);

        // API key gửi qua header, không nằm trong URL
        \Illuminate\Support\Facades\Http::assertSent(fn ($req) => $req->hasHeader('x-goog-api-key', 'test-key') && !str_contains($req->url(), 'key='));
    }
    public function test_review_page_suggests_master_medicine_and_flags_allergy_and_duplicates(): void
    {
        $hoaProfile = User::where('email', 'hoa.tran@gmail.com')->firstOrFail()->familyMember;
        $medicine = Medicine::create([
            'category_id' => \App\Models\MedicineCategory::firstOrFail()->id,
            'code' => 'MED-IBU-TEST',
            'name' => 'Ibuprofen 400mg',
            'active_ingredient' => 'Ibuprofen 400mg',
            'default_unit' => 'viên',
        ]);

        // Mua Ibuprofen cho chị Hoa — người có tiền sử dị ứng NSAID
        $this->actingAs($this->familyAdmin)->post(route('cabinet.requests.store'), $this->payload([
            'family_member_id' => $hoaProfile->id,
            'requester_note' => 'Mua theo lời dược sĩ ở hiệu thuốc',
        ]));
        $request = CabinetRequest::firstOrFail();

        $response = $this->actingAs($this->doctor)->get(route('cabinet.requests.review', $request))->assertOk();
        $response->assertSee('Thuốc khớp trong danh mục dược phẩm')
            ->assertSee('Khớp tên')
            ->assertSee('data-medicine-id="' . $medicine->id . '"', false)
            ->assertSee('Mua theo lời dược sĩ ở hiệu thuốc')
            ->assertSee('NGUY HIỂM DỊ ỨNG')
            ->assertSee($hoaProfile->name);

        // Thêm Paracetamol khi tủ đã có Panadol/Efferalgan → cảnh báo trùng hoạt chất
        $this->actingAs($this->familyAdmin)->post(route('cabinet.requests.store'), $this->payload([
            'custom_name' => 'Hapacol 500',
            'active_ingredient' => 'Paracetamol 500mg',
        ]));
        $paracetamolRequest = CabinetRequest::where('custom_name', 'Hapacol 500')->firstOrFail();
        $this->actingAs($this->doctor)->get(route('cabinet.requests.review', $paracetamolRequest))
            ->assertOk()
            ->assertSee('CẢNH BÁO QUÁ LIỀU');
    }

    public function test_family_without_doctor_is_warned_and_system_admin_reviews_instead(): void
    {
        // Gia đình mới chưa có Bác sĩ
        $family = Family::create(['name' => 'Gia đình chưa có Bác sĩ', 'code' => 'FAM-NODOC1']);
        $owner = User::create([
            'family_id' => $family->id,
            'name' => 'Chủ hộ mới',
            'email' => 'owner.nodoc@example.com',
            'password' => bcrypt('password123'),
            'role' => 'family_admin',
        ]);
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $owner->id, 'name' => $owner->name, 'relationship' => 'ban_than', 'gender' => 'nam']);

        $this->actingAs($owner)->get(route('cabinet.requests.create'))
            ->assertOk()
            ->assertSee('Gia đình chưa có Bác sĩ / Người chăm sóc.')
            ->assertSee('Gửi cho Quản trị viên kiểm tra');

        $this->actingAs($owner)->post(route('cabinet.requests.store'), $this->payload(['custom_name' => 'Vitamin C 500mg']))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Quản trị viên hệ thống sẽ duyệt thay'));
        $request = CabinetRequest::where('family_id', $family->id)->firstOrFail();

        // Quản trị viên thấy đề nghị ở trang quản trị và duyệt thay
        $this->actingAs($this->systemAdmin)->get(route('admin.cabinet-requests.index'))
            ->assertOk()
            ->assertSee('Gia đình chưa có Bác sĩ')
            ->assertSee(route('cabinet.requests.review', $request), false);
        $this->actingAs($this->systemAdmin)->get(route('cabinet.requests.review', $request))->assertOk();

        $this->actingAs($this->systemAdmin)
            ->post(route('cabinet.requests.approve', $request), $this->payload(['custom_name' => 'Vitamin C 500mg']))
            ->assertRedirect(route('admin.cabinet-requests.index'));

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertDatabaseHas('cabinet_items', ['family_id' => $family->id, 'custom_name' => 'Vitamin C 500mg']);

        // Bác sĩ của gia đình khác vẫn không duyệt được
        $this->actingAs($owner)->post(route('cabinet.requests.store'), $this->payload(['custom_name' => 'Oresol']));
        $second = CabinetRequest::where('custom_name', 'Oresol')->firstOrFail();
        $this->actingAs($this->doctor)->get(route('cabinet.requests.review', $second))->assertNotFound();
    }
}
