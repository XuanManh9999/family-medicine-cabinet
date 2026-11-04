<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mọi lô thuốc gia đình mua về đều phải được Bác sĩ xác nhận mới được dùng:
     * pending_review (chờ xác nhận) → approved (được dùng) | returned (trả lại, phải đổi / mua lại).
     * Dữ liệu cũ đã nằm trong tủ được coi là đã duyệt.
     */
    public function up(): void
    {
        Schema::table('cabinet_items', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('approved')->after('status');
            $table->foreignId('purchased_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('purchased_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('review_note')->nullable()->after('approved_at');
            $table->json('box_image_paths')->nullable()->after('review_note');
            $table->foreignId('refill_request_id')->nullable()->after('prescription_item_id')
                ->constrained('refill_requests')->nullOnDelete();

            $table->index(['family_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::table('cabinet_items', function (Blueprint $table) {
            $table->dropIndex(['family_id', 'approval_status']);
            $table->dropConstrainedForeignId('refill_request_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('purchased_by');
            $table->dropColumn(['approval_status', 'approved_at', 'review_note', 'box_image_paths']);
        });
    }
};
