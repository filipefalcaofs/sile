<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Território MATERIALIZADO no processo (Onda GIS): com o acesso full ao
     * GIS/SEDUR validado em produção, a zona oficial (GeoServer WFS) e o bairro
     * oficial (camada GeoSalvador) são gravados na identificação do imóvel —
     * write-once, auditável, base dos relatórios territoriais por zona/bairro
     * canônico. Nullable: processos sem polígono ou com base indisponível
     * ficam sem valor (degradação honesta — nunca um valor inventado).
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->string('zona_codigo')->nullable()->index()->after('property_polygon_geojson');
            $table->string('bairro_oficial')->nullable()->index()->after('zona_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropIndex(['zona_codigo']);
            $table->dropIndex(['bairro_oficial']);
            $table->dropColumn(['zona_codigo', 'bairro_oficial']);
        });
    }
};
