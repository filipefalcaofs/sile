<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FichaCadastroImobiliarioInertiaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  array<string, mixed>  $processoAttrs
     */
    private function processoComFicha(array $processoAttrs = []): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-000200',
            'protocoled_at' => now(),
            'address_street' => 'Rua das Flores',
            'address_number' => '100',
            'address_neighborhood' => 'Centro',
        ], $processoAttrs));

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        return $processo;
    }

    public function test_com_inscricao_e_lookup_indisponivel_expoe_cadastro_dados_tvl_e_localizacao(): void
    {
        $processo = $this->processoComFicha([
            'property_registration' => '379387-7',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('cadastroImobiliario.status', 'indisponivel')
                ->where('cadastroImobiliario.inscricao', '379387-7')
                ->has('dadosTvl')
                ->where('localizacao.logradouro', 'Rua das Flores'));
    }

    public function test_ficha_expoe_tipo_do_regin_categoria_do_lote_e_declaracao(): void
    {
        $processo = $this->processoComFicha([
            'property_registration' => '344672',
            'origem_declarada' => [
                'protocolo' => 'REG-344672',
                'presta_servico_de_escritorio_virtual' => 'Sim',
                'cnae_principal' => '6202-3/00',
            ],
        ]);
        $processo->forceFill([
            'tipo_imovel' => 'Edificação Comercial',
            'semap_use_category' => 'COMERCIAL',
            'semap_construction_standard' => 'MÉDIO',
        ])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('dadosTvl.tipo_imovel', 'Edificação Comercial')
                ->where('localizacao.categoria_uso_lote', 'COMERCIAL')
                ->where('localizacao.padrao_construtivo_lote', 'MÉDIO')
                ->where('recepcao.protocolo', 'REG-344672')
                ->where('recepcao.presta_servico_de_escritorio_virtual', 'Sim')
                ->where('recepcao.cnae_principal', '6202-3/00'));
    }

    public function test_localizacao_expoe_cod_log_materializado(): void
    {
        $processo = $this->processoComFicha();
        $processo->forceFill(['cod_log' => '212'])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('localizacao.cod_log', '212'));
    }

    public function test_sem_inscricao_expoe_status_sem_inscricao(): void
    {
        $processo = $this->processoComFicha([
            'property_registration' => null,        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('cadastroImobiliario.status', 'sem_inscricao')
                ->has('dadosTvl'));
    }

    public function test_com_lookup_disponivel_expoe_campos_do_cadastro(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.97,
                    longitude: -38.50,
                    inscricao: $inscricao,
                    source: 'fake-cadastro',
                    raw: [],
                    cadastro: new PropertyCadastroCampos(
                        inscricao: $inscricao,
                        endereco: 'Rua Martiniano Bonfim',
                        numero_metrico: '224',
                        loteamento: null,
                        quadra: '0181',
                        lote: '0043',
                        conjunto_edificio: null,
                        bloco: null,
                        sub_unidade: 'GL - Galpão',
                        numero_sub_unidade: null,
                        bairro: 'CABULA',
                        cep: null,
                        area_construida_m2: '0,00',
                        tipo_imovel: 'Residencial Horizontal',
                        data_lancamento: '01/01/1986',
                        situacao_cadastral: 'Ativo',
                        contribuinte: 'ANTONIO COSTA NETO',
                        cpf_cnpj: '035.385.595-20',
                        numero_porta: '000224',
                        area_terreno_m2: '704,00',
                        valor_venal_iptu: 'R$ 769.120,00',
                        logradouro_tributario: '3704 - Rua Martiniano Bonfim',
                        situacao_fiscal: 'Contribuinte',
                        data_emissao_certidao: '01/12/2023 10:22:28',
                    ),
                );
            }
        });

        $processo = $this->processoComFicha([
            'property_registration' => '379387-7',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('cadastroImobiliario.status', 'disponivel')
                ->where('cadastroImobiliario.campos.quadra', '0181'));
    }

    public function test_bairro_da_localizacao_vem_da_inscricao_e_zona_nao_aparece_como_bairro(): void
    {
        $this->fakeCadastroComBairro('CABULA');

        $processo = $this->processoComFicha([
            'property_registration' => '0010010010',
            'address_neighborhood' => 'ZCMe-1/03',
        ]);
        $processo->forceFill([
            'zona_codigo' => 'ZCMe-1/03',
            'camadas_incidentes' => ['via' => 'VE', 'camadas' => []],
        ])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('localizacao.bairro', 'CABULA')
                ->where('localizacao.zona', 'ZCMe-1/03')
                ->where('localizacao.via', 'VE'));
    }

    public function test_sem_cadastro_nao_repete_a_zona_no_campo_bairro(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new PropertyRegistryUnavailableException($inscricao);
            }
        });

        $processo = $this->processoComFicha([
            'property_registration' => '0010010010',
            'address_neighborhood' => 'ZCMe-1/03',
        ]);
        $processo->forceFill(['zona_codigo' => 'ZCMe-1/03'])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('localizacao.bairro', null)
                ->where('localizacao.zona', 'ZCMe-1/03'));
    }

    public function test_auditoria_da_consulta_ao_cadastro_nao_inclui_dados_sensiveis(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.97,
                    longitude: -38.50,
                    inscricao: $inscricao,
                    source: 'fake-cadastro',
                    raw: [],
                    cadastro: new PropertyCadastroCampos(
                        inscricao: $inscricao,
                        endereco: 'Rua Martiniano Bonfim',
                        numero_metrico: '224',
                        loteamento: null,
                        quadra: '0181',
                        lote: '0043',
                        conjunto_edificio: null,
                        bloco: null,
                        sub_unidade: null,
                        numero_sub_unidade: null,
                        bairro: 'CABULA',
                        cep: null,
                        area_construida_m2: null,
                        tipo_imovel: null,
                        data_lancamento: null,
                        situacao_cadastral: null,
                        contribuinte: 'ANTONIO COSTA NETO',
                        cpf_cnpj: '035.385.595-20',
                        numero_porta: null,
                        area_terreno_m2: null,
                        valor_venal_iptu: 'R$ 769.120,00',
                        logradouro_tributario: null,
                        situacao_fiscal: null,
                        data_emissao_certidao: null,
                    ),
                );
            }
        });

        $processo = $this->processoComFicha([
            'property_registration' => '379387-7',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'ficha-cadastro-consulta',
            'subject_type' => $processo->getMorphClass(),
            'subject_id' => $processo->id,
        ]);

        $activity = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'ficha-cadastro-consulta')
            ->where('subject_id', $processo->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($processo->id, $activity->properties['viability_request_id']);
        $this->assertSame('379387-7', $activity->properties['inscricao']);
        $this->assertSame('disponivel', $activity->properties['status']);
        $this->assertArrayNotHasKey('cpf_cnpj', $activity->properties);
        $this->assertArrayNotHasKey('valor_venal_iptu', $activity->properties);
    }

    public function test_lookup_indisponivel_tambem_audita_consulta(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new PropertyRegistryUnavailableException($inscricao);
            }
        });

        $processo = $this->processoComFicha([
            'property_registration' => '379387-7',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'ficha-cadastro-consulta',
            'subject_id' => $processo->id,
        ]);
    }

    public function test_categoria_do_imovel_cai_para_a_utilizacao_da_inscricao_quando_o_regin_nao_trouxe(): void
    {
        $this->fakeCadastroComBairro('CABULA', 'NÃO RESIDENCIAL VERTICAL');

        $processo = $this->processoComFicha(['property_registration' => '350940']);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page
                ->where('localizacao.tipo_imovel', 'NÃO RESIDENCIAL VERTICAL'));
    }

    public function test_categoria_do_imovel_enviada_pelo_regin_prevalece_sobre_a_utilizacao(): void
    {
        $this->fakeCadastroComBairro('CABULA', 'NÃO RESIDENCIAL VERTICAL');

        $processo = $this->processoComFicha(['property_registration' => '350940']);
        $processo->forceFill(['tipo_imovel' => 'Sala'])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page->where('localizacao.tipo_imovel', 'Sala'));
    }

    public function test_categoria_do_imovel_fica_vazia_sem_regin_e_sem_inscricao(): void
    {
        $processo = $this->processoComFicha();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page->where('localizacao.tipo_imovel', null));
    }

    public function test_ficha_usa_o_tipo_do_cadastro_gravado_no_processo(): void
    {
        $processo = $this->processoComFicha();
        $processo->forceFill(['tipo_imovel_cadastro' => 'NÃO RESIDENCIAL VERTICAL'])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page
                ->where('localizacao.tipo_imovel', 'NÃO RESIDENCIAL VERTICAL')
                ->where('dadosTvl.tipo_imovel', 'NÃO RESIDENCIAL VERTICAL'));
    }

    private function fakeCadastroComBairro(string $bairro, ?string $tipoImovel = null): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class($bairro, $tipoImovel) implements PropertyRegistryLookup
        {
            public function __construct(private string $bairro, private ?string $tipoImovel) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.97,
                    longitude: -38.50,
                    inscricao: $inscricao,
                    source: 'fake-cadastro',
                    raw: [],
                    cadastro: new PropertyCadastroCampos(
                        inscricao: $inscricao,
                        endereco: 'Rua Martiniano Bonfim',
                        numero_metrico: '224',
                        loteamento: null,
                        quadra: '0181',
                        lote: '0043',
                        conjunto_edificio: null,
                        bloco: null,
                        sub_unidade: null,
                        numero_sub_unidade: null,
                        bairro: $this->bairro,
                        cep: null,
                        area_construida_m2: null,
                        tipo_imovel: $this->tipoImovel,
                        data_lancamento: null,
                        situacao_cadastral: null,
                        contribuinte: null,
                        cpf_cnpj: null,
                        numero_porta: null,
                        area_terreno_m2: null,
                        valor_venal_iptu: null,
                        logradouro_tributario: null,
                        situacao_fiscal: null,
                        data_emissao_certidao: null,
                    ),
                );
            }
        });
    }
}
