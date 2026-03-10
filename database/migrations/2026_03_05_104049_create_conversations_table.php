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
        Schema::create('conversations', function (Blueprint $table) {

            $table->id();

            // ─── TYPE ─────────────────────────────────────────────────────
            $table->enum('type', [
                'private',  // 2 users k beech
                'group',    // Multiple users
            ])->default('private');

            // ─── GROUP SPECIFIC ───────────────────────────────────────────
            $table->string('name')->nullable();             // "Designer Group"
            $table->string('image')->nullable();            // Group image
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ─── APPOINTMENT LINK ─────────────────────────────────────────
            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();

            // ─── LAST MESSAGE (chat list sorting k liye) ──────────────────
            $table->unsignedBigInteger('last_message_id')->nullable();  // FK baad mein
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
