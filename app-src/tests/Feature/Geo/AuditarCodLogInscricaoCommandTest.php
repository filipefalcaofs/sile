<?php

namespace Tests\Feature\Geo;

use App\Enums\DecisionOutcome;
use App\Enums\PoligonoFonte;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Geo\ClassificacaoViariaHit;
use App\Services\Geo\EnderecamentoHit;
use App\Services\Geo\GeoServerClassificacaoViariaClient;
use App\Services\Geo\GeoServerEnderecamentoClient;
use App\Services\Geo\SemapLoteClient;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Geo\FakeZonaMaiorPorcaoResolver;
use Tests\TestCase;

class AuditarCodLogInscricaoCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csv = storage_path('framework/testing/auditar-codlog-'.uniqid().'.csv');

        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                if ($inscricao === '9999999') {
                    throw new PropertyRegistryUnavailableException($inscricao);
                }

                $localizacao = $inscricao === '3693872' ? '401' : '212';

                return new PropertyRegistryResult(
                    latitude: -12.99,
                    longitude: -38.52,
                    inscricao: $inscricao,
                    raw: [
                        'CodigoLogradouroTributario' => $inscricao === '3693872' ? '579' : '212',
                        'LogradouroTributario' => 'Avenida Euclydes da Cunha',
                        'ListaEndereco' => ['RetornoEndereco' => [[
                            'TipoEndereco' => 'Localização',
                            'CodigoLogradouro' => $localizacao,
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

        $this->app->instance(GeoServerEnderecamentoClient::class, new class($this->lote(-38.52)) extends GeoServerEnderecamentoClient
        {
            /**
             * @param  array<string, mixed>  $lote
             */
            public function __construct(private array $lote) {}

            public function consultar(string $codLog, string $numero, ?string $complemento = null): EnderecamentoHit
            {
                return $codLog === '401' && $numero === '365'
                    ? new EnderecamentoHit(EnderecamentoHit::ENCONTRADO, $this->lote)
                    : new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO);
            }
        });

        $this->app->instance(SemapLoteClient::class, new class extends SemapLoteClient
        {
            public function __construct() {}

            public function consultar(?string $inscricao): EnderecamentoHit
            {
                return new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO);
            }
        });

        $this->app->instance(ZonaMaiorPorcaoResolver::class, (new FakeZonaMaiorPorcaoResolver)
            ->para($this->lote(-38.52), FakeZonaMaiorPorcaoResolver::identificado('ZCMe')));
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);

        parent::tearDown();
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
            'property_registration' => '3693872',
            'address_number' => '365',
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            ...array_intersect_key($atributos, array_flip(['property_registration', 'address_number'])),
        ]);

        $processo->forceFill(array_diff_key($atributos, array_flip(['property_registration', 'address_number'])))->save();

        return $processo->refresh();
    }

    /**
     * @return list<array<string, string>>
     */
    private function linhasDoCsv(): array
    {
        $linhas = array_map(fn (string $linha): array => str_getcsv($linha, ';'), file($this->csv, FILE_IGNORE_NEW_LINES));
        $cabecalho = array_shift($linhas);

        return array_map(fn (array $linha): array => array_combine($cabecalho, $linha), $linhas);
    }

    public function test_lista_o_processo_com_codlog_tributario_e_o_que_mudaria_sem_gravar_nada(): void
    {
        $afetado = $this->processo([
            'cod_log' => '579',
            'camadas_incidentes' => ['via' => 'VC I'],
            'address_polygon_geojson' => $this->lote(-38.60),
            'address_polygon_source' => PoligonoFonte::Chave,
            'zona_codigo' => 'ZPR 3',
        ]);
        ViabilityDecision::factory()->create(['viability_request_id' => $afetado->id, 'outcome' => DecisionOutcome::Indeferida]);
        $this->processo(['property_registration' => '1111111', 'cod_log' => '212']);
        $antes = $afetado->fresh()->getAttributes();

        $this->artisan('processos:auditar-codlog-inscricao', ['--csv' => $this->csv])
            ->expectsOutputToContain('Afetados: 1')
            ->assertSuccessful();

        $linhas = $this->linhasDoCsv();

        $this->assertCount(1, $linhas);
        $this->assertSame((string) $afetado->id, $linhas[0]['processo']);
        $this->assertSame('indeferida', $linhas[0]['decisao_expresso']);
        $this->assertSame('579', $linhas[0]['codlog_atual']);
        $this->assertSame('401', $linhas[0]['codlog_correto']);
        $this->assertSame('Rua Catharina Paraguassú', $linhas[0]['logradouro_correto']);
        $this->assertSame('VC I', $linhas[0]['via_atual']);
        $this->assertSame('VL', $linhas[0]['via_correta']);
        $this->assertSame('chave', $linhas[0]['poligono_fonte_atual']);
        $this->assertSame('muda (chave)', $linhas[0]['poligono_correto']);
        $this->assertSame('ZPR 3', $linhas[0]['zona_atual']);
        $this->assertSame('ZCMe', $linhas[0]['zona_correta']);
        $this->assertSame($antes, $afetado->fresh()->getAttributes());
    }

    public function test_poligono_da_chave_errada_sem_lote_na_chave_correta_nem_no_semap_fica_sem_poligono(): void
    {
        $this->processo([
            'address_number' => '10',
            'cod_log' => '579',
            'address_polygon_geojson' => $this->lote(-38.60),
            'address_polygon_source' => PoligonoFonte::Chave,
            'zona_codigo' => 'ZPR 3',
        ]);

        $this->artisan('processos:auditar-codlog-inscricao', ['--csv' => $this->csv])->assertSuccessful();

        $linha = $this->linhasDoCsv()[0];

        $this->assertSame('perde (sem lote na chave nem no SEMAP)', $linha['poligono_correto']);
        $this->assertSame('ponto (rever)', $linha['zona_correta']);
    }

    public function test_poligono_do_semap_continua_quando_a_chave_correta_nao_acha_lote(): void
    {
        $this->processo([
            'address_number' => '10',
            'cod_log' => '579',
            'address_polygon_geojson' => $this->lote(-38.60),
            'address_polygon_source' => PoligonoFonte::Semap,
            'zona_codigo' => 'ZPR 3',
        ]);

        $this->artisan('processos:auditar-codlog-inscricao', ['--csv' => $this->csv])->assertSuccessful();

        $linha = $this->linhasDoCsv()[0];

        $this->assertSame('mantém (semap)', $linha['poligono_correto']);
        $this->assertSame('ZPR 3', $linha['zona_correta']);
    }

    public function test_cadastro_indisponivel_conta_a_parte_e_nao_entra_na_lista(): void
    {
        $this->processo(['property_registration' => '9999999', 'cod_log' => '579']);

        $this->artisan('processos:auditar-codlog-inscricao', ['--csv' => $this->csv])
            ->expectsOutputToContain('Cadastro indisponível: 1')
            ->assertSuccessful();

        $this->assertSame([], $this->linhasDoCsv());
    }
}
