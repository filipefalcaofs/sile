<?php

use Database\Seeders\PropertyTypeVistoriaSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Lote, Box, Centro comercial, Shopping e Outros na ficha de vistoria. A suíte
 * não executa esta carga: cada teste semeia só o que precisa. Não é desfeita
 * no down — a ficha concluída pode já guardar um desses códigos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || app()->environment('testing')) {
            return;
        }

        (new PropertyTypeVistoriaSeeder)->run();
    }

    public function down(): void
    {
        //
    }
};
