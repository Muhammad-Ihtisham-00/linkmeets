<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reported_users', function (Blueprint $table) {
            $table->id();

            // Kisne report kiya
            $table->foreignId('reporter_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Kise report kiya
            $table->foreignId('reported_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Report reason
            $table->string('reason');
            $table->text('description')->nullable();        // Extra detail

            // Admin review
            $table->enum('status', [
                'pending',    // Admin ne dekha nahi
                'reviewed',   // Admin ne dekha
                'resolved',   // Action liya
                'dismissed',  // Ignore kiya
            ])->default('pending');

            $table->timestamp('reported_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reported_users');
    }
};
