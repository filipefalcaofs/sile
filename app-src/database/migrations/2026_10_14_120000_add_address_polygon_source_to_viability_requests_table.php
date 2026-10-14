<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fonte do polígono do lote (chave, semap ou ponto). Escrita pelo sistema
     * junto com address_polygon_geojson, nunca pelo requerente.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->string('address_polygon_source', 20)->nullable()->after('address_polygon_consulted_at');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn('address_polygon_source');
        });
    }
};
