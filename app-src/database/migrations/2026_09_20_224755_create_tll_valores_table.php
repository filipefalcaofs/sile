<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de valores da TLL (Taxa de Licença de Localização) por exercício —
     * dado versionado/auditado e administrável (HU-014/HU-071). O código TLL
     * (1.01, 2.02) liga ao enquadramento da planilha vigente; o valor monetário
     * e o mapeamento SEFAZ (código da taxa, código do serviço) alimentam o
     * cálculo do DAM (RN-004) e o bloco `taxas` enviado à SEFAZ. Um valor por
     * código por exercício (unique); inativar preserva o histórico (sem destroy).
     *
     * Portável (sem geometria): a suíte roda em SQLite.
     */
    public function up(): void
    {
        Schema::create('tll_valores', function (Blueprint $table) {
            $table->id();

            // Código TLL da planilha (1.01, 2.02) + exercício — um valor por
            // código por ano. unique já cria o índice da chave natural.
            $table->string('codigo_tll');
            $table->unsignedSmallInteger('exercicio');
            $table->unique(['codigo_tll', 'exercicio']);

            // Valor da TLL em R$ e a taxa de serviço (RN-004: o DAM é a
            // atividade de maior valor + a taxa de serviço).
            $table->decimal('valor', 10, 2);
            $table->decimal('taxa_servico', 10, 2)->default(0);

            // Mapeamento SEFAZ (contrato do bloco `taxas`): código da taxa,
            // código do serviço e a descrição do serviço.
            $table->string('codigo_tll_sefaz')->nullable();
            $table->string('codigo_servico_sefaz')->nullable();
            $table->string('servico_sefaz')->nullable();

            // Permite inativar sem excluir (preserva o histórico/auditoria).
            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tll_valores');
    }
};
