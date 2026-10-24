<?php

namespace Tests\Feature\Geo;

use App\Enums\FonteEdificacao;
use App\Enums\PoligonoFonte;
use App\Enums\PropertyLocationSource;
use App\Enums\StatusResolucaoPoligono;
use App\Enums\ViabilityRequestStatus;
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
        $request = ViabilityRequest::factory()->create(['property_polygon_geojson' => null, 'status' => ViabilityRequestStatus::Protocolada]);
        $request->forceFill([
            'property_point_lat' => $lat,
            'property_point_lng' => $lng,
            'property_location_source' => PropertyLocationSource::CadastroImobiliario,
        ])->save();

        return $request->fresh();
    }

    /**
     * @param  array<string, mixed>|int  $ia  resposta do serviço ou status HTTP de erro
     * @param  list<array<string, mixed>>  $iaPorRecorte  respostas do serviço na ordem dos recortes (60, 120, 240 m)
     */
    private function fake(int $imageServer = 200, int $wms = 200, array|int|null $ia = null, array $iaPorRecorte = [], ?string $png2024 = null, ?string $png2016 = null): void
    {
        $ia ??= ['status' => 'ok', 'anel' => [[350, 350], [450, 350], [450, 450], [350, 450]], 'score' => 0.93, 'modelo' => 'sam2.1-hiera-small@1'];

        Http::fake(function (Request $request) use ($imageServer, $wms, $ia, &$iaPorRecorte, $png2024, $png2016) {
            $url = $request->url();

            if (str_contains($url, 'exportImage')) {
                return $imageServer === 200 ? Http::response($png2024 ?? self::PNG, 200, ['Content-Type' => 'image/png']) : Http::response('erro', $imageServer);
            }

            if (str_contains($url, 'mapeamento.salvador')) {
                return $wms === 200 ? Http::response($png2016 ?? self::PNG, 200, ['Content-Type' => 'image/png']) : Http::response('erro', $wms);
            }

            if (str_contains($url, 'ia.local/segmentar')) {
                if ($iaPorRecorte !== []) {
                    return Http::response(array_shift($iaPorRecorte));
                }

                return is_int($ia) ? Http::response('fora', $ia) : Http::response($ia);
            }

            return Http::response('inesperado', 404);
        });
    }

    private function pngReal(bool $corUnica): string
    {
        $imagem = imagecreatetruecolor(64, 64);

        for ($x = 0; $x < 64; $x++) {
            for ($y = 0; $y < 64; $y++) {
                imagesetpixel($imagem, $x, $y, $corUnica ? 0xFDFDFD : (($x * 37 + $y * 91) % 0xFFFFFF));
            }
        }

        ob_start();
        imagepng($imagem);

        return (string) ob_get_clean();
    }

    /**
     * @param  list<array{0: list<array{0: int, 1: int}>, 1: float, 2: bool}>  $candidatos  anel, score e se encosta
     * @return array<string, mixed>
     */
    private function resposta(array $candidatos): array
    {
        $lista = array_map(fn (array $c): array => ['anel' => $c[0], 'score' => $c[1], 'encosta' => $c[2]], $candidatos);
        $melhor = $lista === [] ? null : array_reduce($lista, fn (?array $a, array $c): array => $a === null || $c['score'] > $a['score'] ? $c : $a);

        return ['status' => $lista === [] ? 'sem_objeto' : 'ok', 'anel' => $melhor['anel'] ?? [], 'score' => $melhor['score'] ?? null, 'candidatos' => $lista, 'modelo' => 'sam2.1-hiera-small+decodificador@cpu'];
    }

    private function ladoLesteOesteM(ViabilityRequest $request): float
    {
        $lngs = array_column($request->fresh()->address_polygon_geojson['coordinates'][0][0], 0);

        return (max($lngs) - min($lngs)) * 111320 * cos(deg2rad(-12.98));
    }

    public function test_ortofoto_2024_em_branco_usa_a_de_2016(): void
    {
        $this->fake(png2024: $this->pngReal(corUnica: true), png2016: $this->pngReal(corUnica: false));
        $request = $this->processo();

        $this->rodar($request);

        $this->assertSame(PoligonoFonte::Ia, $request->fresh()->address_polygon_source);
        $this->assertStringEndsWith('+wms-2016-2017', BuildingFootprint::query()->sole()->release);
    }

    public function test_recorte_cresce_ate_o_contorno_nao_encostar_na_borda(): void
    {
        $quadrado = [[350, 350], [450, 350], [450, 450], [350, 450]];
        $naBorda = [[0, 300], [500, 300], [500, 500], [0, 500]];
        $this->fake(iaPorRecorte: [
            $this->resposta([[$naBorda, 0.9, true], [$quadrado, 0.5, false]]),
            $this->resposta([[$quadrado, 0.8, false]]),
        ]);
        $request = $this->processo();

        $this->rodar($request);

        $exportacoes = Http::recorded(fn (Request $r): bool => str_contains($r->url(), 'exportImage'));
        $this->assertCount(2, $exportacoes);
        $this->assertSame(PoligonoFonte::Ia, $request->fresh()->address_polygon_source);
        $this->assertEqualsWithDelta(15.0, $this->ladoLesteOesteM($request), 0.5);
    }

    public function test_fica_o_candidato_de_maior_score_entre_os_que_nao_encostam(): void
    {
        $pequeno = [[390, 390], [410, 390], [410, 410], [390, 410]];
        $grande = [[300, 300], [500, 300], [500, 500], [300, 500]];
        $this->fake(iaPorRecorte: [$this->resposta([[$pequeno, 0.6, false], [$grande, 0.9, false]])]);
        $request = $this->processo();

        $this->rodar($request);

        $this->assertEqualsWithDelta(15.0, $this->ladoLesteOesteM($request), 0.5);
        $this->assertCount(1, Http::recorded(fn (Request $r): bool => str_contains($r->url(), 'exportImage')));
    }

    public function test_no_ultimo_recorte_aceita_o_que_nao_encosta_mesmo_com_outros_na_borda(): void
    {
        $quadrado = [[350, 350], [450, 350], [450, 450], [350, 450]];
        $naBorda = [[0, 300], [500, 300], [500, 500], [0, 500]];
        $resposta = $this->resposta([[$naBorda, 0.9, true], [$quadrado, 0.4, false]]);
        $this->fake(iaPorRecorte: [$resposta, $resposta, $resposta]);
        $request = $this->processo();

        $this->rodar($request);

        $this->assertCount(3, Http::recorded(fn (Request $r): bool => str_contains($r->url(), 'exportImage')));
        $this->assertSame(PoligonoFonte::Ia, $request->fresh()->address_polygon_source);
        $this->assertEqualsWithDelta(30.0, $this->ladoLesteOesteM($request), 0.5);
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
        $antes = count(Http::recorded(fn (Request $r): bool => str_contains($r->url(), 'exportImage')));
        $this->rodar($this->processo());

        $this->assertSame(3, $antes);
        $this->assertCount(3, Http::recorded(fn (Request $r): bool => str_contains($r->url(), 'exportImage')));
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

    public function test_processo_ja_decidido_guarda_o_predio_mas_nao_recebe_o_poligono(): void
    {
        $this->fake();
        $request = $this->processo();
        $request->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();

        $this->rodar($request);

        $this->assertNull($request->fresh()->address_polygon_geojson);
        $this->assertSame(1, BuildingFootprint::query()->where('source', FonteEdificacao::Ia)->count());
        Queue::assertNotPushed(DecidirFluxoExpressoJob::class);
    }

    public function test_prazo_esgotado_nao_recebe_o_poligono(): void
    {
        $this->fake();
        $request = $this->processo();
        LotPolygonResolution::factory()->create([
            'viability_request_id' => $request->id,
            'source' => PoligonoFonte::Ia,
            'status' => StatusResolucaoPoligono::NaoResolvido,
            'reason' => 'prazo_esgotado',
        ]);

        $this->rodar($request);

        $this->assertNull($request->fresh()->address_polygon_geojson);
        Queue::assertNotPushed(DecidirFluxoExpressoJob::class);
    }

    public function test_nao_despacha_segunda_inferencia_para_o_mesmo_ponto(): void
    {
        Queue::fake([GerarPoligonoIaJob::class]);

        GerarPoligonoIaJob::dispatch($this->processo()->id);
        GerarPoligonoIaJob::dispatch($this->processo()->id);

        Queue::assertPushed(GerarPoligonoIaJob::class, 1);
    }
}
