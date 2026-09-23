<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Endereços vinculados a uma regra territorial: a allow-list de Itaigara
     * (CODLOG + número de porta + rótulo do empreendimento) e as allow-lists
     * das observações (a) e (b) do Quadro 10 — por via (CODLOG) ou por bairro
     * (a observação (a) permite nas ZCMu-2 da Estrada Velha do Aeroporto E dos
     * bairros Calçada e São Cristóvão). cod_log é nullable porque a fonte
     * legal omite o código em uma entrada (Hiperideal — OPEN-RT-2); nesse
     * caso o casamento cai no nome normalizado do logradouro.
     */
    public function up(): void
    {
        Schema::create('territorial_rule_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('territorial_rule_id')->constrained('territorial_rules')->cascadeOnDelete();
            $table->string('rotulo')->nullable();
            $table->string('cod_log')->nullable();
            $table->string('logradouro_nome')->nullable();
            $table->string('numero_porta')->nullable();
            $table->string('bairro')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('territorial_rule_addresses');
    }
};
