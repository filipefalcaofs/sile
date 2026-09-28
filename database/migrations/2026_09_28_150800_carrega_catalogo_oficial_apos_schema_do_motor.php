<?php

use App\Support\CatalogoOficialSchema;
use Database\Seeders\CatalogoOficialSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * A carga de 2026_09_23 ficou antes do schema de condicionantes e do
 * código CEIN do setor. Esta migration roda depois desse schema e
 * publica o catálogo. Idempotente — re-seed não duplica versão vigente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || app()->environment('testing')) {
            return;
        }

        if (! CatalogoOficialSchema::pronto()) {
            throw new RuntimeException(
                'Schema do catálogo oficial incompleto: treatment_condicionantes ou sectors.code ausentes.',
            );
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
