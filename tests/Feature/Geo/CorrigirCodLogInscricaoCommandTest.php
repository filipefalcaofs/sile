<?php

namespace Tests\Feature\Geo;

use App\Enums\DecisionOutcome;
use App\Enums\GeoLayerType;
use App\Enums\PoligonoFonte;
use App\Enums\ViabilityRequestStatus;
use App\Enums\ZonaFonte;
use App\Models\GeoLayer;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Geo\ClassificacaoViariaHit;
use App\Services\Geo\EnderecamentoHit;
use App\Services\Geo\GeoServerClassificacaoViariaClient;
use App\Services\Geo\GeoServerEnderecamentoClient;
use App\Services\Geo\SemapLoteClient;
use App\Services\Geo\SpatialRepository;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\Geo\FakeZonaMaiorPorcaoResolver;
use Tests\TestCase;

class CorrigirCodLogInscricaoCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private GeoServerEnderecamentoClient $enderecamento;

    protected function setUp(): void
    {
        parent::setUp();

        config(['viabiliza.features.geoserver_enderecamento' => true]);

        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Via, 'version' => 'logradouro-2024']);

        $espacial = new FakeSpatialRepository;
        $espacial->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Graça']]);
        $espacial->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZCMu 2']]);
        $espacial->setNearest(GeoLayerType::Via, [
            'id' => 3,
            'properties' => ['CODLOG' => '958', 'NOME_LOGRADOURO' => 'Rua Luiz Murat'],
            'distancia_m' => 25.9,
        ]);
        $this->app->instance(SpatialRepository::class, $espacial);

        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.99,
                    longitude: -38.52,
                    inscricao: $inscricao,
                    raw: [
                        'CodigoLogradouroTributario' => '579',
                        'LogradouroTributario' => 'Avenida Euclydes da Cunha',
                        'ListaEndereco' => ['RetornoEndereco' => [[
                            'TipoEndereco' => 'Localização',
                            'CodigoLogradouro' => $inscricao === '1111111' ? '579' : '401',
                            'Logradouro' => 'Rua Catharina Paraguassú',
                        ]]],
                    ],
                );
            }
        });

        $this->app->instance(GeoServerClassificacaoViariaClient::class, new class extends GeoServerClassificacaoViariaClient
        {
            public function __construct() {}

            public function consultar(string $codLog): ClassificacaoViariaHit
            {
                return new ClassificacaoViariaHit(ClassificacaoViariaHit::IDENTIFICADO, $codLog === '401' ? 'VL' : 'VC I', null, null);
            }
        });

        $this->enderecamento = new class($this->lote(-38.52)) extends GeoServerEnderecamentoClient
        {
            /** Resposta da busca do lote pelo ponto do imóvel. */
            public EnderecamentoHit $porPonto;

            public int $consultasPorPonto = 0;

            /**
             * @param  array<string, mixed>  $lote
             */
            public function __construct(private array $lote)
            {
                $this->porPonto = new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO);
            }

            public function consultar(string $codLog, string $numero, ?string $complemento = null): EnderecamentoHit
            {
                return $codLog === '401' && $numero === '365'
                    ? new EnderecamentoHit(EnderecamentoHit::ENCONTRADO, $this->lote)
                    : new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO);
            }

            public function consultarPorPonto(float $lat, float $lng): EnderecamentoHit
            {
                $this->consultasPorPonto++;

                return $this->porPonto;
            }
        };
        $this->app->instance(GeoServerEnderecamentoClient::class, $this->enderecamento);

        $this->app->instance(SemapLoteClient::class, new class extends SemapLoteClient
        {
            public function __construct() {}

            public function consultar(?string $inscricao): EnderecamentoHit
            {
                return new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO);
            }
        });

        $this->app->instance(ZonaMaiorPorcaoResolver::class, (new FakeZonaMaiorPorcaoResolver)
            ->para($this->lote(-38.52), FakeZonaMaiorPorcaoResolver::identificado('ZCMe'))
            ->para($this->lote(-38.70), FakeZonaMaiorPorcaoResolver::identificado('ZPR 3')));
    }

    /**
     * @return array<string, mixed>
     */
    private function lote(float $lng): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[[$lng, -12.99], [$lng, -12.989], [$lng + 0.001, -12.989], [$lng + 0.001, -12.99], [$lng, -12.99]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function processo(array $atributos): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'property_registration' => $atributos['property_registration'] ?? '3693872',
            'address_number' => $atributos['address_number'] ?? '365',
        ]);

        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'cod_log' => '579',
            'camadas_incidentes' => ['via' => 'VC I'],
            'address_polygon_geojson' => $this->lote(-38.60),
            'address_polygon_source' => PoligonoFonte::Chave,
            'zona_codigo' => 'ZPR 3',
            'zona_fonte' => ZonaFonte::EnderecamentoMaiorPorcao,
            ...array_diff_key($atributos, array_flip(['property_registration', 'address_number'])),
        ])->save();

        return $processo->refresh();
    }

    public function test_sem_aplicar_so_simula_e_nao_grava_nada(): void
    {
        $processo = $this->processo([]);
        $antes = $processo->getAttributes();

        $this->artisan('processos:corrigir-codlog-inscricao')
            ->expectsOutputToContain('Afetados: 1')
            ->expectsOutputToContain('Simulação: nada foi gravado')
            ->assertSuccessful();

        $this->assertSame($antes, $processo->fresh()->getAttributes());
    }

    public function test_aplicar_corrige_codlog_via_poligono_e_zona_sem_tocar_a_decisao(): void
    {
        $processo = $this->processo([]);
        $decisao = ViabilityDecision::factory()->create(['viability_request_id' => $processo->id, 'outcome' => DecisionOutcome::Indeferida]);
        $decisaoAntes = $decisao->fresh()->getAttributes();

        $this->artisan('processos:corrigir-codlog-inscricao', ['--aplicar' => true])
            ->expectsOutputToContain('Corrigidos: 1')
            ->assertSuccessful();

        $processo->refresh();

        $this->assertSame('401', $processo->cod_log);
        $this->assertSame('VL', $processo->camadas_incidentes['via']);
        $this->assertSame($this->lote(-38.52), $processo->address_polygon_geojson);
        $this->assertSame(PoligonoFonte::Chave, $processo->address_polygon_source);
        $this->assertSame('ZCMe', $processo->zona_codigo);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->status);
        $this->assertSame($decisaoAntes, $decisao->fresh()->getAttributes());

        $auditoria = Activity::query()->where('event', 'codlog-inscricao-corrigido')->sole();

        $this->assertSame('579', $auditoria->properties['antes']['cod_log']);
        $this->assertSame('401', $auditoria->properties['depois']['cod_log']);
        $this->assertSame('ZPR 3', $auditoria->properties['antes']['zona_codigo']);
        $this->assertSame('ZCMe', $auditoria->properties['depois']['zona_codigo']);
    }

    public function test_lote_da_chave_errada_sem_lote_correto_sai_e_a_zona_volta_ao_ponto(): void
    {
        $processo = $this->processo(['address_number' => '10', 'property_polygon_geojson' => null]);

        $this->artisan('processos:corrigir-codlog-inscricao', ['--aplicar' => true])->assertSuccessful();

        $processo->refresh();

        $this->assertSame('401', $processo->cod_log);
        $this->assertNull($processo->address_polygon_geojson);
        $this->assertNull($processo->address_polygon_source);
        $this->assertEqualsWithDelta(-12.99, (float) $processo->property_point_lat, 0.000001);
        $this->assertSame('ZCMu 2', $processo->zona_codigo);
        $this->assertSame(ZonaFonte::Ponto, $processo->zona_fonte);
    }

    public function test_poligono_que_nao_veio_da_chave_continua_quando_a_chave_correta_nao_acha_lote(): void
    {
        $processo = $this->processo([
            'address_number' => '10',
            'address_polygon_source' => PoligonoFonte::Semap,
        ]);

        $this->artisan('processos:corrigir-codlog-inscricao', ['--aplicar' => true])->assertSuccessful();

        $processo->refresh();

        $this->assertSame('401', $processo->cod_log);
        $this->assertSame($this->lote(-38.60), $processo->address_polygon_geojson);
        $this->assertSame(PoligonoFonte::Semap, $processo->address_polygon_source);
        $this->assertSame('ZPR 3', $processo->zona_codigo);
    }

    public function test_processo_com_codlog_certo_nao_e_tocado(): void
    {
        $processo = $this->processo(['property_registration' => '1111111']);
        $antes = $processo->getAttributes();

        $this->artisan('processos:corrigir-codlog-inscricao', ['--aplicar' => true])
            ->expectsOutputToContain('Corrigidos: 0')
            ->assertSuccessful();

        $this->assertSame($antes, $processo->fresh()->getAttributes());
    }

    public function test_sem_lote_no_endereco_correto_usa_o_lote_que_contem_o_ponto_do_imovel(): void
    {
        $this->enderecamento->porPonto = new EnderecamentoHit(EnderecamentoHit::ENCONTRADO, $this->lote(-38.70), ['CODLOG' => 579, 'NUMERACAO_METRICA' => 365]);
        $processo = $this->processo(['address_number' => '10', 'property_polygon_geojson' => null]);

        $this->artisan('processos:corrigir-codlog-inscricao', ['--aplicar' => true])->assertSuccessful();

        $processo->refresh();

        $this->assertSame('401', $processo->cod_log);
        $this->assertSame($this->lote(-38.70), $processo->address_polygon_geojson);
        $this->assertSame(PoligonoFonte::Ponto, $processo->address_polygon_source);
        $this->assertSame('ZPR 3', $processo->zona_codigo);
        $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $processo->zona_fonte);
    }

    public function test_busca_pelo_ponto_fora_do_ar_mantem_o_lote_e_a_zona(): void
    {
        $this->enderecamento->porPonto = new EnderecamentoHit(EnderecamentoHit::INDISPONIVEL, motivo: 'timeout');
        $processo = $this->processo(['address_number' => '10', 'property_polygon_geojson' => null]);

        $this->artisan('processos:corrigir-codlog-inscricao', ['--aplicar' => true])->assertSuccessful();

        $processo->refresh();

        $this->assertSame('401', $processo->cod_log);
        $this->assertSame($this->lote(-38.60), $processo->address_polygon_geojson);
        $this->assertSame(PoligonoFonte::Chave, $processo->address_polygon_source);
        $this->assertSame('ZPR 3', $processo->zona_codigo);
    }

    public function test_reparar_lotes_devolve_o_lote_pelo_ponto_a_quem_perdeu_na_correcao(): void
    {
        $this->enderecamento->porPonto = new EnderecamentoHit(EnderecamentoHit::ENCONTRADO, $this->lote(-38.70));
        $processo = $this->processo([
            'address_number' => '10',
            'cod_log' => '401',
            'property_polygon_geojson' => null,
            'property_point_lat' => -12.99,
            'property_point_lng' => -38.52,
            'address_polygon_geojson' => null,
            'address_polygon_source' => null,
            'zona_codigo' => 'ZCMu 2',
            'zona_fonte' => ZonaFonte::Ponto,
        ]);
        Activity::query()->create([
            'log_name' => 'geo',
            'event' => 'codlog-inscricao-corrigido',
            'description' => 'teste',
            'subject_type' => $processo->getMorphClass(),
            'subject_id' => $processo->id,
            'properties' => ['antes' => ['poligono_fonte' => 'chave'], 'depois' => ['poligono_fonte' => null]],
        ]);
        $intacto = $this->processo(['cod_log' => '401', 'address_polygon_geojson' => null, 'address_polygon_source' => null]);
        $intactoAntes = $intacto->getAttributes();

        $this->artisan('processos:corrigir-codlog-inscricao', ['--reparar-lotes' => true])
            ->expectsOutputToContain('Lotes reparados: 1')
            ->assertSuccessful();

        $processo->refresh();

        $this->assertSame($this->lote(-38.70), $processo->address_polygon_geojson);
        $this->assertSame(PoligonoFonte::Ponto, $processo->address_polygon_source);
        $this->assertSame('ZPR 3', $processo->zona_codigo);
        $this->assertSame($intactoAntes, $intacto->fresh()->getAttributes());
    }
}
