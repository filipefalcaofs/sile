<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Massa de desenvolvimento. Não entra na entrega SEMIT: o arquivo é
 * excluído na montagem e o DatabaseSeeder só o chama se a classe existir.
 * Os seeders abaixo já se recusam fora de dev/teste quando o gate existe.
 */
class DevDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            DevAdminSeeder::class,
            CompanySeeder::class,
            SolicitacaoDevSeeder::class,
            ZonaFicticiaDevSeeder::class,
            ExpressoDevSeeder::class,
            AnaliseDevSeeder::class,
            ComunicacaoDevSeeder::class,
            AuditoriaDevSeeder::class,
            RelatoriosDevSeeder::class,
            AiConfigDevSeeder::class,
        ]);
    }
}
