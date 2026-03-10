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
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            // ─── OWNER ────────────────────────────────────────────────────
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ─── SERVICE TYPE (frontend dropdown se aayega) ───────────────
            $table->enum('type', [
                'messaging',
                'voice_call',
                'video_call',
                'event',
            ]);

            // ─── SERVICE DETAILS ──────────────────────────────────────────
            $table->string('title');                    // e.g. "Video Call With CEO"
            $table->string('description')->nullable();  // Short description

            // ─── PRICE & DURATION (user khud set karega) ─────────────────
            $table->decimal('price', 10, 2);            // e.g. 250.00
            $table->integer('duration_minutes');        // e.g. 30, 60, 90

            // ─── STATUS ───────────────────────────────────────────────────
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
