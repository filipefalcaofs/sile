<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_regra_ramos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->unsignedTinyInteger('regra');
            $table->unsignedTinyInteger('pergunta')->nullable();
            $table->boolean('resposta')->nullable();
            $table->string('faixa', 16)->nullable();
            $table->boolean('tipo_dirige')->nullable();
            $table->string('codigo_louos', 16)->nullable();
            $table->string('fluxo', 16);
            // Chave normalizada do ramo (NULLs viram '-') — o upsert por chave
            // é idempotente, o que colunas nullable no unique não garantem.
            $table->string('chave', 64);
            $table->timestamps();
            $table->unique(['rule_version_id', 'chave']);
            $table->index(['rule_version_id', 'regra']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_regra_ramos');
    }
};
