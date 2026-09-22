<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escritório virtual — SEDE (reunião SEDUR 2026-07-16). Pergunta ao requerente
 * "será sede de escritório virtual?" na solicitação; confirmação do analista no
 * produto/decisão. Abrigado (*_tenant) vem no M2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->boolean('wants_virtual_office_hq')->default(false)->after('is_virtual_office');
        });
        Schema::table('viability_decisions', function (Blueprint $table): void {
            $table->boolean('is_virtual_office_hq')->default(false)->after('consolidated_result');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', fn (Blueprint $t) => $t->dropColumn('wants_virtual_office_hq'));
        Schema::table('viability_decisions', fn (Blueprint $t) => $t->dropColumn('is_virtual_office_hq'));
    }
};
