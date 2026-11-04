<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $familyAdmin;
    protected User $memberUser;
    protected Family $family;
    protected MedicineCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->family = Family::create([
            'name' => 'Gia đình Mẫu Test',
            'code' => 'FAM-TEST-ADMIN',
        ]);

        $this->superAdmin = User::create([
            'family_id' => $this->family->id,
            'name' => 'Quản trị viên Hệ thống',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'system_admin',
        ]);

        $this->familyAdmin = User::create([
            'family_id' => $this->family->id,
            'name' => 'Chủ hộ Gia đình',
            'email' => 'familyadmin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'family_admin',
        ]);

        $this->memberUser = User::create([
            'family_id' => $this->family->id,
            'name' => 'Thành viên Gia đình',
            'email' => 'member@example.com',
            'password' => bcrypt('password123'),
            'role' => 'member',
        ]);

        $this->category = MedicineCategory::create([
            'name' => 'Kháng sinh & Kháng viêm',
            'description' => 'Thuốc điều trị nhiễm khuẩn',
            'is_prescription_required' => true,
        ]);
    }

    public function test_guest_is_redirected_from_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect('/login');
    }

    public function test_normal_user_is_forbidden_from_admin_area(): void
    {
        $response = $this->actingAs($this->familyAdmin)->get(route('admin.dashboard'));
        $response->assertStatus(403);

        $responseMember = $this->actingAs($this->memberUser)->get(route('admin.dashboard'));
        $responseMember->assertStatus(403);
    }

    public function test_system_admin_can_access_admin_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Tổng quan Hệ thống MedCabinet');
        $response->assertSee('Hộ Gia đình');
        $response->assertSee('Dược thư Chuẩn');
    }

    public function test_system_admin_can_view_and_create_master_medicine(): void
    {
        // 1. Xem danh sách thuốc
        $response = $this->actingAs($this->superAdmin)->get(route('admin.medicines.index'));
        $response->assertStatus(200);
        $response->assertSee('Quản trị Dược thư Quốc gia');

        // 2. Thêm thuốc mới
        $responseCreate = $this->actingAs($this->superAdmin)->post(route('admin.medicines.store'), [
            'category_id' => $this->category->id,
            'code' => 'MED-CLAMOXYL-01',
            'name' => 'Clamoxyl 500mg',
            'active_ingredient' => 'Amoxicillin Trihydrate',
            'concentration' => '500mg',
            'dosage_form' => 'Viên nang cứng',
            'default_unit' => 'viên',
            'manufacturer' => 'GlaxoSmithKline',
            'usage_instructions' => 'Người lớn: 1 viên mỗi 8 giờ sau khi ăn.',
            'contraindications' => 'Dị ứng với kháng sinh nhóm Penicillin hoặc Cephalosporin.',
        ]);

        $responseCreate->assertRedirect(route('admin.medicines.index'));
        $this->assertDatabaseHas('medicines', [
            'code' => 'MED-CLAMOXYL-01',
            'name' => 'Clamoxyl 500mg',
        ]);
    }

    public function test_system_admin_can_update_and_delete_master_medicine(): void
    {
        $med = Medicine::create([
            'category_id' => $this->category->id,
            'code' => 'MED-TEST-DEL',
            'name' => 'Thuốc Kiểm Thử Xóa',
            'active_ingredient' => 'Test Ingredient',
            'default_unit' => 'viên',
        ]);

        // Cập nhật
        $responseUpdate = $this->actingAs($this->superAdmin)->put(route('admin.medicines.update', $med), [
            'category_id' => $this->category->id,
            'code' => 'MED-TEST-DEL',
            'name' => 'Thuốc Đã Cập Nhật',
            'active_ingredient' => 'Test Ingredient Updated',
            'default_unit' => 'gói',
        ]);

        $responseUpdate->assertRedirect(route('admin.medicines.index'));
        $this->assertDatabaseHas('medicines', [
            'id' => $med->id,
            'name' => 'Thuốc Đã Cập Nhật',
            'default_unit' => 'gói',
        ]);

        // Xóa
        $responseDelete = $this->actingAs($this->superAdmin)->delete(route('admin.medicines.destroy', $med));
        $responseDelete->assertRedirect(route('admin.medicines.index'));
        $this->assertDatabaseMissing('medicines', ['id' => $med->id]);
    }

    public function test_system_admin_can_manage_categories(): void
    {
        // Thêm nhóm mới
        $response = $this->actingAs($this->superAdmin)->post(route('admin.categories.store'), [
            'name' => 'Nhóm Da Liễu & Bôi Ngoài',
            'description' => 'Các loại kem, gel bôi da',
            'is_prescription_required' => '1',
        ]);

        $response->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('medicine_categories', [
            'name' => 'Nhóm Da Liễu & Bôi Ngoài',
            'is_prescription_required' => 1,
        ]);
    }

    public function test_system_admin_can_view_families_and_users(): void
    {
        // Xem danh sách gia đình
        $responseFamilies = $this->actingAs($this->superAdmin)->get(route('admin.families.index'));
        $responseFamilies->assertStatus(200);
        $responseFamilies->assertSee('Gia đình Mẫu Test');

        // Xem chi tiết gia đình
        $responseFamilyShow = $this->actingAs($this->superAdmin)->get(route('admin.families.show', $this->family));
        $responseFamilyShow->assertStatus(200);
        $responseFamilyShow->assertSee('Gia đình Mẫu Test');

        // Xem danh sách người dùng
        $responseUsers = $this->actingAs($this->superAdmin)->get(route('admin.users.index'));
        $responseUsers->assertStatus(200);
        $responseUsers->assertSee('superadmin@example.com');
        $responseUsers->assertSee('familyadmin@example.com');

        // Đổi vai trò người dùng
        $responseRole = $this->actingAs($this->superAdmin)->patch(route('admin.users.role', $this->memberUser), [
            'role' => 'family_admin',
        ]);
        $responseRole->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $this->memberUser->id,
            'role' => 'family_admin',
        ]);
    }

    public function test_admin_lists_can_be_filtered_by_day_month_year_and_range(): void
    {
        $oldFamily = Family::create(['name' => 'Gia đình Năm Cũ', 'code' => 'FAM-OLD-2024']);
        $oldFamily->forceFill(['created_at' => '2024-03-15 09:00:00'])->save();

        $oldUser = User::create([
            'family_id' => $oldFamily->id,
            'name' => 'Người dùng Năm Cũ',
            'email' => 'olduser@example.com',
            'password' => bcrypt('password123'),
            'role' => 'family_admin',
        ]);
        $oldUser->forceFill(['created_at' => '2024-03-15 09:00:00'])->save();

        // Theo năm
        $this->actingAs($this->superAdmin)
            ->get(route('admin.families.index', ['period' => 'year', 'year' => 2024]))
            ->assertOk()
            ->assertSee('Gia đình Năm Cũ')
            ->assertDontSee('Gia đình Mẫu Test');

        // Theo tháng
        $this->actingAs($this->superAdmin)
            ->get(route('admin.users.index', ['period' => 'month', 'month' => '2024-03']))
            ->assertOk()
            ->assertSee('olduser@example.com')
            ->assertDontSee('familyadmin@example.com');

        // Theo ngày cụ thể (không khớp ngày nào)
        $this->actingAs($this->superAdmin)
            ->get(route('admin.users.index', ['period' => 'day', 'date' => '2024-03-16']))
            ->assertOk()
            ->assertDontSee('olduser@example.com');

        // Khoảng ngày (nhập ngược vẫn hiểu đúng)
        $this->actingAs($this->superAdmin)
            ->get(route('admin.families.index', ['period' => 'range', 'from' => '2024-03-31', 'to' => '2024-03-01']))
            ->assertOk()
            ->assertSee('Gia đình Năm Cũ');

        // Mốc nhanh "Hôm nay" chỉ còn dữ liệu mới
        $this->actingAs($this->superAdmin)
            ->get(route('admin.families.index', ['period' => 'today']))
            ->assertOk()
            ->assertSee('Gia đình Mẫu Test')
            ->assertDontSee('Gia đình Năm Cũ');

        // Dashboard hiển thị số liệu phát sinh trong kỳ, giá trị lỗi bị bỏ qua
        $this->actingAs($this->superAdmin)
            ->get(route('admin.dashboard', ['period' => 'year', 'year' => 2024]))
            ->assertOk()
            ->assertSee('Phát sinh trong kỳ')
            ->assertSee('Năm 2024)', false);
        $this->actingAs($this->superAdmin)
            ->get(route('admin.dashboard', ['period' => 'month', 'month' => 'abc']))
            ->assertOk()
            ->assertDontSee('Phát sinh trong kỳ');

        // Chi tiết hộ gia đình lọc được đơn thuốc / chi phí
        $this->actingAs($this->superAdmin)
            ->get(route('admin.families.show', [$this->family, 'period' => 'this_month']))
            ->assertOk()
            ->assertSee('Chi phí Y tế');
    }

    public function test_role_change_keeps_family_admin_and_requires_family(): void
    {
        // Không hạ quyền Chủ hộ duy nhất của gia đình
        $this->actingAs($this->superAdmin)
            ->patch(route('admin.users.role', $this->familyAdmin), ['role' => 'member'])
            ->assertSessionHas('warning');
        $this->assertDatabaseHas('users', ['id' => $this->familyAdmin->id, 'role' => 'family_admin']);

        // Tài khoản chưa thuộc gia đình không được gán vai trò trong gia đình
        $orphan = User::create([
            'family_id' => null,
            'name' => 'Tài khoản tự do',
            'email' => 'orphan@example.com',
            'password' => bcrypt('password123'),
            'role' => 'system_admin',
        ]);
        $this->actingAs($this->superAdmin)
            ->patch(route('admin.users.role', $orphan), ['role' => 'doctor_caregiver'])
            ->assertSessionHas('warning');
        $this->assertDatabaseHas('users', ['id' => $orphan->id, 'role' => 'system_admin']);

        // Nâng thành Quản trị viên hệ thống: rời khỏi hộ gia đình, hồ sơ sức khỏe giữ lại
        $profile = \App\Models\FamilyMember::create([
            'family_id' => $this->family->id,
            'user_id' => $this->memberUser->id,
            'name' => $this->memberUser->name,
            'relationship' => 'con_cai',
            'gender' => 'nu',
        ]);
        $this->actingAs($this->superAdmin)
            ->patch(route('admin.users.role', $this->memberUser), ['role' => 'system_admin'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $this->memberUser->id, 'role' => 'system_admin', 'family_id' => null]);
        $this->assertDatabaseHas('family_members', ['id' => $profile->id, 'user_id' => null]);

        // Đường dẫn trong modal sinh bởi route(), không phải chuỗi cứng
        $this->actingAs($this->superAdmin)
            ->get(route('admin.users.index'))
            ->assertSee('data-action="' . route('admin.users.role', $this->memberUser) . '"', false);
    }
}
