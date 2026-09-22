<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cadastro de zonas urbanísticas da LOUOS (parametrização 3.3): a fonte
     * de verdade da zona é a própria lei — o cadastro nasce dos valores
     * distintos de `zona` da versão vigente do Quadro 10 (ZonaSeeder) e
     * passa a ser administrável (HU-014). A publicação de rascunho do
     * Quadro 10 valida que toda zona referenciada existe aqui e está ativa:
     * um typo vira bloqueio explícito, nunca `nao_encontrado` silencioso no
     * motor. TABULAR (sem PostGIS): roda em SQLite.
     */
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nome');
            $table->string('macrozona')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};
