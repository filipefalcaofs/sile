<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerType;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ZonaFonte;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Geo\SpatialRepository;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\Geo\FakeZonaMaiorPorcaoResolver;
use Tests\TestCase;

class PoligonoEnderecamentoProtocoloTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FakeSpatialRepository $espacial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

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

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function rascunhoRegin(array $atributos = []): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'property_polygon_geojson' => null,
            'property_registration' => '344672',
            'used_area_m2' => 120,
            ...$atributos,
        ]);

        $cnae = Cnae::factory()->create();
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);

        return $solicitacao;
    }

    private function fakeCadastro(?string $codLog, ?float $lat = -12.977769, ?float $lng = -38.516404, bool $indisponivel = false): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class($codLog, $lat, $lng, $indisponivel) implements PropertyRegistryLookup
        {
            public function __construct(
                private ?string $codLog,
                private ?float $lat,
                private ?float $lng,
                private bool $indisponivel,
            ) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                if ($this->indisponivel) {
                    throw new PropertyRegistryUnavailableException($inscricao);
                }

                return new PropertyRegistryResult(
                    latitude: $this->lat,
                    longitude: $this->lng,
                    inscricao: $inscricao,
                    raw: [
                        'CodigoLogradouroTributario' => $this->codLog,
                        'LogradouroTributario' => 'Avenida Lafayete Coutinho',
                    ],
                );
            }
        });
    }

    private function ligarEnderecamento(bool $ligado = true): void
    {
        Parameter::query()->updateOrCreate(
            ['key' => 'features.geoserver_enderecamento'],
            ['group' => 'features', 'type' => 'boolean', 'value' => $ligado ? '1' : '0', 'default_value' => '1', 'validation_rules' => ['required', 'boolean'], 'description' => 'Polígono do endereçamento.'],
        );
        cache()->forget('sile.parameters.features.geoserver_enderecamento');
    }

    /**
     * @return array<string, mixed>
     */
    private function quadrado(float $lng): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[$lng, -12.98], [$lng + 0.0005, -12.98], [$lng + 0.0005, -12.9795], [$lng, -12.9795], [$lng, -12.98]]]];
    }

    /**
     * @param  list<array{complemento: ?string, geometria: array<string, mixed>}>  $feicoes
     */
    private function enderecamento(array $feicoes): void
    {
        Http::fake(function (Request $request) use ($feicoes) {
            if (! str_contains($request->url(), 'VM_ENDERECAMENTO')) {
                return Http::response(['type' => 'FeatureCollection', 'features' => []]);
            }

            return Http::response(['type' => 'FeatureCollection', 'features' => array_map(fn (array $f): array => [
                'type' => 'Feature',
                'geometry' => $f['geometria'],
                'properties' => ['COMPLEMENTO' => $f['complemento']],
            ], $feicoes)]);
        });
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function protocolar(array $atributos = []): ViabilityRequest
    {
        $solicitacao = $this->rascunhoRegin(['address_number' => '70', ...$atributos]);
        app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, User::factory()->create());

        return $solicitacao->fresh();
    }

    /**
     * A cadeia audita uma linha por FONTE tentada (endereçamento e, quando
     * ele não acha, cadastro municipal). Estes casos são do endereçamento.
     */
    private function auditoria(ViabilityRequest $request, string $fonte = 'enderecamento'): ?Activity
    {
        return Activity::query()
            ->where('event', 'enderecamento-poligono')
            ->where('subject_id', $request->id)
            ->get()
            ->last(fn (Activity $activity): bool => ($activity->properties['fonte'] ?? 'enderecamento') === $fonte);
    }

    public function test_ca_pe_01_um_poligono_e_gravado_com_a_data_da_consulta(): void
    {
        $this->fakeCadastro('212');
        $this->ligarEnderecamento();
        $this->enderecamento([['complemento' => null, 'geometria' => $this->quadrado(-38.5)]]);

        $request = $this->protocolar();

        $this->assertSame($this->quadrado(-38.5), $request->address_polygon_geojson);
        $this->assertNotNull($request->address_polygon_consulted_at);
        $this->assertSame('encontrado', $this->auditoria($request)?->properties['status']);
    }

    public function test_ca_pe_02_tres_geometrias_sem_complemento_nao_grava_e_audita_ambiguo(): void
    {
        $this->fakeCadastro('212');
        $this->ligarEnderecamento();
        $this->enderecamento([
            ['complemento' => 'BL-3', 'geometria' => $this->quadrado(-38.5)],
            ['complemento' => 'BL-25', 'geometria' => $this->quadrado(-38.6)],
            ['complemento' => 'BL-20', 'geometria' => $this->quadrado(-38.7)],
        ]);

        $request = $this->protocolar();

        $this->assertNull($request->address_polygon_geojson);
        $this->assertSame('ambiguo', $this->auditoria($request)?->properties['status']);
        $this->assertSame(3, $this->auditoria($request)?->properties['candidatas']);
    }

    public function test_ca_pe_03_complemento_que_casa_grava_a_feicao_dele(): void
    {
        $this->fakeCadastro('212');
        $this->ligarEnderecamento();
        $this->enderecamento([
            ['complemento' => 'BL-3', 'geometria' => $this->quadrado(-38.5)],
            ['complemento' => 'BL-25', 'geometria' => $this->quadrado(-38.6)],
            ['complemento' => 'BL-20', 'geometria' => $this->quadrado(-38.7)],
        ]);

        $request = $this->protocolar(['address_complement' => 'BL-25']);

        $this->assertSame($this->quadrado(-38.6), $request->address_polygon_geojson);
    }

    public function test_ca_pe_04_subunidades_com_o_mesmo_poligono_gravam(): void
    {
        $this->fakeCadastro('212');
        $this->ligarEnderecamento();
        $this->enderecamento([
            ['complemento' => null, 'geometria' => $this->quadrado(-38.5)],
            ['complemento' => null, 'geometria' => $this->quadrado(-38.5)],
        ]);

        $this->assertSame($this->quadrado(-38.5), $this->protocolar()->address_polygon_geojson);
    }

    public function test_ca_pe_05_sem_feicao_nao_grava_e_segue_o_fluxo(): void
    {
        $this->fakeCadastro('212');
        $this->ligarEnderecamento();
        $this->enderecamento([]);

        $request = $this->protocolar();

        $this->assertNull($request->address_polygon_geojson);
        $this->assertNotNull($request->protocol_number);
        $this->assertSame('nao_encontrado', $this->auditoria($request)?->properties['status']);
    }

    public function test_ca_pe_06_desligado_nao_consulta_e_a_zona_fica_pelo_ponto(): void
    {
        $this->fakeCadastro('212');
        $this->ligarEnderecamento(false);
        Http::fake();

        $request = $this->protocolar();

        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'VM_ENDERECAMENTO'));
        $this->assertNull($request->address_polygon_geojson);
        $this->assertSame('ZCN-1', $request->zona_codigo);
        $this->assertSame(ZonaFonte::Ponto, $request->zona_fonte);
        $this->assertSame('desligado', $this->auditoria($request)?->properties['status']);
    }

    public function test_sem_numero_nao_consulta_e_audita_sem_chave(): void
    {
        $this->fakeCadastro('212');
        $this->ligarEnderecamento();
        Http::fake();

        $request = $this->protocolar(['address_number' => null]);

        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'VM_ENDERECAMENTO'));
        $this->assertSame('sem_chave', $this->auditoria($request)?->properties['status']);
    }

    public function test_protocolo_com_poligono_decide_a_zona_pela_maior_porcao(): void
    {
        $this->fakeCadastro('212');
        $this->ligarEnderecamento();
        $this->enderecamento([['complemento' => null, 'geometria' => $this->quadrado(-38.5)]]);
        $this->app->instance(ZonaMaiorPorcaoResolver::class, (new FakeZonaMaiorPorcaoResolver)
            ->para($this->quadrado(-38.5), FakeZonaMaiorPorcaoResolver::identificado('ZPR 3')));

        $request = $this->protocolar();

        $this->assertSame('ZPR 3', $request->zona_codigo);
        $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->zona_fonte);
    }
}
