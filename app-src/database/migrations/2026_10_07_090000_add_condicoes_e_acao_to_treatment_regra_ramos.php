<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_regra_ramos', function (Blueprint $table) {
            $table->json('condicoes')->nullable()->after('tipo_dirige');
            $table->json('acao')->nullable()->after('bloqueia');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_regra_ramos', function (Blueprint $table) {
            $table->dropColumn(['condicoes', 'acao']);
        });
    }
};
