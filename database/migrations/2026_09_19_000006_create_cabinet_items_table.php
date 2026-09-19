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
        Schema::create('cabinet_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->string('custom_name');
            $table->string('batch_number')->nullable();
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date');
            $table->decimal('quantity', 8, 2)->default(0);
            $table->string('unit', 30)->default('viên');
            $table->string('storage_location')->nullable();
            $table->decimal('price_per_unit', 12, 2)->nullable();
            $table->string('status', 20)->default('con_han'); // con_han, sap_het_han, qua_han, het_hang
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['family_id', 'expiry_date']);
            $table->index(['family_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabinet_items');
    }
};
