<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmação do requerente para a exclusão do CNAE gatilho da sede de
 * escritório virtual (RN-AA-04): excluir esse CNAE retira a condição de sede
 * e derruba o vínculo de todas as abrigadas do endereço, então o sistema não
 * executa essa cascata sem confirmação explícita.
 *
 * Nulável de propósito, mesma razão de `wants_virtual_office_tenant`: null
 * significa "ainda não perguntado" e NUNCA equivale a confirmação. `false` é
 * a recusa (mantém a sede); só `true` autoriza a perda da condição cadastral.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->boolean('confirma_perda_condicao_sede')->nullable()->after('wants_virtual_office_tenant');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropColumn('confirma_perda_condicao_sede');
        });
    }
};
