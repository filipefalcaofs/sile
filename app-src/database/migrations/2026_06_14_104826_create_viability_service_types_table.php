<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipos de serviço da solicitação (HU-061 RN-005) — dado administrável
     * (CRUD admin), não parâmetro do registry. flow_hint é uma pista textual
     * de roteamento; active liga/desliga o tipo na seleção.
     */
    public function up(): void
    {
        Schema::create('viability_service_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('flow_hint')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viability_service_types');
    }
};
