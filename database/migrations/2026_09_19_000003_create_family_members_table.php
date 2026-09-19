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
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('relationship', 30); // ong_ba, bo_me, vo_chong, con_cai, ban_than, khac
            $table->date('dob')->nullable();
            $table->string('gender', 10)->default('nam'); // nam, nu, khac
            $table->string('blood_type', 10)->nullable();
            $table->text('allergies')->nullable();
            $table->text('underlying_diseases')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamps();

            $table->index(['family_id', 'relationship']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
