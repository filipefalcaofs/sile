<?php

use Database\Seeders\CatalogoOficialSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Homologação sobe com migrate e não com o seed de desenvolvimento.
 * O catálogo universal do motor entra aqui. A suíte não executa esta
 * carga: cada teste semeia só o que precisa. A carga não é desfeita
 * no down — apagar CNAE e regras vigentes destruiria decisão já gravada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || app()->environment('testing')) {
            return;
        }

        ini_set('max_execution_time', '0');

        Artisan::call('db:seed', [
            '--class' => CatalogoOficialSeeder::class,
            '--force' => true,
        ]);

        $output = Artisan::output();

        if ($output !== '') {
            fwrite(STDOUT, $output);
        }
    }

    public function down(): void
    {
        //
    }
};
