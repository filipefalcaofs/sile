<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índice único que habilita o upsert idempotente do import do Quadro 7 por
     * (rule_version_id, cnae_code, area_min) — uma faixa por início de área em
     * cada versão de regra, espelhando o unique de risk_classifications. O 05-01
     * criou apenas o índice de leitura (rule_version_id, cnae_code); sem o
     * UNIQUE o ON CONFLICT do upsert não tem alvo em SQLite/PostgreSQL.
     */
    public function up(): void
    {
        Schema::table('louos_quadro7_faixas', function (Blueprint $table) {
            $table->unique(['rule_version_id', 'cnae_code', 'area_min']);
        });
    }

    public function down(): void
    {
        Schema::table('louos_quadro7_faixas', function (Blueprint $table) {
            $table->dropUnique(['rule_version_id', 'cnae_code', 'area_min']);
        });
    }
};
