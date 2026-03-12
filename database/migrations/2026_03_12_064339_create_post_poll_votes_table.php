<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_poll_votes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('poll_id')->constrained('post_polls')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('post_poll_options')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['poll_id', 'option_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_poll_votes');
    }
};
