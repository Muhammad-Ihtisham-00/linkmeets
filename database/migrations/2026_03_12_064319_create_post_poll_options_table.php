<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_poll_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('poll_id')->constrained('post_polls')->cascadeOnDelete();

            $table->string('option_text');
            $table->unsignedInteger('votes_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_poll_options');
    }
};
