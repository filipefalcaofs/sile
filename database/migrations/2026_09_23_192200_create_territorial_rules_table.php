<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regras territoriais (RT-01 a RT-05 — pacote normativo SEDUR 2026-08-28)
     * como dado versionado: cada regra referencia uma rule_version do domínio
     * `territorial` (mesmo cabeçalho dos quadros LOUOS). O tipo define o
     * casamento (logradouro × CNAE, poligonal, allow-list) e o efeito define o
     * desfecho (indeferir, encaminhar à análise, allow-list de expresso,
     * restrição de uso por via). Alvo da regra conforme o tipo: cod_log /
     * logradouro_nome (endereço), camada + camada_filtro (poligonal WFS, ex.:
     * ABM trechos 5/6) ou zona_alvo (observação do Quadro 10).
     */
    public function up(): void
    {
        Schema::create('territorial_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions');
            $table->string('codigo')->index();
            $table->string('titulo');
            $table->string('tipo');
            $table->string('efeito');
            $table->string('cod_log')->nullable()->index();
            $table->string('logradouro_nome')->nullable();
            $table->string('camada')->nullable();
            $table->string('camada_filtro')->nullable();
            $table->string('zona_alvo')->nullable();
            $table->text('motivo_analise')->nullable();
            $table->foreignId('decision_text_id')->nullable()->constrained('decision_texts');
            $table->string('base_legal')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['rule_version_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('territorial_rules');
    }
};
