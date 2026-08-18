<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Services\Geo\TerritoryService;
use Database\Seeders\GeoLayerSeeder;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Smoke end-to-end da Fase 4 (HU-029 a HU-037) contra PostGIS REAL sobre o
 * snapshot oficial COMMITADO do GeoSalvador (database/data/geo/), SEM rede:
 * golden points conhecidos de Salvador → bairro oficial esperado via
 * TerritoryService::identify, com zona/lote comunicados como indisponíveis
 * (pendente SEDUR) e a identificação auditada (RN-002/RN-004).
 *
 * SEM FACHADA (regra nº1): o bairro real é requisito rígido — snapshot ausente
 * FAZ O TESTE FALHAR (bloqueio da fase), nunca skip. O guard do PostgisTestCase
 * garante que, com o servidor de pé, qualquer problema vira FALHA (não skip).
 */
#[Group('postgis')]
class PhaseFourSmokeTest extends PostgisTestCase
{
    /**
     * Conjunto de golden points (proteção de regressão de domínio): coordenada
     * real de marco conhecido de Salvador → bairro oficial do snapshot
     * commitado (geosalvador-bairros-dec38776-2024, 171 bairros), verificada por
     * ST_Contains sobre o dado real. Se a base oficial da SEDUR substituir o
     * snapshot e mover divisas, este conjunto sinaliza a regressão.
     *
     * @return list<array{0: string, 1: float, 2: float, 3: string}>
     */
    private function goldenPoints(): array
    {
        return [
            // marco conhecido,      lat,       lng,       bairro oficial esperado
            ['Farol da Barra', -13.0103, -38.5320, 'Barra'],
            ['Pituba', -12.9939, -38.4585, 'Pituba'],
            ['Shopping da Bahia', -12.9783, -38.4583, 'Caminho das Árvores'],
        ];
    }

    public function test_snapshot_de_bairros_commitado_e_requisito_rigido(): void
    {
        // Sem fachada: o bairro real PRECISA existir; ausência é bloqueio da
        // fase (FALHA), nunca skip.
        $this->assertFileExists(
            database_path('data/geo/bairros.geojson'),
            'Snapshot oficial de bairros (HU-034) ausente — bloqueio da fase, nunca skip.',
        );
    }

    public function test_golden_points_de_salvador_identificam_o_bairro_real_do_snapshot(): void
    {
        // Carga REAL do snapshot COMMITADO (sem rede): bairro/via/restrição reais
        // + zona/lote pendente_fonte — exatamente o estado canônico de produção.
        $this->seed(GeoLayerSeeder::class);

        // Prova que a carga é real: a camada de bairro vigente tem as ~171
        // feições oficiais. sole() LANÇA (FALHA) se a carga tivesse caído para
        // pendente_fonte por snapshot ausente — bloqueio explícito, nunca skip.
        $bairro = GeoLayer::vigente(GeoLayerType::Bairro)->sole();
        $this->assertGreaterThan(100, $bairro->feature_count, 'Bairro deveria ter as ~171 feições reais do GeoSalvador.');

        $service = app(TerritoryService::class);

        foreach ($this->goldenPoints() as [$marco, $lat, $lng, $bairroEsperado]) {
            $resultado = $service->identify($lat, $lng)->toArray();

            $this->assertSame('identificado', $resultado['bairro']['status'], "{$marco}: bairro deveria ser identificado pelo ST_Contains real.");
            $this->assertSame($bairroEsperado, $resultado['bairro']['nome'], "{$marco}: bairro oficial real esperado do snapshot.");
            $this->assertSame($bairro->version, $resultado['bairro']['versao_camada'], "{$marco}: versão da camada de bairro registrada (RN-004).");

            // Zona e lote: base pendente SEDUR — comunicados como indisponível,
            // NUNCA inventados (HU-031/HU-033, sem fachada).
            $this->assertSame('indisponivel', $resultado['zona']['status'], "{$marco}: zona indisponível (pendente SEDUR).");
            $this->assertSame('Base de zoneamento pendente SEDUR', $resultado['zona']['motivo']);
            $this->assertSame('indisponivel', $resultado['lote']['status'], "{$marco}: lote indisponível (pendente SEDUR).");
            $this->assertSame('Base de lotes pendente SEDUR', $resultado['lote']['motivo']);
        }

        // RN-002: a identificação territorial é auditada.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'territorio',
            'event' => 'identificacao',
            'result' => 'sucesso',
        ]);
    }
}
