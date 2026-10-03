<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controle e evidência da ingestão do benchmark federal, uma linha por UF e
 * competência. Guarda URL, bytes, hash e contagem de linhas porque o número
 * publicado ao gestor precisa ser rastreável até o arquivo que o originou.
 *
 * A unicidade de (uf, ano, mes) é o que torna a ingestão idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmark_ingestions', function (Blueprint $table) {
            $table->id();
            $table->string('uf', 2);
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->string('status', 20);
            $table->string('url');
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('hash', 64)->nullable();
            $table->unsignedInteger('linhas_lidas')->default(0);
            $table->unsignedInteger('linhas_descartadas')->default(0);
            $table->text('motivo')->nullable();
            $table->timestamps();

            $table->unique(['uf', 'ano', 'mes']);
            $table->index(['ano', 'mes', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_ingestions');
    }
};
