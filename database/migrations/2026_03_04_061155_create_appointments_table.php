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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            // ─── USERS ────────────────────────────────────────────────────
            // Jo appointment book kar raha hai
            $table->foreignId('client_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Jis user se appointment book ho rahi hai
            $table->foreignId('provider_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ─── SERVICE ──────────────────────────────────────────────────
            // type / price / duration_minutes sab yahan se aayega
            $table->foreignId('service_id')
                ->nullable()
                ->constrained('services')
                ->nullOnDelete();

            // ─── APPOINTMENT INFO ─────────────────────────────────────────
            $table->string('full_name');
            $table->text('reason')->nullable();

            $table->date('appointment_date');
            $table->time('start_time');
            $table->time('end_time');

            // ─── EVENT SPECIFIC ───────────────────────────────────────────
            // Sirf service type = 'event' k liye
            $table->string('event_location_name')->nullable();
            $table->text('event_address')->nullable();
            $table->decimal('event_distance_km', 8, 2)->nullable();
            $table->string('event_image')->nullable();

            // ─── CALL SPECIFIC ────────────────────────────────────────────
            // Sirf service type = 'voice_call' ya 'video_call' k liye
            $table->string('call_channel_id')->nullable();
            $table->timestamp('call_started_at')->nullable();
            $table->timestamp('call_ended_at')->nullable();
            $table->integer('call_duration_seconds')->nullable();
            $table->boolean('is_recording_enabled')->default(false);

            // ─── STATUS ───────────────────────────────────────────────────
            $table->enum('status', [
                'pending',
                'upcoming',
                'completed',
                'cancelled',
                'rescheduled',
            ])->default('pending');

            // ─── CANCELLATION ─────────────────────────────────────────────
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ─── RESCHEDULE ───────────────────────────────────────────────
            $table->foreignId('rescheduled_from_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();
            $table->timestamp('rescheduled_at')->nullable();

            // ─── EXTRA ────────────────────────────────────────────────────
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
