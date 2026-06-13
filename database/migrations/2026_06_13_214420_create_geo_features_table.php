<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Geometrias de uma camada (HU-036). Coluna geometry(Geometry,4326)
     * GENÉRICA — aceita Polygon, MultiPolygon e LineString (bairro/zona/lote
     * são polígonos; via é LineString), SRID 4326 fixo (Pitfall 2/7). A
     * geometria é gravada via DB::raw/ST_* (nunca mass-assign).
     */
    public function up(): void
    {
        Schema::create('geo_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geo_layer_id')->constrained()->cascadeOnDelete();
            $table->geometry('geometry', subtype: 'geometry', srid: 4326);
            $table->jsonb('properties')->nullable();
            $table->timestamps();
        });

        // Índice espacial SÓ no PostgreSQL — em SQLite (:memory: da suíte) o
        // GiST quebraria a migração e derrubaria os 385 testes (Pitfall 1).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX geo_features_geometry_gist ON geo_features USING GIST (geometry)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_features');
    }
};
