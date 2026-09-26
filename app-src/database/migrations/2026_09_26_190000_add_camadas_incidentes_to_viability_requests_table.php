<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventário das camadas geográficas do imóvel (logradouros, bairro, zona e
     * as poligonais das regras territoriais, com o elemento identificado em
     * cada uma). Escrito pelo sistema no protocolo e na decisão expressa.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->json('camadas_incidentes')->nullable()->after('property_location_source');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn('camadas_incidentes');
        });
    }
};
