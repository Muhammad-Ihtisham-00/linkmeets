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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            // ─── LINKS ────────────────────────────────────────────────────
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignId('sender_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ─── MESSAGE TYPE ─────────────────────────────────────────────
            $table->enum('type', [
                'text',          // Normal message
                'image',         // Image / Gallery
                'document',      // PDF, Word etc
                'audio',         // Voice message
                'location',      // Pin location
                'live_location', // Live location sharing
            ])->default('text');

            // ─── CONTENT ──────────────────────────────────────────────────
            $table->text('body')->nullable();               // Text message

            // File based (image/document/audio)
            $table->string('file_path')->nullable();        // Storage path
            $table->string('file_name')->nullable();        // Original name
            $table->unsignedBigInteger('file_size')->nullable(); // Bytes
            $table->string('mime_type')->nullable();        // image/jpeg etc
            $table->integer('duration_seconds')->nullable();// Audio duration

            // Location based
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_name')->nullable();    // Address label

            // Live location specific
            $table->boolean('is_live_location')->default(false);
            $table->timestamp('live_location_expires_at')->nullable();
            $table->boolean('live_location_stopped')->default(false);

            // ─── DELETE ───────────────────────────────────────────────────
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();

            $table->timestamps();
        });

        // ─── NOW ADD FK to conversations.last_message_id ──────────────────
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('last_message_id')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['last_message_id']);
        });
        Schema::dropIfExists('messages');
    }
};
