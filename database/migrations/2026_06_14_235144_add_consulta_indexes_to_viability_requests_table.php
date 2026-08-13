<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices de DESEMPENHO da consulta de processos (HU-082 RN-008 — resposta da
     * engenharia à reclamação central do legado "sistema muito lento"). Migration
     * ADITIVA: apenas adiciona índices nas colunas filtradas que ainda NÃO os
     * têm.
     *
     * NÃO duplica os já existentes: protocol_number (unique → indexado na criação
     * da tabela), status (index na criação) e as colunas de análise
     * sector_id/assigned_user_id/analysis_category/in_fine_mesh/analysis_due_at
     * (indexadas em 10-02). Cobre aqui inscrição imobiliária, bairro e o protocolo
     * BAP (external_reference) — campos do SAPS sem índice.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->index('property_registration');
            $table->index('address_neighborhood');
            $table->index('external_reference');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropIndex(['property_registration']);
            $table->dropIndex(['address_neighborhood']);
            $table->dropIndex(['external_reference']);
        });
    }
};
