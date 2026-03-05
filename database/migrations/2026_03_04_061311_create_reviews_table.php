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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            // ─── LINKS ────────────────────────────────────────────────────
            // Konsi appointment ka review hai
            $table->foreignId('appointment_id')
                ->constrained('appointments')
                ->cascadeOnDelete();

            // Konsi service ka review hai
            $table->foreignId('service_id')
                ->nullable()
                ->constrained('services')
                ->nullOnDelete();

            // Kisne review diya (client)
            $table->foreignId('reviewer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Kisko review mila (provider)
            $table->foreignId('reviewee_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ─── REVIEW CONTENT ───────────────────────────────────────────
            $table->tinyInteger('rating');               // 1 to 5 stars
            $table->text('review_text')->nullable();
            $table->boolean('would_recommend')->nullable();

            $table->timestamps();

            // Ek appointment ka sirf ek hi review
            $table->unique(['appointment_id', 'reviewer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
