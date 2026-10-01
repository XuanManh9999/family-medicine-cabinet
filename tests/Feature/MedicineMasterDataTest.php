<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicineMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Family $family;
    protected MedicineCategory $category;
    protected Medicine $medicine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->family = Family::create([
            'name' => 'Gia đình Lê Quang Khải',
            'code' => 'FAMTEST1',
        ]);

        $this->user = User::create([
            'family_id' => $this->family->id,
            'name' => 'Lê Quang Khải',
            'email' => 'khai@example.com',
            'password' => bcrypt('password123'),
            'role' => 'family_admin',
        ]);

        $this->category = MedicineCategory::create([
            'name' => 'Giảm đau, Hạ sốt & NSAID',
            'code' => 'CAT_GIAM_DAU',
            'description' => 'Thuốc hạ nhiệt, giảm đau',
        ]);

        $this->medicine = Medicine::create([
            'category_id' => $this->category->id,
            'code' => 'MED-PAN-01',
            'name' => 'Panadol Extra',
            'active_ingredient' => 'Paracetamol 500mg, Caffeine 65mg',
            'concentration' => '500mg/65mg',
            'dosage_form' => 'Viên nén bao phim',
            'default_unit' => 'viên',
            'manufacturer' => 'GlaxoSmithKline (GSK)',
            'usage_instructions' => 'Người lớn: 1-2 viên mỗi 4-6 giờ khi sốt hoặc đau.',
            'side_effects' => 'Mất ngủ nếu uống vào buổi tối do caffeine.',
            'contraindications' => 'Người mẫn cảm với paracetamol, suy gan nặng.',
        ]);
    }

    public function test_authenticated_user_can_view_medicines_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('medicines.index'));

        $response->assertStatus(200);
        $response->assertSee('Tra cứu Danh mục Thuốc chuẩn');
        $response->assertSee('Panadol Extra');
        $response->assertSee('Paracetamol 500mg, Caffeine 65mg');
    }

    public function test_user_can_search_medicine_by_keyword(): void
    {
        Medicine::create([
            'category_id' => $this->category->id,
            'code' => 'MED-AUG-02',
            'name' => 'Augmentin 1g',
            'active_ingredient' => 'Amoxicillin + Clavulanic Acid',
            'concentration' => '1000mg',
            'dosage_form' => 'Viên nén',
            'default_unit' => 'viên',
            'manufacturer' => 'GSK',
        ]);

        $response = $this->actingAs($this->user)->get(route('medicines.index', ['q' => 'Augmentin']));

        $response->assertStatus(200);
        $response->assertSee('Augmentin 1g');
        $response->assertDontSee('Panadol Extra');
    }

    public function test_user_can_filter_medicines_by_category(): void
    {
        $otherCat = MedicineCategory::create([
            'name' => 'Vitamin & Khoáng chất',
            'code' => 'CAT_VITAMIN',
        ]);

        $response = $this->actingAs($this->user)->get(route('medicines.index', ['category_id' => $this->category->id]));

        $response->assertStatus(200);
        $response->assertSee('Panadol Extra');
    }

    public function test_user_can_view_medicine_detail_json(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('medicines.show', $this->medicine));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'medicine' => [
                'id' => $this->medicine->id,
                'name' => 'Panadol Extra',
                'active_ingredient' => 'Paracetamol 500mg, Caffeine 65mg',
            ],
        ]);
    }

    public function test_user_can_view_medicine_detail_html_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('medicines.show', $this->medicine));

        $response->assertStatus(200);
        $response->assertSee('Panadol Extra');
        $response->assertSee('Thông tin Hoạt chất và Bào chế');
        $response->assertSee('Tồn kho trong tủ gia đình');
    }

    public function test_user_can_quick_add_medicine_to_cabinet(): void
    {
        $response = $this->actingAs($this->user)->post(route('medicines.add-to-cabinet', $this->medicine), [
            'quantity' => 20,
            'unit' => 'viên',
            'expiry_date' => now()->addYear()->toDateString(),
            'storage_location' => 'Ngăn số 2',
            'batch_number' => 'LOT-TEST-99',
            'price_per_unit' => 3000,
        ]);

        $response->assertRedirect(route('cabinet.index'));
        $this->assertDatabaseHas('cabinet_items', [
            'family_id' => $this->family->id,
            'medicine_id' => $this->medicine->id,
            'custom_name' => 'Panadol Extra',
            'quantity' => 20,
            'unit' => 'viên',
            'storage_location' => 'Ngăn số 2',
            'batch_number' => 'LOT-TEST-99',
        ]);
    }
}
