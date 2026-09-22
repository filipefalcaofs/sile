<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices de DESEMPENHO dos relatórios e indicadores (EP15 — Pitfall 1 do
     * RESEARCH): as colunas de data/decisão que os relatórios filtram e agregam
     * NÃO estavam indexadas. Sem isto, recortes por período (HU-123) e taxas/
     * tempo (HU-127/128/129/130/145) varrem a tabela inteira.
     *
     * Migration ADITIVA: só adiciona índices nas colunas filtradas que ainda
     * NÃO os têm — a regra Laravel de repetir todos os atributos ao alterar
     * coluna NÃO se aplica a addIndex.
     *
     * NÃO duplica índices já existentes:
     * - viability_requests: protocol_number/status (criação) + property_registration/
     *   address_neighborhood/external_reference (consulta 14) + análise (10-02).
     * - viability_decisions: viability_request_id (unique) e tvl_product_number (unique).
     * - viability_request_transitions: apenas a FK viability_request_id.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            // Recorte por período dos relatórios (HU-123 e SAPS — protocoled_at→decided_at).
            $table->index('protocoled_at');
        });

        Schema::table('viability_decisions', function (Blueprint $table) {
            // Janela temporal das taxas/tempo de decisão (HU-127/128/129).
            $table->index('decided_at');
            // Taxa de resposta expressa (HU-145): flow='expresso' + outcome.
            $table->index(['flow', 'outcome']);
            // Produtividade por analista (HU-130): agrupa por quem decidiu.
            $table->index('decided_by_user_id');
        });

        Schema::table('viability_request_transitions', function (Blueprint $table) {
            // Tempo por etapa (HU-129): pares de transições consecutivas por
            // processo ordenadas no tempo. Nome explícito porque o default
            // estoura o limite de identificador do PostgreSQL.
            $table->index(['viability_request_id', 'created_at'], 'vrt_request_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropIndex(['protocoled_at']);
        });

        Schema::table('viability_decisions', function (Blueprint $table) {
            $table->dropIndex(['decided_at']);
            $table->dropIndex(['flow', 'outcome']);
            $table->dropIndex(['decided_by_user_id']);
        });

        Schema::table('viability_request_transitions', function (Blueprint $table) {
            $table->dropIndex('vrt_request_created_index');
        });
    }
};
