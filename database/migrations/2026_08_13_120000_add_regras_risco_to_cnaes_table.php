<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flags de regra por CNAE (HU-047 RN-010, tela única): mesmas colunas do
     * projeto de referência (sls-sms) — exigência de responsável técnico
     * (sempre ou só em alto risco) e fator multiplicador (com ou sem
     * detalhamento). Ficam direto em `cnaes` porque não são dado versionado
     * como a classificação de risco (RiskClassification): mudam junto com o
     * cadastro do CNAE, sem quatro olhos.
     */
    public function up(): void
    {
        Schema::table('cnaes', function (Blueprint $table) {
            $table->boolean('exige_rt')->default(false)->after('active');
            $table->boolean('exige_rt_se_alto')->default(false)->after('exige_rt');
            $table->boolean('exige_fator_multiplicador')->default(false)->after('exige_rt_se_alto');
            $table->boolean('exige_detalhamento_multiplicador')->default(false)->after('exige_fator_multiplicador');
        });
    }

    public function down(): void
    {
        Schema::table('cnaes', function (Blueprint $table) {
            $table->dropColumn([
                'exige_rt',
                'exige_rt_se_alto',
                'exige_fator_multiplicador',
                'exige_detalhamento_multiplicador',
            ]);
        });
    }
};
