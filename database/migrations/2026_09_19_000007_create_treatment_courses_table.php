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
        Schema::create('treatment_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('family_member_id')->constrained('family_members')->cascadeOnDelete();
            $table->string('title');
            $table->string('hospital_doctor')->nullable();
            $table->text('diagnosis')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('prescription_image_path')->nullable();
            $table->json('ai_analysis_data')->nullable();
            $table->string('status', 30)->default('dang_dieu_tri'); // dang_dieu_tri, da_hoan_thanh, tam_dung
            $table->timestamps();

            $table->index(['family_id', 'family_member_id']);
            $table->index(['family_member_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_courses');
    }
};
