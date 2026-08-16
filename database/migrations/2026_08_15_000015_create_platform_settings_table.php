<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('ai_enabled')->default(true);
            $table->string('ai_provider')->default('openai');
            $table->text('ai_api_key')->nullable();
            $table->string('ai_base_url')->nullable();
            $table->string('ai_model')->default('gpt-4o-mini');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
