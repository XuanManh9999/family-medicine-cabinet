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
        Schema::create('smart_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30); // near_expiry, expired, low_stock, schedule_reminder, drug_interaction
            $table->string('level', 20)->default('info'); // info, warning, danger
            $table->string('title');
            $table->text('content');
            $table->boolean('is_read')->default(false);
            $table->string('action_url')->nullable();
            $table->timestamps();

            $table->index(['family_id', 'is_read']);
            $table->index(['family_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_alerts');
    }
};
