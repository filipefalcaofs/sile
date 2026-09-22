<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Condicionante operacionalizada como PERGUNTA ao requerente cuja resposta
     * reclassifica o risco (mecanismo "DI" — ex.: produto não artesanal vira
     * Alto Risco; HU-019/HU-048, RN-004/005/008). Ligada a uma versão de regra
     * (rule_versions); cnae_code é nullable porque a condicionante pode ser
     * geral (sem CNAE) ou específica de uma subclasse. regra_reclassificacao é
     * jsonb (shape variável do "DI") — vira json em SQLite. TABULAR: roda em
     * SQLite. A unicidade lógica (versão + cnae + pergunta) é garantida pelo
     * import via firstOrCreate (sem índice único, pois pergunta é texto longo).
     */
    public function up(): void
    {
        Schema::create('risk_condicionantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('cnae_code', 7)->nullable();
            $table->text('pergunta');
            $table->string('tipo_resposta')->default('booleano_sim_nao');
            $table->jsonb('regra_reclassificacao')->nullable();
            $table->text('texto_parecer')->nullable();
            $table->timestamps();
            $table->index(['rule_version_id', 'cnae_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_condicionantes');
    }
};
