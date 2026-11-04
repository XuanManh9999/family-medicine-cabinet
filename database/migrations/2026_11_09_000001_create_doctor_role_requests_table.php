<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chủ hộ không tự gán vai trò Bác sĩ: gửi đề nghị kèm thông tin hành nghề, Quản trị viên hệ thống xác minh rồi duyệt
     */
    public function up(): void
    {
        Schema::create('doctor_role_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('credentials'); // Nơi công tác, chuyên khoa, số chứng chỉ hành nghề...
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'family_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_role_requests');
    }
};
