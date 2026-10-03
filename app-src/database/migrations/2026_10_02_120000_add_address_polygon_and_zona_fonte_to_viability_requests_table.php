<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Polígono do endereçamento licenciado (GeoServer SEDUR,
     * enderecamento:VM_ENDERECAMENTO) e a regra que decidiu a zona do
     * processo. Escritos pelo sistema no protocolo, nunca pelo requerente.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->json('address_polygon_geojson')->nullable()->after('property_polygon_geojson');
            $table->timestamp('address_polygon_consulted_at')->nullable()->after('address_polygon_geojson');
            $table->string('zona_fonte', 40)->nullable()->after('zona_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn(['address_polygon_geojson', 'address_polygon_consulted_at', 'zona_fonte']);
        });
    }
};
