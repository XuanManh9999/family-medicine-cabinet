<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nhắc uống thuốc tự động: mỗi cữ thuốc chỉ nhắc 1 lần cho mỗi giai đoạn
     * (due = đến giờ uống, missed = quá giờ chưa điểm danh)
     */
    public function up(): void
    {
        Schema::table('smart_alerts', function (Blueprint $table) {
            $table->foreignId('medication_schedule_id')->nullable()->after('cabinet_item_id')
                ->constrained('medication_schedules')->cascadeOnDelete();
        });

        Schema::create('medication_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_schedule_id')->constrained('medication_schedules')->cascadeOnDelete();
            $table->dateTime('scheduled_for');
            $table->string('stage', 20); // due | missed
            $table->foreignId('smart_alert_id')->nullable()->constrained('smart_alerts')->nullOnDelete();
            $table->string('emailed_to')->nullable();
            $table->timestamps();

            $table->unique(['medication_schedule_id', 'scheduled_for', 'stage'], 'medication_reminders_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_reminders');

        Schema::table('smart_alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('medication_schedule_id');
        });
    }
};
