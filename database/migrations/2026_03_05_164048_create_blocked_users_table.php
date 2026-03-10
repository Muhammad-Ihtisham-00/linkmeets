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
        Schema::create('blocked_users', function (Blueprint $table) {
           
            $table->id();

            // Kisne block kiya
            $table->foreignId('blocker_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Kise block kiya
            $table->foreignId('blocked_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamp('blocked_at');

            $table->timestamps();

            // Ek user dusre ko sirf ek baar block kar sakta
            $table->unique(['blocker_id', 'blocked_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_users');
    }
};
