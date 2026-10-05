<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viability_request_etapas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->string('etapa');
            $table->string('servico');
            $table->string('situacao');
            $table->text('motivo')->nullable();
            $table->unsignedInteger('tentativas')->default(0);
            $table->timestamp('primeira_falha_em')->nullable();
            $table->timestamp('ultima_tentativa_em')->nullable();
            $table->timestamp('proxima_tentativa_em')->nullable();
            $table->timestamp('esgotada_em')->nullable();
            $table->timestamps();

            $table->unique(['viability_request_id', 'etapa']);
            $table->index(['situacao', 'proxima_tentativa_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viability_request_etapas');
    }
};
