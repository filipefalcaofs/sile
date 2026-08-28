<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resposta da PERGUNTA GERAL de escritorio virtual ("deseja ser abrigado?",
 * RN-EV-01 da revisao 4). O campo `wants_virtual_office_hq` que ja existe
 * responde outra coisa — a pergunta VINCULADA ao CNAE 8211-3/00 ("ira prestar
 * servico de escritorio virtual, centro de negocios ou coworking?") — e
 * continua com a semantica dele.
 *
 * Nulavel de proposito: null significa "ainda nao perguntado", que e o estado
 * de todo rascunho anterior a esta migration e nao pode ser confundido com
 * uma recusa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->boolean('wants_virtual_office_tenant')->nullable()->after('wants_virtual_office_hq');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropColumn('wants_virtual_office_tenant');
        });
    }
};
