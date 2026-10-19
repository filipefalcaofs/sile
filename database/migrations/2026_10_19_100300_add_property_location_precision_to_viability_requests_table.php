<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Se o ponto geocodificado casou o número do imóvel (numero) ou só a via.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->string('property_location_precision', 10)->nullable()->after('property_location_source');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn('property_location_precision');
        });
    }
};
