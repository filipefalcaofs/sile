<?php

namespace Tests\Feature\Geo;

use App\Enums\FonteEdificacao;
use App\Enums\GeoLayerType;
use App\Models\BuildingFootprint;
use App\Models\BuildingFootprintImport;
use App\Models\GeoLayer;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

#[Group('postgis')]
class ImportarEdificacoesOverturePostgisTest extends PostgisTestCase
{
    private const AMOSTRA = 'tests/Fixtures/geo/overture-amostra.geojsonseq';

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
     * Arquivo temporário com as linhas informadas da amostra (índices 0-based) e um final opcional.
     *
     * @param  list<int>  $linhas
     */
    private function arquivoCom(array $linhas, string $final = ''): string
    {
        $origem = file(base_path(self::AMOSTRA), FILE_IGNORE_NEW_LINES);
        $caminho = tempnam(sys_get_temp_dir(), 'overture').'.geojsonseq';
        file_put_contents($caminho, implode("\n", array_map(fn (int $i): string => $origem[$i], $linhas))."\n".$final);

        return $caminho;
    }

    private function ativas(): int
    {
        return BuildingFootprint::query()->where('source', FonteEdificacao::Overture)->whereNull('retired_at')->count();
    }

    public function test_importa_e_recorta_pelo_limite(): void
    {
        $this->artisan('geo:edificacoes:importar-overture', ['arquivo' => base_path(self::AMOSTRA), '--release' => '2026-09-23.1'])
            ->assertSuccessful();

        $importacao = BuildingFootprintImport::query()->latest('id')->first();
        $this->assertSame(BuildingFootprintImport::CONCLUIDA, $importacao->status);
        $this->assertSame(5, $importacao->counters['lidos']);
        $this->assertSame(3, $importacao->counters['inseridos']);
        $this->assertSame(1, $importacao->counters['fora_do_limite']);
        $this->assertSame(1, $importacao->counters['invalidos']);
        $this->assertSame(3, $this->ativas());

        $primeiro = BuildingFootprint::query()->where('source_id', '00000001-gers-0000-0000-000000000001')->first();
        $this->assertEqualsWithDelta(0.91, $primeiro->confidence, 0.0001);
        $this->assertSame(6.5, $primeiro->properties['height']);
        $this->assertSame('OpenStreetMap', $primeiro->source_datasets[0]['dataset']);
        $this->assertSame($importacao->id, $primeiro->import_id);
    }

    public function test_reimportar_mesma_release_e_checksum_nao_altera(): void
    {
        $this->artisan('geo:edificacoes:importar-overture', ['arquivo' => base_path(self::AMOSTRA), '--release' => '2026-09-23.1'])->assertSuccessful();
        $antes = BuildingFootprint::query()->max('updated_at');

        $this->artisan('geo:edificacoes:importar-overture', ['arquivo' => base_path(self::AMOSTRA), '--release' => '2026-09-23.1'])
            ->expectsOutputToContain('já importada')
            ->assertSuccessful();

        $this->assertSame(BuildingFootprintImport::IGNORADA, BuildingFootprintImport::query()->latest('id')->first()->status);
        $this->assertSame($antes, BuildingFootprint::query()->max('updated_at'));
        $this->assertSame(3, $this->ativas());
    }

    public function test_release_nova_aposenta_ausentes(): void
    {
        $this->artisan('geo:edificacoes:importar-overture', ['arquivo' => base_path(self::AMOSTRA), '--release' => '2026-09-23.1'])->assertSuccessful();

        $this->artisan('geo:edificacoes:importar-overture', ['arquivo' => $this->arquivoCom([0, 1]), '--release' => '2026-10-21.0'])->assertSuccessful();

        $importacao = BuildingFootprintImport::query()->latest('id')->first();
        $this->assertSame(2, $importacao->counters['atualizados']);
        $this->assertSame(1, $importacao->counters['aposentados']);
        $this->assertSame(2, $this->ativas());
        $this->assertNotNull(BuildingFootprint::query()->where('source_id', '00000003-gers-0000-0000-000000000003')->value('retired_at'));
    }

    public function test_carga_parcial_nao_aposenta_o_resto_da_cidade(): void
    {
        $this->artisan('geo:edificacoes:importar-overture', ['arquivo' => base_path(self::AMOSTRA), '--release' => '2026-09-23.1'])->assertSuccessful();

        $this->artisan('geo:edificacoes:importar-overture', ['arquivo' => $this->arquivoCom([0]), '--release' => '2026-10-21.0'])->assertSuccessful();

        $importacao = BuildingFootprintImport::query()->latest('id')->first();
        $this->assertSame(0, $importacao->counters['aposentados']);
        $this->assertSame(1, $importacao->counters['aposentadoria_suspensa']);
        $this->assertSame(3, $this->ativas());
    }

    public function test_falha_no_meio_preserva_base_anterior(): void
    {
        $this->artisan('geo:edificacoes:importar-overture', ['arquivo' => base_path(self::AMOSTRA), '--release' => '2026-09-23.1'])->assertSuccessful();

        $this->artisan('geo:edificacoes:importar-overture', ['arquivo' => $this->arquivoCom([0], '{"type":"Feature","geometry":{"type":"Polyg'), '--release' => '2026-10-21.0'])
            ->assertFailed();

        $importacao = BuildingFootprintImport::query()->latest('id')->first();
        $this->assertSame(BuildingFootprintImport::FALHOU, $importacao->status);
        $this->assertNotNull($importacao->error);
        $this->assertSame(3, $this->ativas());
        $this->assertSame(3, BuildingFootprint::query()->where('release', '2026-09-23.1')->count());
    }

    public function test_sem_limite_recusa(): void
    {
        DB::table('geo_features')->delete();

        $this->artisan('geo:edificacoes:importar-overture', ['arquivo' => base_path(self::AMOSTRA), '--release' => '2026-09-23.1'])
            ->expectsOutputToContain('Importe o limite do município antes: geo:importar-wfs limite_municipio limite_salvador:VM_LIMITE_SALVADOR')
            ->assertFailed();

        $this->assertSame(0, BuildingFootprint::query()->count());
    }
}
