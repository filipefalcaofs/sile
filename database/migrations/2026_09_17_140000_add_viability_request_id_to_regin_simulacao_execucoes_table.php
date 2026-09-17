<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga a execução da simulação REGIN ao processo real criado no clique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regin_simulacao_execucoes', function (Blueprint $table): void {
            $table->foreignId('viability_request_id')
                ->nullable()
                ->after('user_id')
                ->constrained('viability_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('regin_simulacao_execucoes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('viability_request_id');
        });
    }
};
