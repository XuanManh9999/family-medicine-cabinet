<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Đề nghị nhập thuốc vào tủ: Chủ hộ / Thành viên gửi, Bác sĩ hoặc Quản trị viên duyệt
     */
    public function up(): void
    {
        Schema::create('cabinet_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->string('custom_name');
            $table->string('batch_number')->nullable();
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date');
            $table->decimal('quantity', 8, 2);
            $table->string('unit', 30);
            $table->string('storage_location')->nullable();
            $table->decimal('price_per_unit', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('record_expense')->default(false);
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('cabinet_item_id')->nullable()->constrained('cabinet_items')->nullOnDelete();
            $table->foreignId('medicine_expense_id')->nullable()->constrained('medicine_expenses')->nullOnDelete();
            $table->timestamps();

            $table->index(['family_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabinet_requests');
    }
};
