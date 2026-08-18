<?php

namespace Database\Seeders;

use App\Enums\GeoLayerType;
use App\Services\Geo\GeoJsonLayerImporter;
use App\Support\DemoMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Zona urbanística FICTÍCIA — EXCLUSIVA de desenvolvimento/teste (NUNCA produção).
 *
 * Existe por UM motivo: tornar um DEFERIMENTO do fluxo expresso NAVEGÁVEL em dev.
 * A carga oficial de zoneamento (Quadro 10/SEDUR — SIGIS/CA 2000) está bloqueada;
 * sem ela o motor degrada honestamente para `em_analise` (a maioria dos casos).
 * Para DEMONSTRAR o core value sem fachada, carregamos uma feição de ZONA sobre
 * um polígono do Centro de Salvador com um CÓDIGO de zona que o Quadro 10 real já
 * conhece (ZCN-1 → permitido para nR1) — assim o motor REAL defere de verdade.
 *
 * Entrega-funcional: muda só a CARGA (o polígono fictício e sua proveniência),
 * nunca a LÓGICA. Em produção este seeder é um NO-OP (gate de ambiente abaixo) —
 * a degradação honesta para `em_analise` permanece até a zona oficial entrar, e
 * então a MESMA lógica passa a deferir/indeferir sobre o dado real.
 *
 * Driver-aware (espelha GeoLayerSeeder): a geometria exige PostGIS; em SQLite a
 * carga não roda (a prova com zona real é dos testes @group postgis). A
 * GeoJsonLayerImporter fecha a camada `pendente_fonte` da zona (GeoLayerSeeder)
 * e abre a fictícia como vigente — então TerritoryService::vigente(Zona) passa a
 * resolver a feição fictícia. Idempotente por (type, version).
 */
class ZonaFicticiaDevSeeder extends Seeder
{
    /**
     * Versão da camada fictícia — rótulo EXPLÍCITO de demonstração de dev.
     */
    public const VERSION = 'zona-ficticia-dev';

    /**
     * Código de zona REAL do Quadro 10 (ZCN-1: Zona de Comércio e Negócios) —
     * permite o grupo nR1 (minimercado e afins). É a carga que o motor lê; a
     * proveniência fictícia vive na versão/origem/propriedades, não no código.
     */
    public const ZONA_CODE = 'ZCN-1';

    public function run(): void
    {
        // GATE DE AMBIENTE (anti-fachada): a zona fictícia JAMAIS entra em
        // produção — lá a degradação honesta (em_analise sem zona oficial) é o
        // comportamento correto. Só dev/teste para demonstrar o deferimento.
        if (! DemoMode::allowsDemoSeeders()) {
            $this->command?->warn('ZonaFicticiaDevSeeder: ignorado fora de dev/teste (produção mantém a degradação honesta sem zona oficial).');

            return;
        }

        // Driver-aware: a geometria da zona exige PostGIS. Em SQLite não há
        // ST_*; a carga real (e o deferimento navegável) é exercida nos testes
        // @group postgis e no dev pgsql — consistente com o GeoLayerSeeder.
        if (DB::getDriverName() !== 'pgsql') {
            $this->command?->warn('ZonaFicticiaDevSeeder: zona fictícia exige PostGIS — pulada em SQLite (degradação honesta).');

            return;
        }

        app(GeoJsonLayerImporter::class)->import(
            GeoLayerType::Zona,
            self::VERSION,
            'demonstracao-dev-sile — zona fictícia, substituir pela base oficial SEDUR (Quadro 10)',
            $this->featureCollection(),
        );
    }

    /**
     * FeatureCollection com UMA feição de zona cobrindo um polígono do Centro de
     * Salvador (contém o ponto dos exemplos do ExpressoDevSeeder). As
     * propriedades trazem o código que o Quadro 10 lê (NOME) e rótulos
     * EXPLÍCITOS de fictício — o motor lê o código; o humano vê a proveniência.
     *
     * @return array<string, mixed>
     */
    private function featureCollection(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'NOME' => self::ZONA_CODE,
                        'descricao' => 'Zona fictícia de demonstração — substituir pela base oficial SEDUR (Quadro 10)',
                        'fonte' => 'demonstracao-dev-sile',
                    ],
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[
                            [-38.520, -12.980],
                            [-38.520, -12.965],
                            [-38.500, -12.965],
                            [-38.500, -12.980],
                            [-38.520, -12.980],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
