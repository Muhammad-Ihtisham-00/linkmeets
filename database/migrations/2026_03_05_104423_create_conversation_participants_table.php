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
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();

            // ─── LINKS ────────────────────────────────────────────────────
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ─── ROLE ─────────────────────────────────────────────────────
            // Group mein admin ya member
            $table->enum('role', ['admin', 'member'])->default('member');

            // ─── READ TRACKING ────────────────────────────────────────────
            // Is se unread count nikalta hai
            $table->timestamp('last_read_at')->nullable();

            // ─── STATUS ───────────────────────────────────────────────────
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();       // Group leave kiya

            // ─── MUTE ─────────────────────────────────────────────────────
            $table->boolean('is_muted')->default(false);
            $table->timestamp('muted_until')->nullable();

            $table->timestamps();

            // Ek user ek conversation mein sirf ek baar
            $table->unique(['conversation_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
