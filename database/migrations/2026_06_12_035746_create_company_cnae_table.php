<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot CNAEs da empresa (HU-025/HU-026). cnae_id com restrictOnDelete:
     * o banco impede excluir um CNAE vinculado a empresas (defesa da pendência
     * herdada da Fase 2). A invariante "exatamente um principal por empresa"
     * é garantida NA APLICAÇÃO pelo CompanyCnaeService em transação (precedente
     * [01-07]). unique(company_id, cnae_id) impede duplicar o CNAE na empresa.
     */
    public function up(): void
    {
        Schema::create('company_cnae', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cnae_id')->constrained()->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'cnae_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_cnae');
    }
};
