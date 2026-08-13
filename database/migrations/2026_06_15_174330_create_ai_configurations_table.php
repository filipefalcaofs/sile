<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider');            // openai | anthropic | gemini | azure | compativel
            $table->string('capability')->default('text'); // text | vision | embeddings
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable();   // criptografada (RN-009) — nunca em claro
            $table->string('model');
            $table->decimal('temperature', 3, 2)->default(0.10);
            $table->unsignedInteger('max_tokens')->default(4096);
            $table->unsignedInteger('timeout_ms')->default(60000);
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['capability', 'is_default']);
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_configurations');
    }
};
