<?php

namespace Tests\Feature\Geo;

use App\Enums\FonteEdificacao;
use App\Enums\GeoLayerType;
use App\Models\BuildingFootprint;
use App\Models\GeoLayer;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

#[Group('postgis')]
class BuildingFootprintRepositoryPostgisTest extends PostgisTestCase
{
    private const LAT = -12.98;

    private const LNG = -38.46;

    private function repositorio(): BuildingFootprintRepository
    {
        return app(BuildingFootprintRepository::class);
    }

    /**
     * Quadrado de lado em metros, com canto inferior esquerdo deslocado do ponto base.
     *
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
     * @param  array<string, mixed>  $geojson
     */
    private function predio(FonteEdificacao $fonte, array $geojson, array $atributos = []): int
    {
        $normal = $this->repositorio()->normalizar($geojson);

        return $this->repositorio()->upsert(
            fonte: $fonte,
            sourceId: $atributos['source_id'] ?? uniqid('p', true),
            release: '2026-09-23.1',
            geojson: $normal['geojson'],
            areaM2: $normal['area_m2'],
            confidence: null,
            sourceDatasets: null,
            properties: [],
            importId: null,
            duplicateOfId: $atributos['duplicate_of_id'] ?? null,
            notas: $normal['notas'],
        )['id'];
    }

    private function limite(): void
    {
        $layer = GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::LimiteMunicipio, 'version' => 'limite-teste']);

        DB::table('geo_features')->insert([
            'geo_layer_id' => $layer->id,
            'geometry' => DB::raw("ST_SetSRID(ST_GeomFromText('POLYGON((-38.70 -13.02, -38.30 -13.02, -38.30 -12.73, -38.70 -12.73, -38.70 -13.02))'), 4326)"),
            'properties' => json_encode(['MUNICIPIOS' => 'SALVADOR']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_predio_que_contem_o_ponto(): void
    {
        $id = $this->predio(FonteEdificacao::Overture, $this->quadrado(10, -5, -5), ['source_id' => 'gers-1']);

        $candidata = $this->repositorio()->contendoPonto(FonteEdificacao::Overture, self::LAT, self::LNG);

        $this->assertNotNull($candidata);
        $this->assertSame($id, $candidata->id);
        $this->assertSame('gers-1', $candidata->sourceId);
        $this->assertSame('contem_ponto', $candidata->metodo);
        $this->assertEqualsWithDelta(100.0, $candidata->areaM2, 2.0);
        $this->assertSame('MultiPolygon', $candidata->geojson['type']);
    }

    public function test_ponto_fora_de_qualquer_predio_devolve_nulo(): void
    {
        $this->predio(FonteEdificacao::Overture, $this->quadrado(10, 3, 3));

        $this->assertNull($this->repositorio()->contendoPonto(FonteEdificacao::Overture, self::LAT, self::LNG));
    }

    public function test_sobrepostos_vence_o_de_menor_area(): void
    {
        $this->predio(FonteEdificacao::Overture, $this->quadrado(40, -20, -20));
        $menor = $this->predio(FonteEdificacao::Overture, $this->quadrado(10, -5, -5));

        $candidata = $this->repositorio()->contendoPonto(FonteEdificacao::Overture, self::LAT, self::LNG);

        $this->assertSame($menor, $candidata->id);
        $this->assertSame('menor_area_entre_sobrepostos', $candidata->metodo);
    }

    public function test_fonte_diferente_nao_entra(): void
    {
        $this->predio(FonteEdificacao::Microsoft, $this->quadrado(10, -5, -5));

        $this->assertNull($this->repositorio()->contendoPonto(FonteEdificacao::Overture, self::LAT, self::LNG));
    }

    public function test_ignora_aposentado_e_duplicado(): void
    {
        $aposentado = $this->predio(FonteEdificacao::Microsoft, $this->quadrado(10, -5, -5));
        BuildingFootprint::query()->whereKey($aposentado)->update(['retired_at' => now()]);
        $overture = $this->predio(FonteEdificacao::Overture, $this->quadrado(30, -15, -15));
        $this->predio(FonteEdificacao::Microsoft, $this->quadrado(12, -6, -6), ['duplicate_of_id' => $overture]);

        $this->assertNull($this->repositorio()->contendoPonto(FonteEdificacao::Microsoft, self::LAT, self::LNG));
    }

    public function test_normalizar_corrige_gravata_borboleta(): void
    {
        $q = $this->quadrado(20, 0, 0)['coordinates'][0];
        $gravata = ['type' => 'Polygon', 'coordinates' => [[$q[0], $q[2], $q[1], $q[3], $q[0]]]];

        $normal = $this->repositorio()->normalizar($gravata);

        $this->assertTrue($normal['valida']);
        $this->assertSame('MultiPolygon', $normal['geojson']['type']);
        $this->assertContains('geometria_corrigida', $normal['notas']);
    }

    public function test_normalizar_recusa_area_fora_dos_limites(): void
    {
        $pequeno = $this->repositorio()->normalizar($this->quadrado(3));
        $grande = $this->repositorio()->normalizar($this->quadrado(150));

        $this->assertFalse($pequeno['valida']);
        $this->assertContains('area_fora_dos_limites', $pequeno['notas']);
        $this->assertFalse($grande['valida']);
    }

    public function test_normalizar_recusa_o_que_nao_e_poligono(): void
    {
        $normal = $this->repositorio()->normalizar(['type' => 'LineString', 'coordinates' => [[self::LNG, self::LAT], [self::LNG + 0.001, self::LAT]]]);

        $this->assertFalse($normal['valida']);
        $this->assertNull($normal['geojson']);
    }

    public function test_dentro_do_limite_usa_ponto_interior(): void
    {
        $this->assertFalse($this->repositorio()->limiteDisponivel());

        $this->limite();

        $this->assertTrue($this->repositorio()->limiteDisponivel());
        $this->assertTrue($this->repositorio()->dentroDoLimite($this->quadrado(10)));
        $this->assertFalse($this->repositorio()->dentroDoLimite(['type' => 'Polygon', 'coordinates' => [[
            [-38.80, -12.98], [-38.79, -12.98], [-38.79, -12.97], [-38.80, -12.97], [-38.80, -12.98],
        ]]]));
    }

    public function test_duplicado_overture_por_iou(): void
    {
        $overture = $this->predio(FonteEdificacao::Overture, $this->quadrado(10));

        $this->assertSame($overture, $this->repositorio()->duplicadoOverture($this->quadrado(10, 1, 1)));
        $this->assertNull($this->repositorio()->duplicadoOverture($this->quadrado(10, 6, 6)));
    }

    public function test_upsert_atualiza_pela_fonte_e_id_original(): void
    {
        $primeiro = $this->predio(FonteEdificacao::Overture, $this->quadrado(10), ['source_id' => 'gers-x']);
        BuildingFootprint::query()->whereKey($primeiro)->update(['retired_at' => now()]);

        $normal = $this->repositorio()->normalizar($this->quadrado(12));
        $segundo = $this->repositorio()->upsert(FonteEdificacao::Overture, 'gers-x', '2026-10-21.0', $normal['geojson'], $normal['area_m2'], 0.9, null, [], null, null, []);

        $this->assertSame($primeiro, $segundo['id']);
        $this->assertFalse($segundo['inserido']);
        $atual = BuildingFootprint::query()->find($primeiro);
        $this->assertNull($atual->retired_at);
        $this->assertSame('2026-10-21.0', $atual->release);
        $this->assertEqualsWithDelta(144.0, $atual->area_m2, 3.0);
    }
}
