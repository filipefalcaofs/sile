<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quadro 10 da LOUOS (HU-016/HU-039): permissão da atividade na zona
     * (permitido | permitido_condicionado | proibido). Ligada a uma versão de
     * regra (rule_versions, domínio louos_quadro10) reusada da Fase 6 — dado
     * versionado. TABULAR (sem PostGIS): roda em SQLite. grupo_uso casa com o
     * grupo do Quadro 7; condicionante_ref remete à condicionante urbanística
     * quando a permissão é condicionada; base_legal registra o dispositivo da
     * LOUOS. A zona vem do território (Fase 4, hoje indisponível/pendente
     * SEDUR) — sem zona, o motor degrada para análise, não consulta esta tabela.
     */
    public function up(): void
    {
        Schema::create('louos_quadro10_permissoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('zona');
            $table->string('grupo_uso');
            $table->string('subgrupo')->nullable();
            $table->string('permissao');
            $table->string('condicionante_ref')->nullable();
            $table->string('base_legal')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->index(['rule_version_id', 'zona', 'grupo_uso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('louos_quadro10_permissoes');
    }
};
