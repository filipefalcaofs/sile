<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classificação de risco SANITÁRIO por subclasse CNAE (planilha VISA),
     * dimensão SEPARADA do risco municipal — tabela própria, ligada a uma
     * versão de regra (rule_versions, domínio risco_sanitario). Dado
     * versionado, nunca código (HU-019/HU-047/HU-048). TABULAR (sem PostGIS):
     * roda em SQLite. cnae_code é FK lógica para Cnae.code (dígitos, mesma
     * normalização). unique(rule_version_id, cnae_code) garante uma
     * classificação por CNAE em cada versão e habilita o upsert idempotente do
     * import.
     */
    public function up(): void
    {
        Schema::create('risk_sanitary_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('cnae_code', 7);
            $table->string('risco_sanitario');
            $table->string('macroarea')->nullable();
            $table->boolean('autorizado_escritorio_virtual')->default(false);
            $table->boolean('autorizado_mei')->default(false);
            $table->boolean('exige_rt')->default(false);
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->unique(['rule_version_id', 'cnae_code']);
            $table->index('cnae_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_sanitary_classifications');
    }
};
