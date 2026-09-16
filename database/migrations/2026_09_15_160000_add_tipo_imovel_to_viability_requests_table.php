<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de imóvel na solicitação vem do REGIN (SEDUR 2026-08-31 / PO 2026-09-15).
 * raw = valor recebido; normalized = código reconhecido ou null quando
 * desconhecido/ausente. Fora do fillable — o cidadão não preenche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->string('tipo_imovel')->nullable()->after('property_registration');
            $table->string('tipo_imovel_normalized')->nullable()->after('tipo_imovel');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropColumn(['tipo_imovel', 'tipo_imovel_normalized']);
        });
    }
};
