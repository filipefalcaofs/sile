<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agregado do tempo de viabilidade de endereço por município e competência,
 * apurado a partir dos microdados da REDESIM.
 *
 * Só o agregado persiste: o arquivo de microdados é processado em streaming e
 * descartado. Guardar o cru de 27 UFs por 96 competências seria inviável, e o
 * agregado responde a tudo que as telas perguntam.
 *
 * O município é identificado por UF mais nome normalizado — a base federal não
 * traz código IBGE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmark_municipality_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('uf', 2);
            $table->string('municipio', 150);
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->unsignedInteger('n')->default(0);
            $table->decimal('media', 12, 2)->nullable();
            $table->decimal('p50', 12, 2)->nullable();
            $table->decimal('p75', 12, 2)->nullable();
            $table->decimal('p90', 12, 2)->nullable();
            $table->decimal('p95', 12, 2)->nullable();
            $table->decimal('p99', 12, 2)->nullable();
            $table->decimal('maximo', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['uf', 'municipio', 'ano', 'mes']);
            $table->index(['ano', 'mes', 'media']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_municipality_metrics');
    }
};
