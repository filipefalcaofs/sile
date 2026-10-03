<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A declaração recebida no POST fica no processo (origem_declarada).
     * O CNPJ da empresa passa a ser opcional: a viabilidade chega com razão
     * social e CPF do solicitante, e o CNPJ só vem quando a atividade está
     * abrigada em escritório virtual — esse CNPJ não é o da empresa.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->json('origem_declarada')->nullable();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('cnpj', 14)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn('origem_declarada');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('cnpj', 14)->nullable(false)->change();
        });
    }
};
