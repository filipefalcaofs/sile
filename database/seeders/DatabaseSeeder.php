<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            LegalTermSeeder::class,
            ParameterSeeder::class,
            CnaeSeeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
            RiskTriggerSeeder::class,
            LouosQuadro7Seeder::class,
            LouosQuadro10Seeder::class,
            LouosQuadro11Seeder::class,
            ViabilityServiceTypeSeeder::class,
            DocumentRequirementSeeder::class,
            DevAdminSeeder::class,
            CompanySeeder::class,
            GeoLayerSeeder::class,
            // Depende de empresas/CNAEs/catálogos acima — fecha o seed de dev.
            SolicitacaoDevSeeder::class,
        ]);
    }
}
