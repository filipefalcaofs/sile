<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ponto do imóvel quando não há polígono (inscrição no Cadastro SEFAZ ou
     * ponto do catálogo da simulação) e a origem da localização. Nullable:
     * processo sem inscrição localizada fica sem ponto — nunca um ponto
     * inventado. Fora do fillable: só o sistema grava.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->decimal('property_point_lat', 10, 7)->nullable()->after('cod_log');
            $table->decimal('property_point_lng', 10, 7)->nullable()->after('property_point_lat');
            $table->string('property_location_source', 30)->nullable()->after('property_point_lng');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn(['property_point_lat', 'property_point_lng', 'property_location_source']);
        });
    }
};
