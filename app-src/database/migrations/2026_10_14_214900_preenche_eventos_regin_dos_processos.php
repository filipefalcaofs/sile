<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Grava os eventos da JUCEB dos processos do REGIN já recebidos.
     */
    public function up(): void
    {
        Artisan::call('regin:preencher-eventos');
    }

    public function down(): void {}
};
