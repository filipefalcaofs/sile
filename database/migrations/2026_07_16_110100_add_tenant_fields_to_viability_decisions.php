<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produto do ABRIGADO de escritório virtual (reunião SEDUR 2026-07-16,
 * RN-EV-05): referencia o nº TVL da SEDE ("End. Virtual - TVL Nº").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_decisions', function (Blueprint $table): void {
            $table->boolean('is_virtual_office_tenant')->default(false)->after('is_virtual_office_hq');
            $table->string('virtual_office_hq_tvl_number')->nullable()->after('is_virtual_office_tenant');
        });
    }

    public function down(): void
    {
        Schema::table('viability_decisions', function (Blueprint $table): void {
            $table->dropColumn(['is_virtual_office_tenant', 'virtual_office_hq_tvl_number']);
        });
    }
};
