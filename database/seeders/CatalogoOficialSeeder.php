<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Catálogo universal do motor: dado oficial que toda instalação precisa
 * (homologação e produção), sem usuário, empresa ou processo.
 *
 * Idempotente. A migration de carga chama este seeder fora da suíte;
 * o deploy que só roda `migrate` publica a planilha, os CNAEs e as
 * demais regras vigentes.
 */
class CatalogoOficialSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            LegalTermSeeder::class,
            ParameterSeeder::class,
            DecisionTextSeeder::class,
            CnaeSeeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
            EscritorioVirtualCnaeSeeder::class,
            RiskTriggerSeeder::class,
            PropertyTypeSeeder::class,
            LouosQuadro10Seeder::class,
            LouosQuadro11Seeder::class,
            TratamentoRegrasSeeder::class,
            TerritorialRuleSeeder::class,
            TllValoresSeeder::class,
            ZonaSeeder::class,
            ViaSeeder::class,
            ViabilityServiceTypeSeeder::class,
            DocumentRequirementSeeder::class,
            GeoLayerSeeder::class,
            GeoServerLayerSeeder::class,
            SectorSeeder::class,
            StandardTextSeeder::class,
            HolidaySeeder::class,
        ]);
    }
}
