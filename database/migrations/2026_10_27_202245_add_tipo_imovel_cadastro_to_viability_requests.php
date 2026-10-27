<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Utilizacao do Cadastro Imobiliário (residencial, não residencial...). Fica em
 * campo próprio: o `tipo_imovel` é o texto do REGIN e alimenta o reconhecimento
 * que decide o encaminhamento do processo; sobrescrevê-lo mudaria o roteamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->string('tipo_imovel_cadastro', 120)->nullable()->after('tipo_imovel_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn('tipo_imovel_cadastro');
        });
    }
};
