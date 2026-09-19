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
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_course_id')->constrained('treatment_courses')->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->string('medicine_name');
            $table->string('dosage');
            $table->string('frequency');
            $table->text('instructions')->nullable();
            $table->decimal('total_prescribed_quantity', 8, 2)->default(0);
            $table->string('unit', 30)->default('viên');
            $table->timestamps();

            $table->index('treatment_course_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
