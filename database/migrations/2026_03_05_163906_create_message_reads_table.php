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
        Schema::create('message_reads', function (Blueprint $table) {
             $table->id();

            // ─── LINKS ────────────────────────────────────────────────────
            $table->foreignId('message_id')
                  ->constrained('messages')
                  ->cascadeOnDelete();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // ─── READ TIME ────────────────────────────────────────────────
            // Kab padha — blue tick ✓✓
            $table->timestamp('read_at');

            $table->timestamps();

            // Ek user ek message sirf ek baar read kare
            $table->unique(['message_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_reads');
    }
};
