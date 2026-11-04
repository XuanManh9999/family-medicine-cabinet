<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - prescription_items.stopped_at: Bác sĩ cho ngưng thuốc → sửa đơn sau đó không làm thuốc uống lại
     * - cabinet_items.medicine_expense_id: chi phí ghi lúc "Đã mua" được gỡ nếu lô bị Bác sĩ trả lại (tránh tính 2 lần khi đổi thuốc)
     */
    public function up(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->date('stopped_at')->nullable()->after('meal_timing');
        });

        Schema::table('cabinet_items', function (Blueprint $table) {
            $table->foreignId('medicine_expense_id')->nullable()->after('refill_request_id')
                ->constrained('medicine_expenses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cabinet_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('medicine_expense_id');
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn('stopped_at');
        });
    }
};
