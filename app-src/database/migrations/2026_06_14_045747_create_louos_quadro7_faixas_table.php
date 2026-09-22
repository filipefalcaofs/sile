<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quadro 7 da LOUOS (HU-015/HU-038): enquadramento por faixa de área —
     * modelo "Enquadramento TVL" do SAPS (CNAE → faixa de área → grupo/subgrupo
     * de uso). Ligada a uma versão de regra (rule_versions, domínio
     * louos_quadro7) reusada da Fase 6 — dado versionado, nunca código. TABULAR
     * (sem PostGIS): roda em SQLite. cnae_code é FK lógica para Cnae.code
     * (dígitos, mesma normalização do import de risco). area_max nula = sem
     * limite superior ("qualquer área"). As faixas não-sobrepostas são validadas
     * na camada de aplicação (mantenedor/seed), não no banco.
     */
    public function up(): void
    {
        Schema::create('louos_quadro7_faixas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('cnae_code', 7);
            $table->string('grupo');
            $table->string('subgrupo')->nullable();
            $table->decimal('area_min', 12, 2)->default(0);
            $table->decimal('area_max', 12, 2)->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->index(['rule_version_id', 'cnae_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('louos_quadro7_faixas');
    }
};
