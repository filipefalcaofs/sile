<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categoria de uso e padrão construtivo do lote na SEMAP. Informação do
     * cadastro municipal, escrita no protocolo. Não entra no motor.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->string('semap_use_category', 80)->nullable()->after('address_polygon_consulted_at');
            $table->string('semap_construction_standard', 40)->nullable()->after('semap_use_category');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn(['semap_use_category', 'semap_construction_standard']);
        });
    }
};
