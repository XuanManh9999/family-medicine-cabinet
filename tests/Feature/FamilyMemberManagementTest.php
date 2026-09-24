<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_view_family_members_list_with_kpi_stats(): void
    {
        $admin = User::where('email', 'an.nguyen@gmail.com')->first();
        $response = $this->actingAs($admin)->get(route('members.index'));

        $response->assertStatus(200);
        $response->assertSee('Hồ sơ Sức khỏe Thành viên Gia đình');
        $response->assertSee('Nguyễn Văn Thịnh');
        $response->assertSee('Trần Thị Hoa');
        $response->assertSee('Dị ứng thuốc nhóm NSAID');
    }

    public function test_user_can_view_family_member_show_page_with_ehr(): void
    {
        $admin = User::where('email', 'an.nguyen@gmail.com')->first();
        $grandpa = FamilyMember::where('name', 'Nguyễn Văn Thịnh')->first();

        $response = $this->actingAs($admin)->get(route('members.show', $grandpa));

        $response->assertStatus(200);
        $response->assertSee('Hồ sơ Bệnh án điện tử:');
        $response->assertSee('Nguyễn Văn Thịnh');
        $response->assertSee('Tăng huyết áp vô căn độ 2');
    }

    public function test_admin_can_create_new_family_member(): void
    {
        $admin = User::where('email', 'an.nguyen@gmail.com')->first();
        $family = $admin->family;

        $response = $this->actingAs($admin)->post(route('members.store'), [
            'name' => 'Nguyễn Minh Khôi',
            'relationship' => 'con_cai',
            'dob' => '2023-05-12',
            'gender' => 'nam',
            'blood_type' => 'O',
            'allergies' => 'Dị ứng đạm sữa bò nhẹ',
            'underlying_diseases' => null,
        ]);

        $newMember = FamilyMember::where('name', 'Nguyễn Minh Khôi')->first();
        $this->assertNotNull($newMember);
        $this->assertEquals($family->id, $newMember->family_id);
        $this->assertEquals('con_cai', $newMember->relationship);
        $response->assertRedirect(route('members.show', $newMember));
    }

    public function test_admin_can_update_family_member(): void
    {
        $admin = User::where('email', 'an.nguyen@gmail.com')->first();
        $daughter = FamilyMember::where('name', 'Nguyễn Mai Anh')->first();

        $response = $this->actingAs($admin)->put(route('members.update', $daughter), [
            'name' => 'Nguyễn Mai Anh (Bé Su)',
            'relationship' => 'con_cai',
            'dob' => '2019-06-10',
            'gender' => 'nu',
            'blood_type' => 'O',
            'allergies' => 'Dị ứng phấn hoa',
            'underlying_diseases' => 'Viêm mũi dị ứng thời tiết',
        ]);

        $response->assertRedirect(route('members.show', $daughter));

        $daughter->refresh();
        $this->assertEquals('Nguyễn Mai Anh (Bé Su)', $daughter->name);
        $this->assertEquals('Dị ứng phấn hoa', $daughter->allergies);
    }

    public function test_admin_can_delete_family_member(): void
    {
        $admin = User::where('email', 'an.nguyen@gmail.com')->first();
        $memberToDelete = FamilyMember::where('name', 'Nguyễn Mai Anh')->first();

        $response = $this->actingAs($admin)->delete(route('members.destroy', $memberToDelete));

        $response->assertRedirect(route('members.index'));
        $this->assertDatabaseMissing('family_members', [
            'id' => $memberToDelete->id,
        ]);
    }

    public function test_cannot_delete_founding_family_admin(): void
    {
        $admin = User::where('email', 'an.nguyen@gmail.com')->first();
        $adminMember = FamilyMember::where('user_id', $admin->id)->first();

        $response = $this->actingAs($admin)->delete(route('members.destroy', $adminMember));

        $this->assertDatabaseHas('family_members', [
            'id' => $adminMember->id,
        ]);
    }
}
