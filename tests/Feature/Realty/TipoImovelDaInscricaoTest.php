<?php

namespace Tests\Feature\Realty;

use App\Enums\GeoLayerType;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Geo\SpatialRepository;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * O tipo do imóvel do processo vem da declaração do REGIN; sem ela, vale o
 * uso do imóvel no Cadastro Imobiliário (campo Utilizacao da inscrição).
 */
class TipoImovelDaInscricaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private array $consultadas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PropertyTypeSeeder::class);

        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Via, 'version' => 'logradouro-2024']);

        $espacial = new FakeSpatialRepository;
        $espacial->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $espacial->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZCN-1']]);
        $espacial->setNearest(GeoLayerType::Via, [
            'id' => 3,
            'properties' => ['CODLOG' => '958', 'NOME_LOGRADOURO' => 'Rua Luiz Murat'],
            'distancia_m' => 25.9,
        ]);
        $this->app->instance(SpatialRepository::class, $espacial);
    }

    /**
     * @param  array<string, string|null>  $utilizacaoPorInscricao  null na inscrição = base indisponível
     */
    private function fakeCadastro(array $utilizacaoPorInscricao): void
    {
        $teste = $this;

        $this->app->instance(PropertyRegistryLookup::class, new class($utilizacaoPorInscricao, $teste) implements PropertyRegistryLookup
        {
            /**
             * @param  array<string, string|null>  $utilizacao
             */
            public function __construct(private array $utilizacao, private TipoImovelDaInscricaoTest $teste) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                $this->teste->registrarConsulta($inscricao);

                if (! array_key_exists($inscricao, $this->utilizacao)) {
                    throw new PropertyNotFoundException($inscricao);
                }

                $uso = $this->utilizacao[$inscricao];

                if ($uso === null) {
                    throw new PropertyRegistryUnavailableException($inscricao);
                }

                $campos = (array) PropertyCadastroCampos::vazios();
                $campos['tipo_imovel'] = $uso === '' ? null : $uso;

                return new PropertyRegistryResult(
                    latitude: -12.977769,
                    longitude: -38.516404,
                    inscricao: $inscricao,
                    raw: [
                        'CodigoLogradouroTributario' => '212',
                        'LogradouroTributario' => 'Avenida Lafayete Coutinho',
                        'Utilizacao' => $uso,
                    ],
                    cadastro: new PropertyCadastroCampos(...$campos),
                );
            }
        });
    }

    public function registrarConsulta(string $inscricao): void
    {
        $this->consultadas[] = $inscricao;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function processo(array $atributos = []): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'status' => ViabilityRequestStatus::EmAnalise,
            'property_polygon_geojson' => null,
            'property_registration' => '344672',
            'used_area_m2' => 120,
            'tipo_imovel' => null,
            'tipo_imovel_normalized' => null,
            ...$atributos,
        ]);

        $processo->cnaes()->attach(Cnae::factory()->create()->id, ['is_primary' => true]);

        return $processo;
    }

    public function test_protocolo_grava_o_tipo_da_inscricao_quando_o_processo_nao_tem(): void
    {
        $this->fakeCadastro(['344672' => 'GALPÃO']);
        $processo = $this->processo(['status' => ViabilityRequestStatus::Rascunho]);

        app(ProtocolarSolicitacaoService::class)->protocol($processo, User::factory()->create());

        $processo->refresh();
        $this->assertSame('GALPÃO', $processo->tipo_imovel);
        $this->assertSame('galpao', $processo->tipo_imovel_normalized);
    }

    public function test_protocolo_nao_troca_o_tipo_declarado_no_regin(): void
    {
        $this->fakeCadastro(['344672' => 'GALPÃO']);
        $processo = $this->processo([
            'status' => ViabilityRequestStatus::Rascunho,
            'tipo_imovel' => 'Sala comercial',
            'tipo_imovel_normalized' => 'sala_comercial',
        ]);

        app(ProtocolarSolicitacaoService::class)->protocol($processo, User::factory()->create());

        $processo->refresh();
        $this->assertSame('Sala comercial', $processo->tipo_imovel);
        $this->assertSame('sala_comercial', $processo->tipo_imovel_normalized);
    }

    public function test_protocolo_sem_utilizacao_na_inscricao_deixa_o_tipo_vazio(): void
    {
        $this->fakeCadastro(['344672' => '']);
        $processo = $this->processo(['status' => ViabilityRequestStatus::Rascunho]);

        app(ProtocolarSolicitacaoService::class)->protocol($processo, User::factory()->create());

        $processo->refresh();
        $this->assertNull($processo->tipo_imovel);
        $this->assertNull($processo->tipo_imovel_normalized);
    }

    public function test_comando_sem_aplicar_so_lista(): void
    {
        $this->fakeCadastro(['344672' => 'GALPÃO']);
        $processo = $this->processo();

        $this->artisan('processos:preencher-tipo-imovel')
            ->expectsOutputToContain('GALPÃO')
            ->expectsOutputToContain('--aplicar')
            ->assertSuccessful();

        $processo->refresh();
        $this->assertNull($processo->tipo_imovel);
    }

    public function test_comando_com_aplicar_grava_nos_processos_em_aberto_sem_tipo(): void
    {
        $this->fakeCadastro(['344672' => 'GALPÃO', '111' => 'GALPÃO', '222' => 'GALPÃO', '333' => 'GALPÃO']);
        $emAnalise = $this->processo();
        $emPendencia = $this->processo(['status' => ViabilityRequestStatus::EmPendencia, 'property_registration' => '111']);
        $deferido = $this->processo(['status' => ViabilityRequestStatus::Deferida, 'property_registration' => '222']);
        $comTipo = $this->processo(['property_registration' => '333', 'tipo_imovel' => 'Casa', 'tipo_imovel_normalized' => null]);
        $semInscricao = $this->processo(['property_registration' => null]);

        $this->artisan('processos:preencher-tipo-imovel', ['--aplicar' => true])->assertSuccessful();

        $this->assertSame('galpao', $emAnalise->refresh()->tipo_imovel_normalized);
        $this->assertSame('GALPÃO', $emPendencia->refresh()->tipo_imovel);
        $this->assertNull($deferido->refresh()->tipo_imovel);
        $this->assertSame('Casa', $comTipo->refresh()->tipo_imovel);
        $this->assertNull($semInscricao->refresh()->tipo_imovel);
        $this->assertEqualsCanonicalizing(['344672', '111'], $this->consultadas);
    }

    public function test_comando_com_processo_atinge_so_ele_mesmo_fora_do_aberto(): void
    {
        $this->fakeCadastro(['344672' => 'GALPÃO', '111' => 'GALPÃO']);
        $outro = $this->processo();
        $alvo = $this->processo(['status' => ViabilityRequestStatus::Deferida, 'property_registration' => '111']);

        $this->artisan('processos:preencher-tipo-imovel', ['--processo' => $alvo->id, '--aplicar' => true])
            ->assertSuccessful();

        $this->assertSame('GALPÃO', $alvo->refresh()->tipo_imovel);
        $this->assertNull($outro->refresh()->tipo_imovel);
    }

    public function test_comando_segue_quando_a_inscricao_falha_ou_nao_tem_tipo(): void
    {
        $this->fakeCadastro(['111' => null, '222' => '', '333' => 'GALPÃO']);
        $indisponivel = $this->processo(['property_registration' => '111']);
        $semTipo = $this->processo(['property_registration' => '222']);
        $naoEncontrada = $this->processo(['property_registration' => '999']);
        $certo = $this->processo(['property_registration' => '333']);

        $this->artisan('processos:preencher-tipo-imovel', ['--aplicar' => true])->assertSuccessful();

        $this->assertNull($indisponivel->refresh()->tipo_imovel);
        $this->assertNull($semTipo->refresh()->tipo_imovel);
        $this->assertNull($naoEncontrada->refresh()->tipo_imovel);
        $this->assertSame('GALPÃO', $certo->refresh()->tipo_imovel);
    }
}
