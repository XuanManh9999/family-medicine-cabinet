<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FamilySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Tạo Gia đình mẫu
        $family = Family::updateOrCreate(
            ['code' => 'FAM-VN2026'],
            [
                'name' => 'Gia đình Bác Nguyễn Văn An',
                'address' => 'Số 18 Ngõ 34 Hoàng Cầu, Đống Đa, Hà Nội',
            ]
        );

        // 2. Tạo Tài khoản Người dùng
        $adminUser = User::updateOrCreate(
            ['email' => 'an.nguyen@gmail.com'],
            [
                'family_id' => $family->id,
                'name' => 'Nguyễn Văn An',
                'phone' => '0912345678',
                'role' => 'family_admin',
                'password' => Hash::make('password'),
            ]
        );

        $memberUser = User::updateOrCreate(
            ['email' => 'hoa.tran@gmail.com'],
            [
                'family_id' => $family->id,
                'name' => 'Trần Thị Hoa',
                'phone' => '0987654321',
                'role' => 'member',
                'password' => Hash::make('password'),
            ]
        );

        $family->update(['created_by' => $adminUser->id]);

        // 3. Tạo Hồ sơ Thành viên Gia đình
        // Ông nội (74 tuổi)
        FamilyMember::updateOrCreate(
            [
                'family_id' => $family->id,
                'name' => 'Nguyễn Văn Thịnh',
            ],
            [
                'user_id' => null, // Người cao tuổi không dùng smartphone
                'relationship' => 'ong_ba',
                'dob' => '1952-04-12',
                'gender' => 'nam',
                'blood_type' => 'O',
                'allergies' => null,
                'underlying_diseases' => 'Tăng huyết áp vô căn độ 2, Đái tháo đường type 2 (10 năm), Thoái hóa đốt sống cổ',
            ]
        );

        // Bố (44 tuổi) - gắn với tài khoản an.nguyen@gmail.com
        FamilyMember::updateOrCreate(
            [
                'family_id' => $family->id,
                'name' => 'Nguyễn Văn An',
            ],
            [
                'user_id' => $adminUser->id,
                'relationship' => 'bo_me',
                'dob' => '1982-08-15',
                'gender' => 'nam',
                'blood_type' => 'A',
                'allergies' => null,
                'underlying_diseases' => 'Đau mỏi vai gáy dân văn phòng, Gan nhiễm mỡ độ 1',
            ]
        );

        // Mẹ (41 tuổi) - gắn với tài khoản hoa.tran@gmail.com
        FamilyMember::updateOrCreate(
            [
                'family_id' => $family->id,
                'name' => 'Trần Thị Hoa',
            ],
            [
                'user_id' => $memberUser->id,
                'relationship' => 'vo_chong',
                'dob' => '1985-11-20',
                'gender' => 'nu',
                'blood_type' => 'B',
                'allergies' => 'Dị ứng thuốc nhóm NSAID (Aspirin, Ibuprofen, Diclofenac - gây phù mạch và khó thở)',
                'underlying_diseases' => 'Viêm loét dạ dày tá tràng HP (-)',
            ]
        );

        // Con gái nhỏ (7 tuổi)
        FamilyMember::updateOrCreate(
            [
                'family_id' => $family->id,
                'name' => 'Nguyễn Mai Anh',
            ],
            [
                'user_id' => null,
                'relationship' => 'con_cai',
                'dob' => '2019-06-10',
                'gender' => 'nu',
                'blood_type' => 'O',
                'allergies' => null,
                'underlying_diseases' => 'Dễ viêm họng amidan và ho đờm khi thời tiết giao mùa',
            ]
        );
    }
}
