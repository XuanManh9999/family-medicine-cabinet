<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('medication_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('family_member_id')->constrained('family_members')->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained('prescription_items')->nullOnDelete();
            $table->string('medicine_name');
            $table->string('dosage');
            $table->string('unit', 30)->default('viên');
            $table->string('time_of_day', 20); // sang, trua, chieu, toi
            $table->time('scheduled_time'); // ví dụ 07:30:00
            $table->string('meal_timing', 30)->default('sau_an'); // truoc_an, sau_an, trong_khi_an, khong_phu_thuoc
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['family_member_id', 'is_active']);
            $table->index('scheduled_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_schedules');
    }
};
