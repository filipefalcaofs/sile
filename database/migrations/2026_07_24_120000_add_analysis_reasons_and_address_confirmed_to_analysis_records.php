<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paridade com o legado SAPS (HU-135, spec 2026-07-24): "Motivo de Análise"
 * (lista de texto livre do analista) e "Endereço correto?" (confirmação do
 * analista, sem HU formal — interpretação de baixo risco registrada na spec).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_records', function (Blueprint $table): void {
            $table->json('analysis_reasons')->nullable()->after('parecer');
            $table->boolean('address_confirmed')->nullable()->after('analysis_reasons');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_records', function (Blueprint $table): void {
            $table->dropColumn(['analysis_reasons', 'address_confirmed']);
        });
    }
};
