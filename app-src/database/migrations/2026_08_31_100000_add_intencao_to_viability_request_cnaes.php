<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intencao declarada para o CNAE na alteracao de atividade economica
 * (RN-AA-05b): incluir ou excluir. Nulavel porque distingue duas semanticas
 * diferentes — "esta solicitacao nao declara intencao por atividade"
 * (primeiro estabelecimento, renovacao, onde os CNAEs sao simplesmente as
 * atividades) de "declara, e e incluir/excluir". Um CNAE que a solicitacao
 * nao menciona nao tem linha no pivot, entao "manter" nao precisa de valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_request_cnaes', function (Blueprint $table): void {
            $table->string('intencao')->nullable()->after('is_primary');
        });
    }

    public function down(): void
    {
        Schema::table('viability_request_cnaes', function (Blueprint $table): void {
            $table->dropColumn('intencao');
        });
    }
};
