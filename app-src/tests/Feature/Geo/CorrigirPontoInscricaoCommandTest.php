<?php

namespace Tests\Feature\Geo;

use App\Enums\ConfirmacaoPonto;
use App\Enums\GeoLayerType;
use App\Enums\PoligonoFonte;
use App\Enums\PropertyLocationSource;
use App\Enums\ViabilityRequestStatus;
use App\Enums\ZonaFonte;
use App\Models\GeoLayer;
use App\Models\ViabilityRequest;
use App\Services\Geo\ClassificacaoViariaHit;
use App\Services\Geo\EnderecamentoHit;
use App\Services\Geo\GeoServerClassificacaoViariaClient;
use App\Services\Geo\GeoServerEnderecamentoClient;
use App\Services\Geo\ResultadoPonto;
use App\Services\Geo\SemapLoteClient;
use App\Services\Geo\SpatialRepository;
use App\Services\Geo\ValidadorPontoInscricao;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\Geo\FakeZonaMaiorPorcaoResolver;
use Tests\TestCase;

class CorrigirPontoInscricaoCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PONTO_SEFAZ = [-12.965543, -38.478635];

    private const PONTO_CERTO = [-12.9895, -38.5195];

    /** @var array<string, ResultadoPonto> */
    private array $conferencias = [];

    private object $enderecamento;

    protected function setUp(): void
    {
        parent::setUp();

        config(['viabiliza.features.geoserver_enderecamento' => true]);

        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Via, 'version' => 'logradouro-2024']);

        $espacial = new FakeSpatialRepository;
        $espacial->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Retiro']]);
        $espacial->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZCMu 2']]);
        $espacial->setNearest(GeoLayerType::Via, [
            'id' => 3,
            'properties' => ['CODLOG' => '761', 'NOME_LOGRADOURO' => 'Avenida Barros Reis'],
            'distancia_m' => 25.9,
        ]);
        $this->app->instance(SpatialRepository::class, $espacial);

        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.965543,
                    longitude: -38.478635,
                    inscricao: $inscricao,
                    raw: [
                        'CodigoLogradouroTributario' => '2075',
                        'LogradouroTributario' => 'Avenida Heitor Dias',
                        'ListaEndereco' => ['RetornoEndereco' => [[
                            'TipoEndereco' => 'Localização',
                            'CodigoLogradouro' => '2075',
                            'Logradouro' => 'Avenida Heitor Dias',
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
                return new ClassificacaoViariaHit(ClassificacaoViariaHit::IDENTIFICADO, $codLog === '2075' ? 'VA II' : 'VC I', null, null);
            }
        });

        $this->enderecamento = new class($this->lote(-38.52)) extends GeoServerEnderecamentoClient
        {
            public EnderecamentoHit $porPonto;

            /**
             * @param  array<string, mixed>  $lote
             */
            public function __construct(private array $lote)
            {
                $this->porPonto = new EnderecamentoHit(EnderecamentoHit::ENCONTRADO, $lote);
            }

            public function consultar(string $codLog, string $numero, ?string $complemento = null): EnderecamentoHit
            {
                return new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO);
            }

            public function consultarPorPonto(float $lat, float $lng): EnderecamentoHit
            {
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
            ->para($this->lote(-38.52), FakeZonaMaiorPorcaoResolver::identificado('ZCMe 1/02'))
            ->para($this->lote(-38.60), FakeZonaMaiorPorcaoResolver::identificado('ZCMe 1/03')));

        $conferencias = &$this->conferencias;
        $this->app->instance(ValidadorPontoInscricao::class, new class($conferencias) extends ValidadorPontoInscricao
        {
            /**
             * @param  array<string, ResultadoPonto>  $conferencias
             */
            public function __construct(private array &$conferencias) {}

            public function validar(float $lat, float $lng, ?string $codLog, ?string $numero, ?string $enderecoParaGeocode, string $inscricao): ResultadoPonto
            {
                return $this->conferencias[$inscricao]
                    ?? new ResultadoPonto(ConfirmacaoPonto::NaoVerificado, $lat, $lng, PropertyLocationSource::CadastroImobiliario);
            }
        });
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

    private function substituido(string $inscricao): void
    {
        $this->conferencias[$inscricao] = new ResultadoPonto(
            ConfirmacaoPonto::Substituido,
            self::PONTO_CERTO[0],
            self::PONTO_CERTO[1],
            PropertyLocationSource::EnderecamentoSedur,
            640.0,
            12.0,
        );
    }

    /**
     * Processo com o ponto errado da SEFAZ gravado e o lote tirado desse ponto.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function processo(array $atributos = []): ViabilityRequest
    {
        $inscricao = $atributos['property_registration'] ?? '1078739';

        $processo = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'property_registration' => $inscricao,
            'property_polygon_geojson' => null,
            'address_number' => '98',
        ]);

        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'property_point_lat' => self::PONTO_SEFAZ[0],
            'property_point_lng' => self::PONTO_SEFAZ[1],
            'property_location_source' => PropertyLocationSource::CadastroImobiliario,
            'cod_log' => '2075',
            'camadas_incidentes' => ['via' => 'VA II'],
            'address_polygon_geojson' => $this->lote(-38.60),
            'address_polygon_source' => PoligonoFonte::Ponto,
            'zona_codigo' => 'ZCMe 1/03',
            'zona_fonte' => ZonaFonte::EnderecamentoMaiorPorcao,
            ...array_diff_key($atributos, ['property_registration' => 1]),
        ])->save();

        return $processo->refresh();
    }

    public function test_sem_aplicar_so_lista_e_nao_grava_nada(): void
    {
        $this->substituido('1078739');
        $processo = $this->processo();
        $antes = $processo->getAttributes();

        $this->artisan('processos:corrigir-ponto-inscricao')
            ->expectsOutputToContain('Divergentes: 1')
            ->expectsOutputToContain('Simulação: nada foi gravado')
            ->assertSuccessful();

        $this->assertSame($antes, $processo->refresh()->getAttributes());
        $this->assertSame(0, Activity::query()->where('event', 'ponto-inscricao-corrigido')->count());
    }

    public function test_aplicar_grava_o_ponto_certo_refaz_o_lote_pelo_ponto_e_recalcula_a_zona(): void
    {
        $this->substituido('1078739');
        $processo = $this->processo();

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])
            ->expectsOutputToContain('Corrigidos: 1')
            ->assertSuccessful();

        $processo->refresh();

        $this->assertEqualsWithDelta(self::PONTO_CERTO[0], $processo->property_point_lat, 0.0000001);
        $this->assertEqualsWithDelta(self::PONTO_CERTO[1], $processo->property_point_lng, 0.0000001);
        $this->assertSame(PropertyLocationSource::EnderecamentoSedur, $processo->property_location_source);
        $this->assertSame($this->lote(-38.52), $processo->address_polygon_geojson);
        $this->assertSame(PoligonoFonte::Ponto, $processo->address_polygon_source);
        $this->assertSame('ZCMe 1/02', $processo->zona_codigo);
        $this->assertSame('2075', $processo->cod_log);
        $this->assertSame('VA II', $processo->camadas_incidentes['via']);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->status);
    }

    public function test_aplicar_audita_antes_e_depois(): void
    {
        $this->substituido('1078739');
        $processo = $this->processo();

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])->assertSuccessful();

        $auditoria = Activity::query()->where('event', 'ponto-inscricao-corrigido')->where('subject_id', $processo->id)->first();

        $this->assertNotNull($auditoria);
        $this->assertEquals(self::PONTO_SEFAZ[0], $auditoria->properties['antes']['ponto']['lat']);
        $this->assertEquals(self::PONTO_CERTO[0], $auditoria->properties['depois']['ponto']['lat']);
        $this->assertSame('ponto', $auditoria->properties['antes']['poligono_fonte']);
        $this->assertSame('ZCMe 1/03', $auditoria->properties['antes']['zona_codigo']);
        $this->assertSame('ZCMe 1/02', $auditoria->properties['depois']['zona_codigo']);
        $this->assertSame('enderecamento', $auditoria->properties['depois']['fonte_ponto']);
    }

    public function test_lote_que_veio_da_chave_e_mantido_e_so_o_ponto_muda(): void
    {
        $this->substituido('1078739');
        $processo = $this->processo(['address_polygon_source' => PoligonoFonte::Chave]);

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])->assertSuccessful();

        $processo->refresh();
        $this->assertEqualsWithDelta(self::PONTO_CERTO[0], $processo->property_point_lat, 0.0000001);
        $this->assertSame($this->lote(-38.60), $processo->address_polygon_geojson);
        $this->assertSame(PoligonoFonte::Chave, $processo->address_polygon_source);
    }

    public function test_sem_lote_no_ponto_certo_o_lote_errado_sai_e_a_zona_volta_a_ser_a_do_ponto(): void
    {
        $this->substituido('1078739');
        $this->enderecamento->porPonto = new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO);
        $processo = $this->processo();

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])->assertSuccessful();

        $processo->refresh();
        $this->assertNull($processo->address_polygon_geojson);
        $this->assertSame('ZCMu 2', $processo->zona_codigo);
        $this->assertSame(ZonaFonte::Ponto, $processo->zona_fonte);
        $this->assertSame('2075', $processo->cod_log);
    }

    public function test_busca_do_lote_fora_do_ar_nao_deixa_o_lote_errado(): void
    {
        $this->substituido('1078739');
        $this->enderecamento->porPonto = new EnderecamentoHit(EnderecamentoHit::INDISPONIVEL);
        $processo = $this->processo();

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])->assertSuccessful();

        $this->assertNull($processo->refresh()->address_polygon_geojson);
    }

    public function test_processo_ja_no_ponto_certo_nao_e_divergente(): void
    {
        $this->substituido('1078739');
        $this->processo([
            'property_point_lat' => self::PONTO_CERTO[0],
            'property_point_lng' => self::PONTO_CERTO[1],
        ]);

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])
            ->expectsOutputToContain('Divergentes: 0')
            ->assertSuccessful();

        $this->assertSame(0, Activity::query()->where('event', 'ponto-inscricao-corrigido')->count());
    }

    public function test_ponto_confirmado_e_nao_verificado_nao_sao_tocados(): void
    {
        $this->conferencias['1078739'] = new ResultadoPonto(ConfirmacaoPonto::Confirmado, self::PONTO_SEFAZ[0], self::PONTO_SEFAZ[1], PropertyLocationSource::CadastroImobiliario, 20.0, 20.0);
        $confirmado = $this->processo();
        $naoVerificado = $this->processo(['property_registration' => '2222222']);
        $antes = [$confirmado->getAttributes(), $naoVerificado->getAttributes()];

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])
            ->expectsOutputToContain('Divergentes: 0')
            ->assertSuccessful();

        $this->assertSame($antes, [$confirmado->refresh()->getAttributes(), $naoVerificado->refresh()->getAttributes()]);
    }

    public function test_sem_fonte_que_confirme_so_marca_o_aviso_e_nao_altera_o_resto(): void
    {
        $this->conferencias['1078739'] = new ResultadoPonto(ConfirmacaoPonto::NaoConfirmado, self::PONTO_SEFAZ[0], self::PONTO_SEFAZ[1], PropertyLocationSource::CadastroImobiliario, 640.0);
        $processo = $this->processo();
        $antes = $processo->getAttributes();

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])
            ->expectsOutputToContain('Sem fonte que confirme: 1')
            ->expectsOutputToContain('Marcados como não confirmados: 1')
            ->expectsOutputToContain('Divergentes: 0')
            ->assertSuccessful();

        $processo->refresh();
        $depois = $processo->getAttributes();
        unset($antes['localizacao_conferencia'], $depois['localizacao_conferencia']);
        $this->assertSame($antes, $depois);
        $this->assertSame('nao_confirmado', $processo->localizacao_conferencia['confirmacao']);
        $this->assertEquals(640.0, $processo->localizacao_conferencia['distancia_m']);
        $this->assertSame('Avenida Heitor Dias', $processo->localizacao_conferencia['logradouro']);
        $this->assertTrue($processo->localizacaoNaoConfirmada()['lote_do_ponto']);
    }

    public function test_sem_aplicar_nao_marca_o_aviso(): void
    {
        $this->conferencias['1078739'] = new ResultadoPonto(ConfirmacaoPonto::NaoConfirmado, self::PONTO_SEFAZ[0], self::PONTO_SEFAZ[1], PropertyLocationSource::CadastroImobiliario, 640.0);
        $processo = $this->processo();

        $this->artisan('processos:corrigir-ponto-inscricao')->assertSuccessful();

        $this->assertNull($processo->refresh()->localizacao_conferencia);
    }

    public function test_marca_ja_gravada_nao_e_regravada(): void
    {
        $this->conferencias['1078739'] = new ResultadoPonto(ConfirmacaoPonto::NaoConfirmado, self::PONTO_SEFAZ[0], self::PONTO_SEFAZ[1], PropertyLocationSource::CadastroImobiliario, 640.0);
        $processo = $this->processo(['localizacao_conferencia' => ['confirmacao' => 'nao_confirmado', 'distancia_m' => 640.0, 'logradouro' => 'Avenida Heitor Dias', 'conferido_em' => '2026-10-24T10:00:00-03:00']]);

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])
            ->expectsOutputToContain('Marcados como não confirmados: 0')
            ->assertSuccessful();

        $this->assertSame('2026-10-24T10:00:00-03:00', $processo->refresh()->localizacao_conferencia['conferido_em']);
    }

    public function test_correcao_grava_a_conferencia_substituida(): void
    {
        $this->substituido('1078739');
        $processo = $this->processo(['localizacao_conferencia' => ['confirmacao' => 'nao_confirmado', 'distancia_m' => 640.0]]);

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])->assertSuccessful();

        $processo->refresh();
        $this->assertSame('substituido', $processo->localizacao_conferencia['confirmacao']);
        $this->assertNull($processo->localizacaoNaoConfirmada());
    }

    public function test_ponto_que_passou_a_ser_confirmado_limpa_a_marca(): void
    {
        $this->conferencias['1078739'] = new ResultadoPonto(ConfirmacaoPonto::Confirmado, self::PONTO_SEFAZ[0], self::PONTO_SEFAZ[1], PropertyLocationSource::CadastroImobiliario, 20.0, 20.0);
        $processo = $this->processo(['localizacao_conferencia' => ['confirmacao' => 'nao_confirmado', 'distancia_m' => 640.0]]);

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])->assertSuccessful();

        $processo->refresh();
        $this->assertSame('confirmado', $processo->localizacao_conferencia['confirmacao']);
        $this->assertNull($processo->localizacaoNaoConfirmada());
    }

    public function test_poligono_desenhado_pelo_requerente_nao_e_tocado(): void
    {
        $this->substituido('1078739');
        $processo = $this->processo(['property_polygon_geojson' => $this->lote(-38.70)]);
        $antes = $processo->getAttributes();

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])
            ->expectsOutputToContain('Divergentes: 0')
            ->assertSuccessful();

        $this->assertSame($antes, $processo->refresh()->getAttributes());
    }

    public function test_so_processos_em_aberto_entram(): void
    {
        $this->substituido('1078739');
        $deferida = $this->processo(['status' => ViabilityRequestStatus::Deferida]);
        $indeferida = $this->processo(['status' => ViabilityRequestStatus::Indeferida]);
        $rascunho = $this->processo(['status' => ViabilityRequestStatus::Rascunho]);
        $emConvite = $this->processo(['status' => ViabilityRequestStatus::EmPendencia]);
        $aguardandoBap = $this->processo(['status' => ViabilityRequestStatus::AguardandoBap]);
        $protocolada = $this->processo(['status' => ViabilityRequestStatus::Protocolada]);

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])
            ->expectsOutputToContain('Corrigidos: 3')
            ->assertSuccessful();

        foreach ([$deferida, $indeferida, $rascunho] as $fechado) {
            $this->assertEqualsWithDelta(self::PONTO_SEFAZ[0], $fechado->refresh()->property_point_lat, 0.0000001);
        }

        foreach ([$emConvite, $aguardandoBap, $protocolada] as $aberto) {
            $this->assertEqualsWithDelta(self::PONTO_CERTO[0], $aberto->refresh()->property_point_lat, 0.0000001);
        }
    }

    public function test_filtra_por_processo(): void
    {
        $this->substituido('1078739');
        $alvo = $this->processo();
        $outro = $this->processo();

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true, '--processo' => $alvo->id])
            ->expectsOutputToContain('Corrigidos: 1')
            ->assertSuccessful();

        $this->assertEqualsWithDelta(self::PONTO_CERTO[0], $alvo->refresh()->property_point_lat, 0.0000001);
        $this->assertEqualsWithDelta(self::PONTO_SEFAZ[0], $outro->refresh()->property_point_lat, 0.0000001);
    }

    public function test_decisao_e_ajustes_manuais_nao_mudam(): void
    {
        $this->substituido('1078739');
        $processo = $this->processo();
        $processo->forceFill(['zona_manual' => 'ZPR 3', 'via_manual' => 'VC I'])->save();

        $this->artisan('processos:corrigir-ponto-inscricao', ['--aplicar' => true])->assertSuccessful();

        $processo->refresh();
        $this->assertSame('ZPR 3', $processo->zona_manual);
        $this->assertSame('VC I', $processo->via_manual);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->status);
    }
}
