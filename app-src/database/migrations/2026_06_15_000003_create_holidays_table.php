<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cadastro de feriados (HU-137) — dado versionado/auditado e substituível.
     * É o seam central de dias úteis: o BusinessDeadlineCalculator desconta os
     * feriados ATIVOS daqui ao medir duração em tempo útil (HU-129). A lista
     * municipal oficial de Salvador é pendência SEDUR (degradação honesta): sem
     * feriado municipal cadastrado, só fins de semana são descontados e o
     * relatório exibe a ressalva (hasOfficialCalendar=false). NUNCA se inventa
     * feriado.
     *
     * Portável (sem geometria): a suíte roda em SQLite e o cálculo de dias úteis
     * é puro (datas), independente de PostGIS.
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();

            // Data do feriado. Para os recorrentes anuais (recurring_annually),
            // a data é uma âncora de armazenamento — o provider compara mês/dia,
            // ignorando o ano. unique já cria o índice em date.
            $table->date('date')->unique();

            $table->string('name');

            // Feriado fixo que se repete todo ano (ex.: 25/12 Natal). false = data
            // específica (ex.: feriado municipal pontual de um ano).
            $table->boolean('recurring_annually')->default(false);

            // Permite inativar sem excluir (mantém o histórico/auditoria).
            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
