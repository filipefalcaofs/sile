<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agregado por CNAE, restrito a dois escopos: Salvador e o total Brasil.
 *
 * Responde "em quais atividades Salvador é mais lenta que o país", que aponta
 * diretamente quais regras da LOUOS e quais gatilhos do expresso calibrar.
 *
 * O recorte é deliberado: guardar CNAE de todos os municípios daria ordem de
 * milhões de linhas. Gravado desde a primeira ingestão porque o backfill é
 * caro demais para ser refeito só para acrescentar esta dimensão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmark_activity_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('escopo', 20);
            $table->string('cnae', 7);
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->unsignedInteger('n')->default(0);
            $table->decimal('media', 12, 2)->nullable();
            $table->decimal('p50', 12, 2)->nullable();
            $table->decimal('p90', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['escopo', 'cnae', 'ano', 'mes']);
            $table->index(['escopo', 'ano', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_activity_metrics');
    }
};
