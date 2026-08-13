<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ficha de análise versionada (HU-135/140) — o coração da análise humana e a
     * fonte do parecer. Cada revisão é uma linha; a revisão FINALIZADA é imutável
     * (RN-003) e o diff entre revisões compara 2 linhas. A rev 1 nasce pré-
     * analisada pelo motor (engine_snapshot/per_cnae) ou vazia quando o motor
     * está indisponível (engine_available=false, FA-01). per_cnae espelha a ficha
     * do legado SAPS (status escolhido×sugerido, grupo de uso, valor TLL,
     * gatilhos, condicionantes); parking guarda vagas req×exigido×vistoria.
     *
     * unique(viability_request_id, revision): garante uma revisão por número por
     * processo (versionamento sem colisão). Portável (json, sem geometria).
     */
    public function up(): void
    {
        Schema::create('analysis_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('viability_request_id')->index()->constrained('viability_requests')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('status')->default('rascunho');
            $table->foreignId('analyst_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Snapshot do motor (reuso do SolicitacaoViabilityResolver) e versões
            // de regras da época (RN-005). engine_available=false sinaliza a
            // degradação honesta (ficha vazia, o humano decide assim mesmo).
            $table->jsonb('engine_snapshot')->nullable();
            $table->jsonb('engine_rules_versions')->nullable();
            $table->boolean('engine_available')->default(true);

            // Conteúdo da ficha (espelha SAPS) preenchido/confirmado pelo analista.
            $table->jsonb('per_cnae')->nullable();
            $table->jsonb('conditions')->nullable();
            $table->jsonb('parking')->nullable();

            $table->text('parecer')->nullable();
            $table->timestamp('finalized_at')->nullable();

            $table->timestamps();

            $table->unique(['viability_request_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_records');
    }
};
