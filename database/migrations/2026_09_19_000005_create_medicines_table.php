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
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('medicine_categories')->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('active_ingredient');
            $table->string('concentration')->nullable();
            $table->string('dosage_form')->nullable(); // viên nén, viên nang, dung dịch siro, gói bột...
            $table->string('default_unit', 30)->default('viên'); // viên, gói, chai, vỉ, lọ, ống
            $table->string('manufacturer')->nullable();
            $table->text('usage_instructions')->nullable();
            $table->text('side_effects')->nullable();
            $table->text('contraindications')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('active_ingredient');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
