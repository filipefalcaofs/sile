<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resultado da conferência do ponto da inscrição contra a rua do endereço
     * (confirmação, distância, logradouro e data). Escrito pelo sistema no
     * protocolo e pelo comando de correção; a ficha avisa quando nenhuma fonte
     * confirmou o local.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->json('localizacao_conferencia')->nullable()->after('property_location_source');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn('localizacao_conferencia');
        });
    }
};
