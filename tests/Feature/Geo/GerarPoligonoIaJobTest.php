<?php

namespace Tests\Feature\Geo;

use App\Enums\FonteEdificacao;
use App\Enums\PoligonoFonte;
use App\Enums\PropertyLocationSource;
use App\Enums\StatusResolucaoPoligono;
use App\Jobs\DecidirFluxoExpressoJob;
use App\Jobs\GerarPoligonoIaJob;
use App\Models\BuildingFootprint;
use App\Models\LotPolygonResolution;
use App\Models\ViabilityRequest;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Geo\FakeBuildingFootprintRepository;
use Tests\TestCase;

class GerarPoligonoIaJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1a\n0000";

    private FakeBuildingFootprintRepository $base;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'viabiliza.features.edificacoes_ia' => true,
            'viabiliza.integrations.edificacoes.ia_url' => 'http://ia.local',
        ]);

        $this->base = new FakeBuildingFootprintRepository;
        $this->app->instance(BuildingFootprintRepository::class, $this->base);
        Storage::fake('local');
        Queue::fake([DecidirFluxoExpressoJob::class]);
    }

    private function processo(float $lat = -12.98, float $lng = -38.46): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create(['property_polygon_geojson' => null]);
        $request->forceFill([
            'property_point_lat' => $lat,
            'property_point_lng' => $lng,
            'property_location_source' => PropertyLocationSource::CadastroImobiliario,
        ])->save();

        return $request->fresh();
    }

    /**
     * @param  array<string, mixed>|int  $ia  resposta do serviço ou status HTTP de erro
     */
    private function fake(int $imageServer = 200, int $wms = 200, array|int|null $ia = null): void
    {
        $ia ??= ['status' => 'ok', 'anel' => [[350, 350], [450, 350], [450, 450], [350, 450]], 'score' => 0.93, 'modelo' => 'sam2.1-hiera-small@1'];

        Http::fake(function (Request $request) use ($imageServer, $wms, $ia) {
            $url = $request->url();

            if (str_contains($url, 'exportImage')) {
                return $imageServer === 200 ? Http::response(self::PNG, 200, ['Content-Type' => 'image/png']) : Http::response('erro', $imageServer);
            }

            if (str_contains($url, 'mapeamento.salvador')) {
                return $wms === 200 ? Http::response(self::PNG, 200, ['Content-Type' => 'image/png']) : Http::response('erro', $wms);
            }

            if (str_contains($url, 'ia.local/segmentar')) {
                return is_int($ia) ? Http::response('fora', $ia) : Http::response($ia);
            }

            return Http::response('inesperado', 404);
        });
    }

    private function rodar(ViabilityRequest $request): void
    {
        app()->call([new GerarPoligonoIaJob($request->id), 'handle']);
    }

    public function test_aceita_e_grava_fonte_ia_sem_confianca(): void
    {
        $this->fake();
        $request = $this->processo();

        $this->rodar($request);

        $request->refresh();
        $this->assertSame(PoligonoFonte::Ia, $request->address_polygon_source);
        $this->assertSame('MultiPolygon', $request->address_polygon_geojson['type']);
        $predio = BuildingFootprint::query()->where('source', FonteEdificacao::Ia)->sole();
        $this->assertNull($predio->confidence);
        $this->assertSame(0.93, $predio->properties['score_modelo']);
        $this->assertSame('sam2.1-hiera-small@1+imageserver-2024', $predio->release);
        $resolucao = LotPolygonResolution::query()->where('status', StatusResolucaoPoligono::Encontrado)->sole();
        $this->assertSame(PoligonoFonte::Ia, $resolucao->source);
        $this->assertNull($resolucao->confidence);
        Queue::assertPushed(DecidirFluxoExpressoJob::class);
    }

    public function test_imagem_2024_indisponivel_usa_2016(): void
    {
        $this->fake(imageServer: 500);
        $request = $this->processo();

        $this->rodar($request);

        $this->assertSame(PoligonoFonte::Ia, $request->fresh()->address_polygon_source);
        $this->assertSame('sam2.1-hiera-small@1+wms-2016-2017', BuildingFootprint::query()->sole()->release);
    }

    public function test_sem_imagem_registra_nao_resolvido(): void
    {
        $this->fake(imageServer: 500, wms: 500);
        $request = $this->processo();

        $this->rodar($request);

        $this->assertNull($request->fresh()->address_polygon_geojson);
        $resolucao = LotPolygonResolution::query()->sole();
        $this->assertSame(StatusResolucaoPoligono::NaoResolvido, $resolucao->status);
        $this->assertSame('imagem_indisponivel', $resolucao->reason);
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'ia.local'));
        Queue::assertPushed(DecidirFluxoExpressoJob::class);
    }

    public function test_servico_fora_registra_indisponivel(): void
    {
        $this->fake(ia: 503);
        $request = $this->processo();

        $this->rodar($request);

        $resolucao = LotPolygonResolution::query()->sole();
        $this->assertSame(StatusResolucaoPoligono::Indisponivel, $resolucao->status);
        $this->assertSame('servico_fora', $resolucao->reason);
        $this->assertNull($request->fresh()->address_polygon_geojson);
    }

    public function test_sem_url_do_servico_registra_indisponivel(): void
    {
        config(['viabiliza.integrations.edificacoes.ia_url' => null]);
        $this->fake();
        $request = $this->processo();

        $this->rodar($request);

        $this->assertSame('servico_fora', LotPolygonResolution::query()->sole()->reason);
    }

    public function test_mascara_recusada_nao_grava_nada(): void
    {
        $this->fake(ia: ['status' => 'ok', 'anel' => [[600, 600], [700, 600], [700, 700], [600, 700]], 'score' => 0.5, 'modelo' => 'sam2']);
        $request = $this->processo();

        $this->rodar($request);

        $this->assertNull($request->fresh()->address_polygon_geojson);
        $this->assertSame(0, BuildingFootprint::query()->count());
        $resolucao = LotPolygonResolution::query()->sole();
        $this->assertSame(StatusResolucaoPoligono::NaoResolvido, $resolucao->status);
        $this->assertSame('nao_contem_o_ponto', $resolucao->reason);
    }

    public function test_recorte_reaproveitado_do_cache(): void
    {
        $this->fake(ia: ['status' => 'sem_objeto', 'anel' => [], 'score' => null, 'modelo' => 'sam2']);

        $this->rodar($this->processo());
        $this->rodar($this->processo());

        $this->assertCount(1, Http::recorded(fn (Request $r): bool => str_contains($r->url(), 'exportImage')));
    }

    public function test_reaproveita_poligono_ia_gerado_por_outro_job(): void
    {
        $this->fake();
        $this->base->comCandidata(FonteEdificacao::Ia, ['type' => 'MultiPolygon', 'coordinates' => [[[[-38.4601, -12.9801], [-38.4599, -12.9801], [-38.4599, -12.9799], [-38.4601, -12.9801]]]]]);
        $request = $this->processo();

        $this->rodar($request);

        $this->assertSame(PoligonoFonte::Ia, $request->fresh()->address_polygon_source);
        Http::assertNothingSent();
    }

    public function test_nao_despacha_segunda_inferencia_para_o_mesmo_ponto(): void
    {
        Queue::fake([GerarPoligonoIaJob::class]);

        GerarPoligonoIaJob::dispatch($this->processo()->id);
        GerarPoligonoIaJob::dispatch($this->processo()->id);

        Queue::assertPushed(GerarPoligonoIaJob::class, 1);
    }
}
