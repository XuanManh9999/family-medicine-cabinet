<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Quản trị viên hệ thống là tài khoản trung tâm, không thuộc hộ gia đình nào:
     * gỡ liên kết gia đình và hồ sơ thành viên (hồ sơ sức khỏe vẫn được giữ lại)
     */
    public function up(): void
    {
        $adminIds = DB::table('users')->where('role', 'system_admin')->pluck('id');
        if ($adminIds->isEmpty()) {
            return;
        }

        DB::table('family_members')->whereIn('user_id', $adminIds)->update(['user_id' => null]);
        DB::table('users')->whereIn('id', $adminIds)->update(['family_id' => null]);
    }

    public function down(): void
    {
        // Không khôi phục: không còn biết tài khoản quản trị từng thuộc gia đình nào
    }
};
