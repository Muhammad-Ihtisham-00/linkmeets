<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();

            $table->string('event_name');
            $table->string('event_type');
            $table->date('event_date');
            $table->time('event_time');
            $table->boolean('is_online')->default(false);
            $table->string('location')->nullable();
            $table->text('description');
            $table->string('registration_info')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_events');
    }
};
