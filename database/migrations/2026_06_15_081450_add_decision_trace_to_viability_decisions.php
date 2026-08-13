<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coluna ADITIVA decision_trace (HU-099 RN-004/RN-005): o snapshot passo a
     * passo da decisão (entrada → risco → LOUOS Quadro 7/10/11/11A →
     * consolidação → desfecho, por CNAE) que hoje vive no consulta_array mas é
     * DESCARTADO. Gravada UMA única vez na criação da decisão (imutável por
     * convenção, como per_cnae) — a explicabilidade (12-05) PROJETA este trace
     * sem nunca recomputar o motor. Nullable: decisões LEGADAS (anteriores a
     * esta fase) ficam null e degradam honestas ("não registrado nesta decisão").
     *
     * jsonb espelha per_cnae/rules_versions/fundamentacao (portável: jsonb em
     * Postgres, json em SQLite). NÃO altera nenhuma coluna existente.
     */
    public function up(): void
    {
        Schema::table('viability_decisions', function (Blueprint $table) {
            $table->jsonb('decision_trace')->nullable()->after('fundamentacao');
        });
    }

    public function down(): void
    {
        Schema::table('viability_decisions', function (Blueprint $table) {
            $table->dropColumn('decision_trace');
        });
    }
};
