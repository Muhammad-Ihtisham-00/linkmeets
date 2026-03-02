<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('privacy_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('profile_private')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->boolean('allow_tagging')->default(true);
            $table->tinyInteger('post_visibility')->default(1)->comment('1=everyone, 2=friends, 3=only_me');
            $table->tinyInteger('email_visibility')->default(1)->comment('1=everyone, 2=friends, 3=only_me');
            $table->tinyInteger('phone_visibility')->default(1)->comment('1=everyone, 2=friends, 3=only_me');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('privacy_settings');
    }
};
