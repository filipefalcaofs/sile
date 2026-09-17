<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recepção durável do POST /api_integracao/recebe (Guia Técnico REGIN).
 * Ack 3 só depois do persist; 5 se o protocolo já existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regin_recebimentos', function (Blueprint $table): void {
            $table->id();
            $table->string('protocolo')->unique();
            $table->string('cnpj_destino')->nullable();
            $table->string('cnpj_empresa')->nullable();
            $table->string('cnpj_origem')->nullable();
            $table->unsignedInteger('cod_funcao')->nullable();
            $table->string('nire')->nullable();
            $table->string('servico')->nullable();
            $table->timestamp('data_geracao')->nullable();
            $table->json('corpo')->nullable();
            $table->json('envelope');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regin_recebimentos');
    }
};
