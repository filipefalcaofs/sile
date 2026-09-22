<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classificação de risco MUNICIPAL por subclasse CNAE (HU-020/HU-047),
     * ligada a uma versão de regra (rule_versions, domínio risco_municipal) —
     * dado versionado, nunca código. TABULAR (sem PostGIS): roda em SQLite.
     * cnae_code é FK lógica para Cnae.code (dígitos, mesma normalização). As
     * condicionantes gerais do Decreto vêm parseadas do CSV (jsonb — vira json
     * em SQLite). unique(rule_version_id, cnae_code) garante uma classificação
     * por CNAE em cada versão e habilita o upsert idempotente do import.
     */
    public function up(): void
    {
        Schema::create('risk_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('cnae_code', 7);
            $table->string('risco_municipal');
            $table->jsonb('condicionantes')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->unique(['rule_version_id', 'cnae_code']);
            $table->index('cnae_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_classifications');
    }
};
