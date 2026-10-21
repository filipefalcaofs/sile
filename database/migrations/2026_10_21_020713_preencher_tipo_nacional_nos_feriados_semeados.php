<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Os feriados nacionais fixos semeados (recorrentes, sem origem) ficaram sem
     * tipo; passam a ser classificados como nacionais. Cadastros manuais
     * pontuais seguem sem tipo até a SEDUR classificá-los na tela.
     */
    public function up(): void
    {
        DB::table('holidays')
            ->whereNull('source')
            ->whereNull('type')
            ->where('recurring_annually', true)
            ->update(['type' => 'nacional']);
    }

    public function down(): void
    {
        DB::table('holidays')
            ->whereNull('source')
            ->where('type', 'nacional')
            ->where('recurring_annually', true)
            ->update(['type' => null]);
    }
};
