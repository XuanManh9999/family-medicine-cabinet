<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mua thuốc theo đơn & yêu cầu bổ sung:
     * - Lô thuốc trong tủ biết mình được mua cho thuốc nào trong đơn (để tính "đã mua / còn phải mua" và trừ kho đúng hộp)
     * - Yêu cầu bổ sung khi thuốc đang uống sắp hết, Bác sĩ kê tiếp / ngưng thuốc / đồng ý mua thêm
     */
    public function up(): void
    {
        Schema::table('cabinet_items', function (Blueprint $table) {
            $table->foreignId('prescription_item_id')->nullable()->after('medicine_id')
                ->constrained('prescription_items')->nullOnDelete();
            $table->decimal('purchased_quantity', 8, 2)->nullable()->after('quantity');
        });

        Schema::create('refill_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained('prescription_items')->nullOnDelete();
            $table->foreignId('cabinet_item_id')->nullable()->constrained('cabinet_items')->nullOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->foreignId('family_member_id')->nullable()->constrained('family_members')->nullOnDelete();
            $table->string('medicine_name');
            $table->string('unit', 30)->nullable();
            $table->string('source', 20); // schedule = thuốc đang uống theo lịch | cabinet = thuốc trong tủ sắp hết / hết hạn
            $table->decimal('current_stock', 8, 2)->nullable();
            $table->decimal('days_left', 8, 1)->nullable();
            $table->text('requester_note')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | stopped | rejected | fulfilled | cancelled
            $table->decimal('approved_quantity', 8, 2)->nullable();
            $table->date('extended_until')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['family_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refill_requests');

        Schema::table('cabinet_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prescription_item_id');
            $table->dropColumn('purchased_quantity');
        });
    }
};
