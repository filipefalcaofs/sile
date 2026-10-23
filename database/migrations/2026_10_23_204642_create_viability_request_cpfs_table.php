<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CPFs que podem ver o processo além do solicitante: sócios do REGIN e o
     * solicitante declarado da empresa a constituir. Indexado por CPF para a
     * consulta de processos do Portal de Serviços.
     */
    public function up(): void
    {
        Schema::create('viability_request_cpfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->string('cpf', 11)->index();
            $table->string('papel', 20);
            $table->timestamps();

            $table->unique(['viability_request_id', 'cpf', 'papel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viability_request_cpfs');
    }
};
