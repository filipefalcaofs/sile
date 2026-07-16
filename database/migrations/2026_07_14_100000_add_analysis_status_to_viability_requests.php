<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coluna do eixo operacional da análise (paralela ao status canônico).
 * Nullable: só materializa quando o processo entra em análise humana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->string('analysis_status')->nullable()->after('analysis_stage');
            $table->index('analysis_status');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropIndex(['analysis_status']);
            $table->dropColumn('analysis_status');
        });
    }
};
