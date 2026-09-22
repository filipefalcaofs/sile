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
            // Textos decisórios (TVL/parecer/ficha) — catálogo administrável
            // por chave estável, seedado a partir de DecisionTextCatalog::defaults().
            DecisionTextSeeder::class,
            CnaeSeeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
            // Lista EV (escritório virtual, RN-EV-05/07): CNAEs permitidos para
            // ABRIGADO, snapshot versionado do endpoint SEDUR
            // AtividadesPermitidasEmEscritorioVirtual.php — dado versionado
            // separado, torna VirtualOfficeActivityCnae::permitido() navegável.
            EscritorioVirtualCnaeSeeder::class,
            RiskTriggerSeeder::class,
            // Tipos de imóvel do REGIN (catálogo SEDUR 2026-08-26) — dado
            // administrável que o motor lê em TipoImovelCatalog::vigente().
            // Sem esta carga o banco está alcançável e VAZIO: todo valor do
            // REGIN vira desconhecido e o processo degrada para análise.
            PropertyTypeSeeder::class,
            LouosQuadro10Seeder::class,
            LouosQuadro11Seeder::class,
            TratamentoRegrasSeeder::class,
            // Tabela oficial TLL 2026 (Simplifica): valores + hash SEFAZ,
            // exercício vigente do domínio tll_valores. Sem esta carga o DAM
            // fica pendente (honesto) — o motor já existe.
            TllValoresSeeder::class,
            // Zonas urbanísticas da LOUOS (parametrização 3.3): nascem dos
            // valores distintos de `zona` do Quadro 10 VIGENTE — por isso
            // roda APÓS os seeders LOUOS. Sem quadro vigente, fica vazio
            // (honesto). Idempotente; preserva os campos administrados.
            ZonaSeeder::class,
            // Classes de via da LOUOS (usabilidade SEDUR 19/09, item 07):
            // nascem dos valores distintos de `classe_via` do Quadro 11A
            // VIGENTE — por isso roda APÓS os seeders LOUOS. Sem quadro
            // vigente, fica vazio (honesto). Idempotente; preserva os campos
            // administrados.
            ViaSeeder::class,
            ViabilityServiceTypeSeeder::class,
            DocumentRequirementSeeder::class,
            GeoLayerSeeder::class,
            // Catálogo de camadas WFS do GeoServer SEDUR: as 20 FeatureTypes de
            // zona que viviam hardcoded em config/sile.php — zona nova passa a
            // entrar por cadastro (manter-territorio), sem deploy.
            GeoServerLayerSeeder::class,
            // Análise técnica SEDUR (EP10). Catálogos (setor + textos-padrão):
            // o setor inicial evita distribuição bloqueada.
            SectorSeeder::class,
            StandardTextSeeder::class,
        ]);
    }
}
