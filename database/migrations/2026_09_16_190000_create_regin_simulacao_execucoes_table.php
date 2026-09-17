<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado persistido da simulação REGIN — só homologação do motor.
 * Não é decisão de processo (sem TVL, sem fluxo expresso real).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regin_simulacao_execucoes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique();
            $table->json('relatorio');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regin_simulacao_execucoes');
    }
};
