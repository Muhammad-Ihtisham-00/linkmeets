<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_find_experts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();

            $table->string('expertise_needed');
            $table->text('project_description');
            $table->text('key_requirements');
            $table->string('duration');
            $table->string('type'); // full-time, part-time, hourly etc.
            $table->string('budget')->nullable();
            $table->boolean('is_urgent')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_find_experts');
    }
};
