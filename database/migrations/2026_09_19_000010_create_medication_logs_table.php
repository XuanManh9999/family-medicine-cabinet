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
        Schema::create('medication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('medication_schedules')->cascadeOnDelete();
            $table->foreignId('family_member_id')->constrained('family_members')->cascadeOnDelete();
            $table->dateTime('scheduled_time');
            $table->dateTime('actual_taken_time')->nullable();
            $table->string('status', 20)->default('da_uong'); // da_uong, quen_uong, bo_qua
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['family_member_id', 'scheduled_time']);
            $table->index(['schedule_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_logs');
    }
};
