<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Geometria do lote por FONTE (endereçamento SEDUR, SEMAP/GeoSalvador e o
     * polígono declarado no REGIN). Guardar as três preserva a evidência de
     * cada origem e permite apontar divergência entre elas; `lote_poligono_fonte`
     * registra qual venceu a precedência. Ambas fora do fillable: são escritas
     * pelos serviços de geometria, nunca pelo cidadão.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->json('lote_poligonos')->nullable();
            $table->string('lote_poligono_fonte')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn(['lote_poligonos', 'lote_poligono_fonte']);
        });
    }
};
