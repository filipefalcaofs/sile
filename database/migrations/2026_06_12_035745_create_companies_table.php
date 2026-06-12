<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cadastro empresarial (HU-023). CNPJ normalizado em string(14) SEMPRE —
     * o CNPJ alfanumérico (IN RFB 2.229/2024) entra em produção em julho/2026;
     * tipo numérico quebraria letras e perderia zeros à esquerda. Endereço como
     * texto achatado nesta fase (geocodificação é Fase 4). source registra a
     * origem de criação (manual|redesim), imutável.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('cnpj', 14)->unique();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('legal_nature_code', 4)->nullable();
            $table->string('legal_nature')->nullable();
            $table->string('size_code', 2)->nullable();
            $table->string('size')->nullable();
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip_code', 8)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->default('manual');
            $table->string('redesim_protocol')->nullable();
            $table->timestamp('redesim_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
