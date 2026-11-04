<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Đề nghị nhập thuốc rút gọn cho Chủ hộ: ảnh vỏ hộp để Bác sĩ đối chiếu,
     * thuốc mua cho ai, ghi chú của người gửi, hoạt chất AI đọc được từ vỏ hộp
     */
    public function up(): void
    {
        Schema::table('cabinet_requests', function (Blueprint $table) {
            $table->json('box_image_paths')->nullable()->after('notes');
            $table->foreignId('family_member_id')->nullable()->after('requested_by')
                ->constrained('family_members')->nullOnDelete();
            $table->text('requester_note')->nullable()->after('box_image_paths');
            $table->string('active_ingredient')->nullable()->after('custom_name');
        });
    }

    public function down(): void
    {
        Schema::table('cabinet_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('family_member_id');
            $table->dropColumn(['box_image_paths', 'requester_note', 'active_ingredient']);
        });
    }
};
