<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biblioteca de textos-padrão do parecer (HU-085) — administrável: o analista
     * insere trechos pré-aprovados ao redigir o parecer. category agrupa por tema
     * (índice), active liga/desliga sem excluir e version permite evoluir o texto
     * mantendo o histórico (dados versionados, não código).
     */
    public function up(): void
    {
        Schema::create('standard_texts', function (Blueprint $table) {
            $table->id();
            $table->string('category')->index();
            $table->text('content');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_texts');
    }
};
