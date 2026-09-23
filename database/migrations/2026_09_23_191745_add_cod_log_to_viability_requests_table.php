<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CODLOG (código oficial do logradouro no cadastro municipal) materializado
     * no processo — mesma onda do território materializado (zona/bairro). É o
     * casamento das regras territoriais por endereço (RT-01 logradouro 212,
     * allow-list de Itaigara, allow-lists de via do Quadro 10). Nullable e
     * write-once conservador: processos sem via identificada ficam sem valor
     * (degradação honesta — nunca um código inventado).
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->string('cod_log')->nullable()->index()->after('bairro_oficial');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropIndex(['cod_log']);
            $table->dropColumn('cod_log');
        });
    }
};
