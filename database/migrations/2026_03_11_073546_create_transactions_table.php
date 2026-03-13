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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // ─── WALLET ───────────────────────────────────────────────────
            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete();

            // ─── USERS ────────────────────────────────────────────────────
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Send money mein receiver
            $table->foreignId('receiver_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ─── TYPE ─────────────────────────────────────────────────────
            $table->enum('type', [
                'add_money',    // Add money to wallet
                'send_money',   // User to user transfer
                'withdraw',     // Withdraw to bank/paypal
                'earning',      // Appointment payment received
                'refund',       // Refund on cancellation
                'fee',          // Platform fee deduction
            ]);

            // ─── AMOUNT ───────────────────────────────────────────────────
            $table->decimal('amount', 12, 2);               // Transaction amount
            $table->decimal('fee', 12, 2)->default(0.00);   // Platform fee
            $table->decimal('net_amount', 12, 2);           // amount - fee
            $table->string('currency', 3)->default('USD');

            // ─── DESCRIPTION ──────────────────────────────────────────────
            // Figma: "Consultation With Dr. Sarah Johnson"
            $table->string('description')->nullable();
            $table->string('reference_number')->unique(); // TXN-XXXXXXXX

            // ─── LINKS ────────────────────────────────────────────────────
            // Appointment se linked transaction
            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();

            // Payment method used
            $table->foreignId('payment_method_id')
                ->nullable()
                ->constrained('payment_methods')
                ->nullOnDelete();

            // ─── GATEWAY ──────────────────────────────────────────────────
            $table->string('gateway_transaction_id')->nullable(); // Stripe/PayPal TXN ID
            $table->string('gateway_status')->nullable();         // Gateway response status
            $table->json('gateway_response')->nullable();         // Full gateway response

            // ─── STATUS ───────────────────────────────────────────────────
            $table->enum('status', [
                'pending',    // Processing
                'completed',  // Done
                'failed',     // Failed
                'refunded',   // Refunded
                'cancelled',  // Cancelled
            ])->default('pending');

            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
