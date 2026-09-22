<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Catálogos oficiais usados em homologação e produção.
     * Não cria usuário, empresa nem processo de exemplo.
     */
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
