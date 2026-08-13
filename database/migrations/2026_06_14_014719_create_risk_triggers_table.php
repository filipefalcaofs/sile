<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gatilhos de risco (categoria semi-expresso) como TABELA parametrizada
     * (HU-049/HU-051): cada gatilho acionado derruba o encaminhamento para
     * análise técnica com `motivo` auditável. `ativo` é o estado administrável
     * (liga/desliga por interface, default true); `categoria` distingue a
     * natureza do gatilho. TABULAR (sem PostGIS): roda em SQLite. unique(codigo)
     * habilita o upsert idempotente do seeder e o vínculo com o enum TipoGatilho.
     */
    public function up(): void
    {
        Schema::create('risk_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('titulo');
            $table->text('motivo');
            $table->boolean('ativo')->default(true);
            $table->string('categoria')->default('semi_expresso');
            $table->timestamps();
            $table->unique('codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_triggers');
    }
};
