<?php

namespace Tests\Feature\Geo;

use App\Enums\FonteEdificacao;
use App\Enums\GeoLayerType;
use App\Models\BuildingFootprint;
use App\Models\BuildingFootprintImport;
use App\Models\GeoLayer;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use App\Services\Geo\Edificacoes\Quadkey;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

#[Group('postgis')]
class ImportarEdificacoesMicrosoftPostgisTest extends PostgisTestCase
{
    private const LAT = -12.98;

    private const LNG = -38.46;

    protected function setUp(): void
    {
        parent::setUp();

        $layer = GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::LimiteMunicipio, 'version' => 'limite-teste']);
        DB::table('geo_features')->insert([
            'geo_layer_id' => $layer->id,
            'geometry' => DB::raw("ST_SetSRID(ST_GeomFromText('POLYGON((-38.70 -13.02, -38.30 -13.02, -38.30 -12.73, -38.70 -12.73, -38.70 -13.02))'), 4326)"),
            'properties' => json_encode(['MUNICIPIOS' => 'SALVADOR']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function quadrado(float $ladoM, float $dxM = 0.0, float $dyM = 0.0): array
    {
        $grauLat = 1 / 110574;
        $grauLng = 1 / (111320 * cos(deg2rad(self::LAT)));
        $x0 = self::LNG + $dxM * $grauLng;
        $y0 = self::LAT + $dyM * $grauLat;
        $x1 = $x0 + $ladoM * $grauLng;
        $y1 = $y0 + $ladoM * $grauLat;

        return ['type' => 'Polygon', 'coordinates' => [[[$x0, $y0], [$x1, $y0], [$x1, $y1], [$x0, $y1], [$x0, $y0]]]];
    }

    /**
     * @param  array<string, mixed>  $geometria
     */
    private function linha(array $geometria, float $confidence = 0.87, float $height = -1.0): string
    {
        return json_encode(['type' => 'Feature', 'properties' => ['height' => $height, 'confidence' => $confidence], 'geometry' => $geometria]);
    }

    /**
     * @param  list<string>  $linhas
     */
    private function gz(array $linhas): string
    {
        return gzencode(implode("\n", $linhas)."\n");
    }

    private function arquivoGz(array $linhas): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'ms').'.csv.gz';
        file_put_contents($caminho, $this->gz($linhas));

        return $caminho;
    }

    public function test_importa_arquivo_local_e_marca_duplicado_da_overture(): void
    {
        $repositorio = app(BuildingFootprintRepository::class);
        $normal = $repositorio->normalizar($this->quadrado(10));
        $overture = $repositorio->upsert(FonteEdificacao::Overture, 'gers-1', '2026-09-23.1', $normal['geojson'], $normal['area_m2'], null, null, [], null, null, [])['id'];

        $this->artisan('geo:edificacoes:importar-microsoft', [
            '--arquivo' => [$this->arquivoGz([$this->linha($this->quadrado(10, 1, 1)), $this->linha($this->quadrado(12, 40, 0))])],
            '--release' => '2026-08-13',
        ])->assertSuccessful();

        $importacao = BuildingFootprintImport::query()->latest('id')->first();
        $this->assertSame(BuildingFootprintImport::CONCLUIDA, $importacao->status);
        $this->assertSame(2, $importacao->counters['inseridos']);
        $this->assertSame(1, $importacao->counters['duplicados']);
        $this->assertSame(1, BuildingFootprint::query()->where('source', FonteEdificacao::Microsoft)->where('duplicate_of_id', $overture)->count());
        $this->assertSame(1, BuildingFootprint::query()->where('source', FonteEdificacao::Microsoft)->whereNull('duplicate_of_id')->count());
    }

    public function test_confidence_menos_um_vira_nulo(): void
    {
        $this->artisan('geo:edificacoes:importar-microsoft', [
            '--arquivo' => [$this->arquivoGz([$this->linha($this->quadrado(10), -1.0, 7.5)])],
            '--release' => '2026-08-13',
        ])->assertSuccessful();

        $predio = BuildingFootprint::query()->where('source', FonteEdificacao::Microsoft)->first();
        $this->assertNull($predio->confidence);
        $this->assertSame(7.5, $predio->properties['height']);
    }

    public function test_mesma_geometria_gera_o_mesmo_id_original(): void
    {
        $arquivo = $this->arquivoGz([$this->linha($this->quadrado(10))]);

        $this->artisan('geo:edificacoes:importar-microsoft', ['--arquivo' => [$arquivo], '--release' => '2026-08-13'])->assertSuccessful();
        $this->artisan('geo:edificacoes:importar-microsoft', ['--arquivo' => [$this->arquivoGz([$this->linha($this->quadrado(10), 0.5)])], '--release' => '2026-11-01'])->assertSuccessful();

        $this->assertSame(1, BuildingFootprint::query()->where('source', FonteEdificacao::Microsoft)->count());
    }

    public function test_baixa_so_brasil_e_quadkeys_do_bbox(): void
    {
        $dentro = Quadkey::cobrindo(-38.47, -12.99, -38.45, -12.97)[0];
        $csv = "Location,QuadKey,Url,Size,UploadDate\n"
            ."Brazil,{$dentro},https://minedbuildings.z5.web.core.windows.net/global-buildings/2026-08-13/global-buildings.geojsonl/RegionName=Brazil/quadkey={$dentro}/part.csv.gz,1.2MB,2026-08-13\n"
            ."Brazil,000000000,https://minedbuildings.z5.web.core.windows.net/global-buildings/2026-08-13/global-buildings.geojsonl/RegionName=Brazil/quadkey=000000000/part.csv.gz,3MB,2026-08-13\n"
            ."Argentina,{$dentro},https://minedbuildings.z5.web.core.windows.net/global-buildings/2026-08-13/global-buildings.geojsonl/RegionName=Argentina/quadkey={$dentro}/part.csv.gz,1MB,2026-08-13\n";

        Http::fake([
            '*dataset-links.csv' => Http::response($csv),
            '*part.csv.gz' => Http::response($this->gz([$this->linha($this->quadrado(10))])),
        ]);

        $this->artisan('geo:edificacoes:importar-microsoft', ['--release' => '2026-08-13'])->assertSuccessful();

        $tiles = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), 'part.csv.gz'));
        $this->assertCount(1, $tiles);
        $this->assertStringContainsString("RegionName=Brazil/quadkey={$dentro}", $tiles->first()[0]->url());
        $this->assertSame(1, BuildingFootprint::query()->where('source', FonteEdificacao::Microsoft)->count());
    }
}
