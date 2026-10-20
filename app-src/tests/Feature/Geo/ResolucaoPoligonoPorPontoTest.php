<?php

namespace Tests\Feature\Geo;

use App\Enums\FonteEdificacao;
use App\Enums\PoligonoFonte;
use App\Enums\PropertyLocationSource;
use App\Enums\StatusResolucaoPoligono;
use App\Enums\ViabilityRequestStatus;
use App\Jobs\DecidirFluxoExpressoJob;
use App\Jobs\GerarPoligonoIaJob;
use App\Models\LotPolygonResolution;
use App\Models\ViabilityRequest;
use App\Services\Expresso\DecisionResult;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use App\Services\Geo\ResolucaoPoligonoPorPonto;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Support\Geo\FakeBuildingFootprintRepository;
use Tests\TestCase;

/**
 * Ordem do polígono pelo ponto: Overture → Microsoft → IA → endereçamento
 * SEDUR pelo ponto → nada.
 */
class ResolucaoPoligonoPorPontoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FakeBuildingFootprintRepository $base;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'viabiliza.features.geoserver_enderecamento' => true,
            'viabiliza.features.edificacoes_overture' => true,
            'viabiliza.features.edificacoes_microsoft' => true,
            'viabiliza.features.edificacoes_ia' => false,
        ]);

        $this->base = new FakeBuildingFootprintRepository;
        $this->app->instance(BuildingFootprintRepository::class, $this->base);
    }

    /**
     * @return array<string, mixed>
     */
    private function lote(): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [-38.4605, -12.9805], [-38.4595, -12.9805], [-38.4595, -12.9795], [-38.4605, -12.9795], [-38.4605, -12.9805],
        ]]];
    }

    /**
     * Endereçamento pelo ponto: devolve o lote (ou nada) e registra a chamada na ordem.
     */
    private function enderecamento(bool $comLote): void
    {
        Http::fake(function (Request $request) use ($comLote) {
            if (str_contains(urldecode($request->url()), 'INTERSECTS')) {
                $this->base->consultas[] = 'enderecamento_ponto';
            }

            return Http::response(['type' => 'FeatureCollection', 'features' => $comLote ? [[
                'type' => 'Feature', 'geometry' => $this->lote(), 'properties' => ['CODLOG' => 1, 'NUMERACAO_METRICA' => 1],
            ]] : []]);
        });
    }

    private function processo(): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create(['property_polygon_geojson' => null, 'status' => ViabilityRequestStatus::Protocolada]);
        $request->forceFill([
            'property_point_lat' => -12.98,
            'property_point_lng' => -38.46,
            'property_location_source' => PropertyLocationSource::CadastroImobiliario,
        ])->save();

        return $request->fresh();
    }

    private function resolucao(): ResolucaoPoligonoPorPonto
    {
        return app(ResolucaoPoligonoPorPonto::class);
    }

    public function test_ordem_overture_microsoft_antes_do_enderecamento_pelo_ponto(): void
    {
        $this->enderecamento(comLote: true);
        $request = $this->processo();

        $esperaIa = $this->resolucao()->resolver($request, podeEsperarIa: true);

        $this->assertFalse($esperaIa);
        $this->assertSame(['overture', 'microsoft', 'enderecamento_ponto'], $this->base->consultas);
        $this->assertSame(PoligonoFonte::Ponto, $request->fresh()->address_polygon_source);
    }

    public function test_complementar_achou_nao_consulta_enderecamento_pelo_ponto(): void
    {
        $this->enderecamento(comLote: true);
        $this->base->comCandidata(FonteEdificacao::Overture, ['type' => 'MultiPolygon', 'coordinates' => [$this->lote()['coordinates']]]);
        $request = $this->processo();

        $this->resolucao()->resolver($request, podeEsperarIa: true);

        $this->assertSame(['overture'], $this->base->consultas);
        $this->assertSame(PoligonoFonte::Overture, $request->fresh()->address_polygon_source);
    }

    public function test_enderecamento_pelo_ponto_sem_lote_deixa_sem_poligono(): void
    {
        $this->enderecamento(comLote: false);
        $request = $this->processo();

        $this->resolucao()->resolver($request, podeEsperarIa: true);

        $this->assertNull($request->fresh()->address_polygon_geojson);
    }

    public function test_precisa_resolver_com_edificacoes_ligadas_e_enderecamento_desligado(): void
    {
        config(['viabiliza.features.geoserver_enderecamento' => false]);

        $this->assertTrue($this->resolucao()->precisaResolver($this->processo()));
    }

    public function test_nada_ligado_nao_precisa_resolver(): void
    {
        config([
            'viabiliza.features.geoserver_enderecamento' => false,
            'viabiliza.features.edificacoes_overture' => false,
            'viabiliza.features.edificacoes_microsoft' => false,
        ]);

        $this->assertFalse($this->resolucao()->precisaResolver($this->processo()));
    }

    public function test_job_devolve_a_fila_enquanto_espera_ia(): void
    {
        config(['viabiliza.features.edificacoes_ia' => true]);
        Queue::fake([GerarPoligonoIaJob::class, DecidirFluxoExpressoJob::class]);
        $this->enderecamento(comLote: true);
        $request = $this->processo();

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldNotReceive('decide');

        (new DecidirFluxoExpressoJob($request->id))->handle($service);

        Queue::assertPushed(GerarPoligonoIaJob::class, fn (GerarPoligonoIaJob $job): bool => $job->viabilityRequestId === $request->id);
        $this->assertNotContains('enderecamento_ponto', $this->base->consultas);
    }

    public function test_espera_da_ia_tem_orcamento_proprio_alem_das_tentativas_do_job(): void
    {
        config([
            'viabiliza.features.edificacoes_ia' => true,
            'viabiliza.expresso.job.tries' => 3,
            'viabiliza.edificacoes.ia.espera_tentativas' => 3,
            'viabiliza.edificacoes.ia.espera_segundos' => 60,
        ]);
        Queue::fake([GerarPoligonoIaJob::class]);
        $this->enderecamento(comLote: true);
        $request = $this->processo();

        $filaDoJob = Mockery::mock(Job::class);
        $filaDoJob->shouldReceive('attempts')->andReturn(3);
        $filaDoJob->shouldReceive('release')->once()->with(60);
        $job = new DecidirFluxoExpressoJob($request->id);
        $job->setJob($filaDoJob);

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldNotReceive('decide');

        $job->handle($service);

        $this->assertSame(6, $job->tries);
        Queue::assertPushed(GerarPoligonoIaJob::class);
    }

    public function test_prazo_esgotado_segue_para_o_enderecamento_pelo_ponto(): void
    {
        config(['viabiliza.features.edificacoes_ia' => true, 'viabiliza.edificacoes.ia.espera_tentativas' => 1]);
        Queue::fake([GerarPoligonoIaJob::class, DecidirFluxoExpressoJob::class]);
        $this->enderecamento(comLote: true);
        $request = $this->processo();
        LotPolygonResolution::factory()->create(['viability_request_id' => $request->id, 'source' => PoligonoFonte::Ia, 'status' => StatusResolucaoPoligono::Pendente]);

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('decide')->once()->andReturn(DecisionResult::paraAnalise('teste'));

        (new DecidirFluxoExpressoJob($request->id))->handle($service);

        Queue::assertNotPushed(GerarPoligonoIaJob::class);
        $this->assertContains('enderecamento_ponto', $this->base->consultas);
        $this->assertSame(PoligonoFonte::Ponto, $request->fresh()->address_polygon_source);
        $this->assertSame('prazo_esgotado', LotPolygonResolution::query()->where('status', StatusResolucaoPoligono::NaoResolvido)->sole()->reason);
    }

    public function test_ia_que_ja_falhou_nao_espera_de_novo(): void
    {
        config(['viabiliza.features.edificacoes_ia' => true]);
        Queue::fake([GerarPoligonoIaJob::class, DecidirFluxoExpressoJob::class]);
        $this->enderecamento(comLote: false);
        $request = $this->processo();
        LotPolygonResolution::factory()->create(['viability_request_id' => $request->id, 'source' => PoligonoFonte::Ia, 'status' => StatusResolucaoPoligono::NaoResolvido, 'reason' => 'nao_contem_o_ponto']);

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('decide')->once()->andReturn(DecisionResult::paraAnalise('teste'));

        (new DecidirFluxoExpressoJob($request->id))->handle($service);

        Queue::assertNotPushed(GerarPoligonoIaJob::class);
        $this->assertContains('enderecamento_ponto', $this->base->consultas);
    }
}
