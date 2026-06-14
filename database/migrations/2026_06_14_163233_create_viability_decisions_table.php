<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro IMUTÁVEL da decisão do fluxo expresso (HU-076/078), 1:1 com a
     * solicitação (unique viability_request_id) — append-only: o
     * FluxoExpressoService (09-05) grava uma única vez dentro da transação da
     * decisão e nunca atualiza por negócio. É a fonte do PDF/TVL (Fase 10) e da
     * explicabilidade (Fase 12): guarda o veredito consolidado, o veredito por
     * CNAE (RN-009), as versões de regras da época (RN-005) e a fundamentação
     * legal. tvl_product_number é único e preenchido SÓ no deferimento (RN-007).
     *
     * Portável (jsonb como em viability_requests; SEM geometria) — a suíte roda
     * em SQLite e a decisão usa o resultado dos motores, não SQL espacial.
     */
    public function up(): void
    {
        Schema::create('viability_decisions', function (Blueprint $table) {
            $table->id();

            // 1:1 com a solicitação — unique garante uma única decisão por processo.
            $table->foreignId('viability_request_id')
                ->constrained('viability_requests')
                ->cascadeOnDelete()
                ->unique();

            $table->string('flow')->default('expresso');

            // deferida/indeferida — em_analise/pendente NÃO criam decisão.
            $table->string('outcome');

            // ResultadoViabilidade consolidado (permitido/permitido_com_condicoes/nao_permitido).
            $table->string('consolidated_result');

            // Número de produto TVL (RN-007): único, preenchido SÓ no deferimento.
            $table->string('tvl_product_number')->nullable()->unique();

            $table->jsonb('per_cnae');
            $table->jsonb('rules_versions');
            $table->jsonb('fundamentacao');

            // Motivo (ex.: 'indeferido sem atuação' na HU-134).
            $table->text('reason')->nullable();

            // null = decisão do sistema (fluxo automático).
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('decided_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viability_decisions');
    }
};
