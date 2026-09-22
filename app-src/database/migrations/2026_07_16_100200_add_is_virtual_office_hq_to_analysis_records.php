<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escritório virtual — SEDE (reunião SEDUR 2026-07-16, RN-EV-02): o analista
 * confirma na ficha se o processo é "Sede de Escritório Virtual". Nullable —
 * fichas anteriores não têm o dado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_records', function (Blueprint $table): void {
            $table->boolean('is_virtual_office_hq')->nullable()->after('parking');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_records', function (Blueprint $table): void {
            $table->dropColumn('is_virtual_office_hq');
        });
    }
};
