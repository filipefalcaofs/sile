<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Edificações complementares de Salvador (Overture, Microsoft e geradas
     * por IA), recortadas pelo limite do município. A geometria é gravada via
     * ST_* (nunca mass-assign). Ativa = retired_at e duplicate_of_id nulos.
     */
    public function up(): void
    {
        Schema::create('building_footprints', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);
            $table->string('source_id', 100);
            $table->string('release', 150);
            $table->geometry('geometry', subtype: 'multipolygon', srid: 4326);
            $table->decimal('area_m2', 12, 2);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->jsonb('source_datasets')->nullable();
            $table->jsonb('properties')->nullable();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('building_footprints')->nullOnDelete();
            $table->foreignId('import_id')->nullable()->constrained('building_footprint_imports')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->jsonb('validation_notes')->nullable();
            $table->timestamps();
            $table->unique(['source', 'source_id']);
        });

        // Índices espaciais e parciais só no PostgreSQL (SQLite da suíte não os aceita).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX building_footprints_geometry_gist ON building_footprints USING GIST (geometry)');
            DB::statement('CREATE INDEX building_footprints_ativas_gist ON building_footprints USING GIST (geometry) WHERE retired_at IS NULL AND duplicate_of_id IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('building_footprints');
    }
};
