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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();

            // ─── OWNER ────────────────────────────────────────────────────
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ─── TYPE ─────────────────────────────────────────────────────
            // Figma mein: PayPal, Google Pay, Apple Pay, Card
            $table->enum('type', [
                'paypal',
                'google_pay',
                'apple_pay',
                'card',
            ]);

            // ─── GATEWAY DETAILS ──────────────────────────────────────────
            $table->string('gateway_customer_id')->nullable();  // Stripe customer ID
            $table->string('gateway_method_id')->nullable();    // Stripe payment method ID
            $table->string('gateway_token')->nullable();        // PayPal token etc

            // ─── CARD SPECIFIC ────────────────────────────────────────────
            $table->string('card_brand')->nullable();           // Visa, Mastercard
            $table->string('card_last_four')->nullable();       // 5679
            $table->string('card_exp_month')->nullable();       // 12
            $table->string('card_exp_year')->nullable();        // 2027
            $table->string('card_holder_name')->nullable();

            // ─── PAYPAL SPECIFIC ──────────────────────────────────────────
            $table->string('paypal_email')->nullable();

            // ─── STATUS ───────────────────────────────────────────────────
            $table->boolean('is_connected')->default(true);    // Connected status
            $table->boolean('is_default')->default(false);     // Default method

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
