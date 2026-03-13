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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            // ─── OWNER ────────────────────────────────────────────────────
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // ─── BALANCES ─────────────────────────────────────────────────
            $table->decimal('available_balance', 12, 2)->default(0.00); // $9807.98
            $table->decimal('pending_balance', 12, 2)->default(0.00);   // $160.69
            $table->decimal('total_earning', 12, 2)->default(0.00);     // $893678.56
            $table->decimal('total_withdrawn', 12, 2)->default(0.00);   // Total withdrawn ever

            // ─── CURRENCY ─────────────────────────────────────────────────
            $table->string('currency', 3)->default('USD');

            // ─── STATUS ───────────────────────────────────────────────────
            $table->boolean('is_active')->default(true);
            $table->boolean('is_frozen')->default(false);  // Admin freeze kar sakta hai

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
