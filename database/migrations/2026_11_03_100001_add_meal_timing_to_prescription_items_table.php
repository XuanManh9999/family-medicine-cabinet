<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lưu thời điểm uống so với bữa ăn ngay trên từng thuốc của đơn
     * (trước đây chỉ nằm trong lịch uống nên form sửa đơn bị mất thông tin này)
     */
    public function up(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->string('meal_timing', 30)->nullable()->after('unit'); // truoc_an, sau_an, trong_khi_an, khong_phu_thuoc
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn('meal_timing');
        });
    }
};
