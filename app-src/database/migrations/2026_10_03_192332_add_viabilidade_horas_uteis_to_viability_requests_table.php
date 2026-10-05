<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tempo de viabilidade do processo em horas úteis, na mesma régua que o
 * governo federal usa para publicar o tempo de viabilidade de endereço:
 * horas úteis entre o início e o fim da análise, desconsiderando fins de
 * semana, feriados e o período em que o processo está com o cidadão.
 *
 * Materializada na decisão (em vez de calculada na leitura) porque é a base
 * de todos os percentis por período e precisa de índice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->decimal('viabilidade_horas_uteis', 10, 2)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropIndex(['viabilidade_horas_uteis']);
            $table->dropColumn('viabilidade_horas_uteis');
        });
    }
};
