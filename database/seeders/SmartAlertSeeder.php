<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\SmartAlert;
use App\Models\User;
use Illuminate\Database\Seeder;

class SmartAlertSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $family = Family::where('code', 'FAM-VN2026')->first();
        if (!$family) {
            return;
        }

        $adminUser = User::where('email', 'an.nguyen@gmail.com')->first();

        $alerts = [
            [
                'family_id' => $family->id,
                'user_id' => $adminUser?->id,
                'type' => 'expired',
                'level' => 'danger',
                'title' => 'CẢNH BÁO NGUY HIỂM: Thuốc đã quá hạn sử dụng!',
                'content' => 'Thuốc "Cephalexin 500mg" tại Góc tủ thuốc phòng khách đã hết hạn cách đây 20 ngày. Tuyệt đối không sử dụng và cần tiêu hủy an toàn ngay lập tức!',
                'is_read' => false,
                'action_url' => '/cabinet/items',
            ],
            [
                'family_id' => $family->id,
                'user_id' => $adminUser?->id,
                'type' => 'drug_interaction',
                'level' => 'danger',
                'title' => 'CẢNH BÁO TƯƠNG TÁC AI: Nguy cơ quá liều Paracetamol!',
                'content' => 'Hệ thống AI phát hiện tủ thuốc có đồng thời Panadol Extra và Decolgen Forte. Cả hai thuốc đều chứa Paracetamol. Tuyệt đối không cho người bệnh uống đồng thời vì sẽ gây hoại tử tế bào gan cấp tính.',
                'is_read' => false,
                'action_url' => '/ai/interaction-checker',
            ],
            [
                'family_id' => $family->id,
                'user_id' => $adminUser?->id,
                'type' => 'near_expiry',
                'level' => 'warning',
                'title' => 'Cảnh báo thuốc cận date (HSD dưới 30 ngày)',
                'content' => 'Gói thuốc "Acemuc 200mg" chỉ còn 18 ngày hạn sử dụng (hết hạn ngày ' . now()->addDays(18)->format('d/m/Y') . '). Hãy ưu tiên sử dụng trước hoặc có phương án thay thế.',
                'is_read' => false,
                'action_url' => '/cabinet/items?filter=near_expiry',
            ],
            [
                'family_id' => $family->id,
                'user_id' => $adminUser?->id,
                'type' => 'low_stock',
                'level' => 'warning',
                'title' => 'Vật tư y tế sắp hết hàng',
                'content' => '"Băng cá nhân Urgo Waterproof" trong hộp chỉ còn lại 2 miếng gạc. Hãy lên danh sách mua bổ sung vào lần đi siêu thị tới.',
                'is_read' => true,
                'action_url' => '/cabinet/items?filter=low_stock',
            ],
            [
                'family_id' => $family->id,
                'user_id' => $adminUser?->id,
                'type' => 'schedule_reminder',
                'level' => 'info',
                'title' => 'Nhắc lịch uống thuốc hàng ngày',
                'content' => 'Nhắc cữ uống thuốc sáng 07:30 của Ông nội: Amlodipine 5mg (1 viên) sau khi ăn sáng no.',
                'is_read' => true,
                'action_url' => '/schedules',
            ],
        ];

        foreach ($alerts as $alert) {
            SmartAlert::updateOrCreate(
                [
                    'family_id' => $alert['family_id'],
                    'title' => $alert['title'],
                ],
                $alert
            );
        }
    }
}
