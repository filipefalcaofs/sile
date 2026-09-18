<?php

namespace Tests\Feature\Geo;

use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Geo\TerritorioProcessoService;
use App\Services\Geo\TerritoryResult;
use App\Services\Geo\TerritoryService;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Materialização do território no processo (Onda GIS): com o acesso full ao
 * GIS/SEDUR validado em produção, a zona oficial (GeoServer WFS) e o bairro
 * oficial (camada GeoSalvador) deixam de ser consultados e descartados — são
 * GRAVADOS em viability_requests (zona_codigo/bairro_oficial) na identificação
 * do imóvel e como rede de segurança no protocolo. Degradação NUNCA
 * sobrescreve dado bom: indisponível/não encontrado não apaga o que estava.
 */
class TerritorioProcessoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * TerritoryResult com zona/bairro identificados (o restante indisponível).
     */
    private function territorioIdentificado(?string $zona, ?string $bairro): TerritoryResult
    {
        $dim = fn (string $status, ?string $nome): array => [
            'status' => $status,
            'nome' => $nome,
            'propriedades' => null,
            'motivo' => null,
            'versao_camada' => 'teste',
        ];

        return new TerritoryResult(
            bairro: $dim($bairro !== null ? 'identificado' : 'indisponivel', $bairro),
            via: $dim('indisponivel', null) + ['distancia_m' => null],
            zona: $dim($zona !== null ? 'identificado' : 'indisponivel', $zona),
            lote: $dim('indisponivel', null),
            restricoes: ['status' => 'indisponivel', 'itens' => [], 'motivo' => null, 'versao_camada' => null],
        );
    }

    private function mockTerritorio(TerritoryResult $result): void
    {
        $mock = Mockery::mock(TerritoryService::class);
        $mock->shouldReceive('identify')->andReturn($result);
        $this->app->instance(TerritoryService::class, $mock);
    }

    public function test_materializa_zona_e_bairro_oficial_quando_identificados(): void
    {
        $this->mockTerritorio($this->territorioIdentificado('ZPR-1', 'Pituba'));

        $processo = ViabilityRequest::factory()->create();

        $gravou = app(TerritorioProcessoService::class)->materializar($processo);

        $this->assertTrue($gravou);
        $this->assertSame('ZPR-1', $processo->fresh()->zona_codigo);
        $this->assertSame('Pituba', $processo->fresh()->bairro_oficial);
    }

    public function test_degradacao_nao_sobrescreve_dado_materializado(): void
    {
        // Base indisponível desta vez (GeoServer fora) — o que estava gravado
        // NÃO pode ser apagado pela degradação.
        $this->mockTerritorio($this->territorioIdentificado(null, null));

        $processo = ViabilityRequest::factory()->create([
            'zona_codigo' => 'ZPR-2',
            'bairro_oficial' => 'Ribeira',
        ]);

        $gravou = app(TerritorioProcessoService::class)->materializar($processo);

        $this->assertFalse($gravou);
        $this->assertSame('ZPR-2', $processo->fresh()->zona_codigo);
        $this->assertSame('Ribeira', $processo->fresh()->bairro_oficial);
    }

    public function test_sem_poligono_nao_materializa(): void
    {
        $processo = ViabilityRequest::factory()->create(['property_polygon_geojson' => null]);

        $gravou = app(TerritorioProcessoService::class)->materializar($processo);

        $this->assertFalse($gravou);
        $this->assertNull($processo->fresh()->zona_codigo);
    }

    public function test_protocolo_materializa_o_territorio_como_rede_de_seguranca(): void
    {
        $this->mockTerritorio($this->territorioIdentificado('ZPR-1', 'Pituba'));

        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
        $solicitacao = ViabilityRequest::factory()->draft()->withPrimaryCnae()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'used_area_m2' => 120.0,
        ]);

        $protocolada = app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $user);

        $this->assertSame('ZPR-1', $protocolada->fresh()->zona_codigo);
        $this->assertSame('Pituba', $protocolada->fresh()->bairro_oficial);
    }

    public function test_backfill_materializa_processos_com_poligono_e_pula_sem_poligono(): void
    {
        $this->mockTerritorio($this->territorioIdentificado('ZPR-1', 'Pituba'));

        // A factory protocoled() crava o número — aqui cada processo tem o seu.
        $comPoligono = ViabilityRequest::factory()->protocoled()->create(['protocol_number' => 'VIA-2026-000701']);
        $semPoligono = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => 'VIA-2026-000702',
            'property_polygon_geojson' => null,
        ]);
        $jaMaterializado = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => 'VIA-2026-000703',
            'zona_codigo' => 'ZDE-1',
            'bairro_oficial' => 'Centro',
        ]);

        $this->artisan('geo:materializar-territorio')->assertSuccessful();

        $this->assertSame('ZPR-1', $comPoligono->fresh()->zona_codigo);
        $this->assertSame('Pituba', $comPoligono->fresh()->bairro_oficial);
        $this->assertNull($semPoligono->fresh()->zona_codigo);
        // Já materializado: fora do recorte do backfill, valor preservado.
        $this->assertSame('ZDE-1', $jaMaterializado->fresh()->zona_codigo);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'territorio',
            'event' => 'materializacao-backfill',
            'result' => 'sucesso',
        ]);
    }
}
