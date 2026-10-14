<?php

namespace Tests\Feature\Motor;

use App\Enums\EtapaMotor;
use App\Enums\GeoLayerType;
use App\Enums\SituacaoEtapa;
use App\Models\GeoLayer;
use App\Models\ViabilityRequest;
use App\Services\Geo\ClassificacaoViariaHit;
use App\Services\Geo\GeoServerClassificacaoViariaClient;
use App\Services\Geo\SpatialRepository;
use App\Services\Geo\TerritorioProcessoService;
use App\Services\Motor\EtapaMotorRegistrador;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

class RegistroDasEtapasTerritorioTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FakeSpatialRepository $espacial;

    protected function setUp(): void
    {
        parent::setUp();

        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Via, 'version' => 'logradouro-2024']);

        $this->espacial = new FakeSpatialRepository;
        $this->espacial->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $this->espacial->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZCN-1']]);
        $this->espacial->setNearest(GeoLayerType::Via, [
            'id' => 3,
            'properties' => ['CODLOG' => '958', 'NOME_LOGRADOURO' => 'Rua Luiz Murat'],
            'distancia_m' => 25.9,
        ]);
        $this->app->instance(SpatialRepository::class, $this->espacial);
    }

    private function classificacaoViaria(string $status, ?string $classe = null, ?string $motivo = null): void
    {
        $this->mock(GeoServerClassificacaoViariaClient::class, function ($mock) use ($status, $classe, $motivo): void {
            $mock->shouldReceive('consultar')->andReturn(new ClassificacaoViariaHit($status, $classe, $classe, $motivo));
        });
    }

    private function processoComPonto(): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'property_polygon_geojson' => null,
            'property_point_lat' => -12.977769,
            'property_point_lng' => -38.516404,
        ]);
    }

    public function test_materializar_registra_zona_via_e_bairro_como_ok_quando_identificados(): void
    {
        $this->classificacaoViaria(ClassificacaoViariaHit::IDENTIFICADO, 'VE');
        $processo = $this->processoComPonto();

        app(TerritorioProcessoService::class)->materializar($processo);

        foreach ([EtapaMotor::Zona, EtapaMotor::Via, EtapaMotor::Bairro] as $etapa) {
            $this->assertSame(
                SituacaoEtapa::Ok,
                $processo->etapas()->where('etapa', $etapa->value)->first()?->situacao,
                $etapa->value,
            );
        }
    }

    public function test_materializar_sem_ponto_nao_registra_etapas_de_territorio(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'property_polygon_geojson' => null,
            'property_point_lat' => null,
            'property_point_lng' => null,
        ]);

        app(TerritorioProcessoService::class)->materializar($processo);

        $this->assertSame(0, $processo->etapas()->count());
    }

    private function etapaVia(ViabilityRequest $processo): ?SituacaoEtapa
    {
        return $processo->etapas()->where('etapa', EtapaMotor::Via->value)->first()?->situacao;
    }

    public function test_classe_viaria_segue_o_codlog_do_processo_e_nao_a_via_mais_proxima(): void
    {
        $this->espacial->setNearest(GeoLayerType::Via, null);
        $this->classificacaoViaria(ClassificacaoViariaHit::IDENTIFICADO, 'VE');
        $processo = $this->processoComPonto();
        $processo->forceFill(['cod_log' => '958'])->save();

        app(TerritorioProcessoService::class)->materializar($processo);

        $this->assertSame(SituacaoEtapa::Ok, $this->etapaVia($processo));
    }

    public function test_classe_viaria_indisponivel_no_geoserver_fica_reprocessavel(): void
    {
        $this->classificacaoViaria(ClassificacaoViariaHit::INDISPONIVEL, null, 'GeoServer fora do ar.');
        $processo = $this->processoComPonto();

        app(TerritorioProcessoService::class)->materializar($processo);

        $this->assertSame(SituacaoEtapa::Indisponivel, $this->etapaVia($processo));
    }

    public function test_classe_viaria_nao_encontrada_para_o_codlog_e_definitiva(): void
    {
        $this->classificacaoViaria(ClassificacaoViariaHit::NAO_ENCONTRADO, null, 'Logradouro sem classificação.');
        $processo = $this->processoComPonto();

        app(TerritorioProcessoService::class)->materializar($processo);

        $this->assertSame(SituacaoEtapa::Definitiva, $this->etapaVia($processo));
    }

    public function test_comando_recalcula_a_etapa_sem_resultado_pelo_codlog(): void
    {
        $this->classificacaoViaria(ClassificacaoViariaHit::IDENTIFICADO, 'VE');
        $processo = $this->processoComPonto();
        $processo->forceFill(['cod_log' => '958'])->save();
        app(EtapaMotorRegistrador::class)
            ->registrar($processo, EtapaMotor::Via, SituacaoEtapa::Definitiva);

        $this->artisan('processos:recalcular-classe-viaria')->assertSuccessful();

        $this->assertSame(SituacaoEtapa::Ok, $this->etapaVia($processo));
    }

    public function test_comando_grava_a_via_no_inventario_de_camadas_e_cobre_processo_sem_etapa(): void
    {
        $this->classificacaoViaria(ClassificacaoViariaHit::IDENTIFICADO, 'VE');
        $processo = $this->processoComPonto();
        $processo->forceFill(['cod_log' => '958', 'camadas_incidentes' => ['zona' => null, 'camadas' => []]])->save();

        $this->artisan('processos:recalcular-classe-viaria')->assertSuccessful();

        $this->assertSame(SituacaoEtapa::Ok, $this->etapaVia($processo));
        $this->assertSame('VE', $processo->fresh()->camadas_incidentes['via']);
    }
}
