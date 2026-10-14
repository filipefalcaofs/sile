<?php

namespace Tests\Feature\Motor;

use App\Enums\AnalysisStatus;
use App\Enums\EtapaMotor;
use App\Enums\GeoLayerType;
use App\Enums\ViabilityRequestStatus;
use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEtapa;
use App\Services\Geo\SpatialRepository;
use App\Services\Motor\ReprocessarEtapasService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Reprocesso sem polígono não decide pelo ponto na hora: agenda o job de
 * decisão, que busca o lote pelo ponto (consulta lenta) antes de decidir.
 */
class ReprocessarPoligonoPorPontoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Via, 'version' => 'logradouro-2024']);

        $espacial = new FakeSpatialRepository;
        $espacial->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $espacial->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZCN-1']]);
        $espacial->setNearest(GeoLayerType::Via, [
            'id' => 3,
            'properties' => ['CODLOG' => '958', 'NOME_LOGRADOURO' => 'Rua Luiz Murat'],
            'distancia_m' => 25.9,
        ]);
        $this->app->instance(SpatialRepository::class, $espacial);

        Http::fake(['*' => Http::response(['type' => 'FeatureCollection', 'features' => []])]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function processoEmAnaliseComPonto(array $atributos = []): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create($atributos + [
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
            'property_polygon_geojson' => null,
            'address_polygon_geojson' => null,
            'property_point_lat' => -12.9738,
            'property_point_lng' => -38.4803,
            'zona_codigo' => 'ZCN-1',
            'bairro_oficial' => 'Comércio',
            'cod_log' => '958',
            'used_area_m2' => 40,
        ]);
        $processo->cnaes()->attach(Cnae::factory()->create()->id, ['is_primary' => true]);

        ViabilityRequestEtapa::factory()->indisponivel()->create([
            'viability_request_id' => $processo->id,
            'etapa' => EtapaMotor::Zona,
        ]);

        return $processo;
    }

    public function test_sem_poligono_e_com_ponto_agenda_a_decisao_com_o_ator(): void
    {
        config(['sile.features.geoserver_enderecamento' => true]);
        $ator = User::factory()->create();
        $processo = $this->processoEmAnaliseComPonto();

        $resultado = app(ReprocessarEtapasService::class)->reprocessar($processo, $ator);

        Queue::assertPushed(
            DecidirFluxoExpressoJob::class,
            fn (DecidirFluxoExpressoJob $job): bool => $job->viabilityRequestId === $processo->id
                && $job->reavaliacao
                && $job->atorId === $ator->id,
        );
        $this->assertStringContainsString('polígono do lote', $resultado->mensagem);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
    }

    public function test_com_poligono_ja_gravado_reavalia_na_hora_sem_agendar(): void
    {
        config(['sile.features.geoserver_enderecamento' => true]);
        $processo = $this->processoEmAnaliseComPonto([
            'address_polygon_geojson' => ['type' => 'Polygon', 'coordinates' => [[
                [-38.4803, -12.9738], [-38.4798, -12.9738], [-38.4798, -12.9733], [-38.4803, -12.9733], [-38.4803, -12.9738],
            ]]],
        ]);

        $resultado = app(ReprocessarEtapasService::class)->reprocessar($processo);

        Queue::assertNotPushed(DecidirFluxoExpressoJob::class);
        $this->assertSame('Etapas reprocessadas e veredito reavaliado.', $resultado->mensagem);
    }

    public function test_interruptor_desligado_reavalia_na_hora_sem_agendar(): void
    {
        config(['sile.features.geoserver_enderecamento' => false]);
        $processo = $this->processoEmAnaliseComPonto();

        app(ReprocessarEtapasService::class)->reprocessar($processo);

        Queue::assertNotPushed(DecidirFluxoExpressoJob::class);
    }
}
