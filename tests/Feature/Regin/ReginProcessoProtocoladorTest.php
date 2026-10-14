<?php

namespace Tests\Feature\Regin;

use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\ReginRecebimento;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Services\Regin\ReginProtocoloSimulacaoService;
use App\Services\Tratamento\CategoriaEmpresa;
use App\Services\Tratamento\PlanilhaTratamentoImporter;
use App\Services\Tratamento\TratamentoRamoResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

class ReginProcessoProtocoladorTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ruc(array $overrides = []): array
    {
        $rowset = [
            'RUC_GENERAL' => [
                'RGE_CGC_CPF' => '13927801000149',
                'RGE_NOMB' => 'Empresa REGIN Teste',
            ],
            'RUC_ESTAB' => [
                'RES_AREA' => '834',
                'RES_DIRECCION' => 'Rua das Flores',
                'RES_NUME' => '100',
                'RES_IDENT_COMP' => 'Sala 2',
                'RES_URBANIZACION' => 'Centro',
                'RES_ZONA_POSTAL' => '40000000',
            ],
            'GROUPRUC_ACTV_ECON' => [
                'RUC_ACTV_ECON' => [
                    ['RAE_TAE_COD_ACTVD' => '4712100', 'RAE_CALIF_ACTV' => '1'],
                    ['RAE_TAE_COD_ACTVD' => '5611201', 'RAE_CALIF_ACTV' => '2'],
                ],
            ],
            'GROUPRUC_GEN_PROTOCOLO' => [
                'RUC_GEN_PROTOCOLO' => [
                    ['RGP_TGE_COD_TIP_TAB' => '5', 'RGP_VALOR' => '12345678'],
                ],
            ],
        ];

        return ['rowset' => array_replace_recursive($rowset, $overrides)];
    }

    private function recebimento(array $corpo): ReginRecebimento
    {
        return ReginRecebimento::factory()->create([
            'protocolo' => '43747',
            'corpo' => $corpo,
        ]);
    }

    /**
     * @param  callable(string): PropertyRegistryResult  $resolver
     */
    private function fakeInscricao(callable $resolver): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class($resolver) implements PropertyRegistryLookup
        {
            /** @param callable(string): PropertyRegistryResult $resolver */
            public function __construct(private $resolver) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return ($this->resolver)($inscricao);
            }
        });
    }

    private function resultadoComArea(string $inscricao, ?string $area): PropertyRegistryResult
    {
        $cadastro = new PropertyCadastroCampos(
            inscricao: $inscricao,
            endereco: null,
            numero_metrico: null,
            loteamento: null,
            quadra: null,
            lote: null,
            conjunto_edificio: null,
            bloco: null,
            sub_unidade: null,
            numero_sub_unidade: null,
            bairro: null,
            cep: null,
            area_construida_m2: $area,
            tipo_imovel: null,
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
        );

        return new PropertyRegistryResult(
            latitude: null,
            longitude: null,
            inscricao: $inscricao,
            source: 'teste',
            cadastro: $cadastro,
        );
    }

    public function test_ruc_completo_protocola_com_cnaes_endereco_area_e_inscricao(): void
    {
        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($this->ruc()));

        $this->assertNotNull($processo);
        $this->assertSame(ViabilityRequestOrigin::Regin, $processo->origin);
        $this->assertSame(ReginProtocoloSimulacaoService::CONTINGENCIA_RECEBE, $processo->contingency_reason);
        $this->assertSame('43747', $processo->external_reference);
        $this->assertSame(834.0, (float) $processo->used_area_m2);
        $this->assertSame('Rua das Flores', $processo->address_street);
        $this->assertSame('100', $processo->address_number);
        $this->assertSame('Sala 2', $processo->address_complement);
        $this->assertSame('Centro', $processo->address_neighborhood);
        $this->assertSame('40000000', $processo->address_zip);
        $this->assertSame('12345678', $processo->property_registration);
        $this->assertSame(2, $processo->cnaes()->count());
        $this->assertSame('4712100', $processo->primaryCnae()->first()?->code);
        $this->assertNotNull($processo->protocol_number);
    }

    public function test_natureza_juridica_do_ruc_vai_para_a_empresa(): void
    {
        $processo = app(ReginProcessoProtocolador::class)->protocolar(
            $this->recebimento($this->ruc(['RUC_COMP' => ['RCO_TNC_COD_NATUR' => '2062']])),
        );

        $this->assertSame('2062', $processo->company->legal_nature_code);
    }

    public function test_orgao_publico_do_ruc_chega_ao_motor_como_categoria_da_empresa(): void
    {
        $processo = app(ReginProcessoProtocolador::class)->protocolar(
            $this->recebimento($this->ruc(['RUC_COMP' => ['RCO_TNC_COD_NATUR' => '1015']])),
        );

        $this->assertSame('1015', $processo->company->legal_nature_code);
        $this->assertSame(
            'orgao_publico',
            CategoriaEmpresa::deNaturezaJuridica($processo->company->legal_nature_code, $processo->company->legal_nature),
        );
    }

    public function test_empresa_ja_cadastrada_sem_natureza_recebe_a_do_ruc(): void
    {
        Company::factory()->create(['cnpj' => '13927801000149', 'legal_nature_code' => null, 'legal_nature' => null]);

        $processo = app(ReginProcessoProtocolador::class)->protocolar(
            $this->recebimento($this->ruc(['RUC_COMP' => ['RCO_TNC_COD_NATUR' => '2062']])),
        );

        $this->assertSame('2062', $processo->company->fresh()->legal_nature_code);
        $this->assertSame(1, Company::query()->where('cnpj', '13927801000149')->count());
    }

    public function test_rowset_em_maiusculas_como_a_juceb_envia_tambem_protocola(): void
    {
        $corpo = ['ROWSET' => $this->ruc()['rowset']];

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($corpo));

        $this->assertNotNull($processo);
        $this->assertSame('43747', $processo->external_reference);
        $this->assertSame(834.0, (float) $processo->used_area_m2);
        $this->assertSame('Rua das Flores', $processo->address_street);
        $this->assertSame('12345678', $processo->property_registration);
        $this->assertSame(2, $processo->cnaes()->count());
    }

    public function test_protocolo_nao_decide_na_hora_e_deixa_a_decisao_para_o_job(): void
    {
        Queue::fake();

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($this->ruc()));

        $this->assertNotNull($processo);
        $this->assertSame(ViabilityRequestStatus::Protocolada, $processo->status);
        Queue::assertPushed(DecidirFluxoExpressoJob::class, 1);
    }

    public function test_groupperguntas_do_ruc_alimentam_as_respostas_da_planilha_por_cnae(): void
    {
        $this->seedTratamentoPlanilha();

        $corpo = $this->ruc([
            'GROUPPERGUNTAS' => [
                'PERGUNTAS' => [
                    [
                        'TIPO' => '2',
                        'CNPJ' => '05457349000170',
                        'COD_PERGUNTA' => '352',
                        'PER_PERGUNTA' => 'A atividade será desenvolvida no local?',
                        'PER_RESPOSTA' => 'Sim, a atividade será desenvolvida no local.',
                    ],
                ],
            ],
        ]);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($corpo));

        $this->assertNotNull($processo);
        $respostas = $processo->simulation_snapshot['respostas_tratamento_por_cnae']['4712100'] ?? [];
        $this->assertSame(true, $respostas[11] ?? null);
        $this->assertSame('352', $processo->simulation_snapshot['perguntas_regin'][0]['codigo'] ?? null);
    }

    public function test_groupperguntas_com_resposta_nao_simples_grava_falso(): void
    {
        $this->seedTratamentoPlanilha();

        $corpo = $this->ruc([
            'GROUPPERGUNTAS' => [
                'PERGUNTAS' => [
                    'TIPO' => '2',
                    'CNPJ' => '05457349000170',
                    'COD_PERGUNTA' => '352',
                    'PER_PERGUNTA' => 'A atividade será desenvolvida no local?',
                    'PER_RESPOSTA' => 'NÃO',
                ],
            ],
        ]);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($corpo));

        $this->assertNotNull($processo);
        $respostas = $processo->simulation_snapshot['respostas_tratamento_por_cnae']['4712100'] ?? [];
        $this->assertSame(false, $respostas[11] ?? null);
    }

    public function test_groupperguntas_fora_da_planilha_ficam_apenas_no_snapshot_bruto(): void
    {
        $this->seedTratamentoPlanilha();

        $corpo = $this->ruc([
            'GROUPPERGUNTAS' => [
                'PERGUNTAS' => [
                    [
                        'TIPO' => '4',
                        'CNPJ' => '22306987000100',
                        'COD_PERGUNTA' => '316',
                        'PER_PERGUNTA' => 'Pergunta de outra instituição, fora da planilha municipal?',
                        'PER_RESPOSTA' => 'SIM',
                    ],
                ],
            ],
        ]);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($corpo));

        $this->assertNotNull($processo);
        $this->assertSame([], $processo->simulation_snapshot['respostas_tratamento_por_cnae'] ?? []);
        $this->assertSame('316', $processo->simulation_snapshot['perguntas_regin'][0]['codigo'] ?? null);
    }

    public function test_pergunta_de_outra_instituicao_com_o_mesmo_enunciado_nao_vale_para_a_planilha(): void
    {
        $this->seedTratamentoPlanilha();

        $corpo = $this->ruc([
            'GROUPPERGUNTAS' => [
                'PERGUNTAS' => [
                    [
                        'TIPO' => '4',
                        'CNPJ' => '22306987000100',
                        'COD_PERGUNTA' => '999',
                        'PER_PERGUNTA' => 'A atividade será desenvolvida no local?',
                        'PER_RESPOSTA' => 'NÃO',
                    ],
                    [
                        'TIPO' => '2',
                        'CNPJ' => '05457349000170',
                        'COD_PERGUNTA' => '352',
                        'PER_PERGUNTA' => 'A atividade será desenvolvida no local?',
                        'PER_RESPOSTA' => 'Sim, a atividade será desenvolvida no local.',
                    ],
                ],
            ],
        ]);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($corpo));

        $respostas = $processo->simulation_snapshot['respostas_tratamento_por_cnae']['4712100'] ?? [];
        $this->assertSame(true, $respostas[11] ?? null);
        $this->assertCount(2, $processo->simulation_snapshot['perguntas_regin']);
    }

    public function test_seguir_na_pergunta_1_resolve_a_subdivisao_pela_opcao_marcada_sim(): void
    {
        $versao = $this->seedTratamentoPlanilha();
        app(PlanilhaTratamentoImporter::class)->complementar($versao, database_path('data/regras-20-08-26/planilha-regras.xlsx'));

        $pergunta1 = collect(app(TratamentoRamoResolver::class)->perguntasExibidas('9609299'))->firstWhere('numero', 1);
        $opcoes = $pergunta1['opcoes'] ?? [];
        $this->assertGreaterThanOrEqual(2, count($opcoes), 'A planilha deve listar as subdivisões do CNAE 9609-2/99.');

        $linhas = [[
            'TIPO' => '2',
            'CNPJ' => '05457349000170',
            'COD_PERGUNTA' => '705',
            'PER_PERGUNTA' => 'Identifique entre as opções a seguir os serviços ou as atividades que serão prestado(a)s/desenvolvido (a)s:',
            'PER_RESPOSTA' => 'Seguir',
        ]];

        foreach ($opcoes as $indice => $opcao) {
            $linhas[] = [
                'TIPO' => '2',
                'CNPJ' => '05457349000170',
                'COD_PERGUNTA' => (string) (553 + $indice),
                'PER_PERGUNTA' => $opcao['texto'],
                'PER_RESPOSTA' => $indice === 1 ? 'SIM' : 'NÃO',
            ];
        }

        $corpo = $this->ruc(['GROUPPERGUNTAS' => ['PERGUNTAS' => $linhas]]);
        $corpo['rowset']['GROUPRUC_ACTV_ECON']['RUC_ACTV_ECON'] = [['RAE_TAE_COD_ACTVD' => '9609299', 'RAE_CALIF_ACTV' => '1']];

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($corpo));

        $respostas = $processo->simulation_snapshot['respostas_tratamento_por_cnae']['9609299'] ?? [];
        $this->assertSame(2, $respostas[1] ?? null);
    }

    public function test_envelope_real_bap2601472834_com_groupperguntas_alimenta_o_motor(): void
    {
        $this->seedTratamentoPlanilha();

        $corpo = ['ROWSET' => [
            'RUC_GENERAL' => [
                'RGE_CGC_CPF' => '91237157900',
                'RGE_NOMB' => 'FABIAN TEIXEIRA FRANCO',
            ],
            'RUC_COMP' => ['RCO_TNC_COD_NATUR' => '2062'],
            'RUC_ESTAB' => [
                'RES_AREA' => '55',
                'RES_DIRECCION' => 'BEIRA MAR',
                'RES_NUME' => 'QSTM6',
                'RES_URBANIZACION' => 'STELLA MARIS',
                'RES_ZONA_POSTAL' => '41600677',
            ],
            'GROUPRUC_ACTV_ECON' => [
                'RUC_ACTV_ECON' => [
                    ['RAE_TAE_COD_ACTVD' => '1064300', 'RAE_CALIF_ACTV' => '1'],
                ],
            ],
            'GROUPRUC_GEN_PROTOCOLO' => [
                'RUC_GEN_PROTOCOLO' => [
                    ['RGP_TGE_COD_TIP_TAB' => '5', 'RGP_VALOR' => '9269886'],
                ],
            ],
            'GROUPPERGUNTAS' => [
                'PERGUNTAS' => [
                    ['TIPO' => '2', 'CNPJ' => '05457349000170', 'COD_PERGUNTA' => '517', 'PER_PERGUNTA' => 'A ocupação será em imóvel residencial (casa, apartamento), galpão ou container?', 'PER_RESPOSTA' => 'NÃO'],
                    ['TIPO' => '2', 'CNPJ' => '05457349000170', 'COD_PERGUNTA' => '352', 'PER_PERGUNTA' => 'A atividade será desenvolvida no local?', 'PER_RESPOSTA' => 'Sim, a atividade será desenvolvida no local.'],
                    ['TIPO' => '2', 'CNPJ' => '05457349000170', 'COD_PERGUNTA' => '515', 'PER_PERGUNTA' => 'Trata-se de escritório virtual?', 'PER_RESPOSTA' => 'NÃO'],
                    ['TIPO' => '2', 'CNPJ' => '05457349000170', 'COD_PERGUNTA' => '724', 'PER_PERGUNTA' => 'A atividade está estabelecida em área pública?', 'PER_RESPOSTA' => 'NÃO'],
                    ['TIPO' => '4', 'CNPJ' => '22306987000100', 'COD_PERGUNTA' => '316', 'PER_PERGUNTA' => 'A atividade empresarial será realizada em forma de escritório virtual, sem estabelecimento físico, ou ambulantes, carrinho de lanches, barracas itinerantes e similares?', 'PER_RESPOSTA' => 'SIM'],
                ],
            ],
        ]];

        $recebimento = ReginRecebimento::factory()->create([
            'protocolo' => 'BAP2601472834',
            'corpo' => $corpo,
        ]);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($recebimento);

        $this->assertNotNull($processo);
        $this->assertSame('BAP2601472834', $processo->external_reference);
        $this->assertSame('9269886', $processo->property_registration);
        $this->assertSame('1064300', $processo->primaryCnae()->first()?->code);
        $this->assertSame(55.0, (float) $processo->used_area_m2);
        $this->assertSame('2062', $processo->company->legal_nature_code);
        $this->assertSame('outra', CategoriaEmpresa::deNaturezaJuridica($processo->company->legal_nature_code));

        $respostas = $processo->simulation_snapshot['respostas_tratamento_por_cnae']['1064300'] ?? [];
        $this->assertSame(true, $respostas[2] ?? null);
        $this->assertCount(5, $processo->simulation_snapshot['perguntas_regin']);
    }

    public function test_perguntas_do_xml_do_envelope_alimentam_a_planilha_quando_o_json_vem_vazio(): void
    {
        $this->seedTratamentoPlanilha();

        $corpo = ['ROWSET' => $this->ruc()['rowset']];
        $corpo['ROWSET']['GROUPPER_PERGUNTAS'] = ['PER_PERGUNTAS' => []];

        $recebimento = ReginRecebimento::factory()->create([
            'protocolo' => 'BAP2601473733',
            'corpo' => $corpo,
            'envelope' => [
                'protocolo' => 'BAP2601473733',
                'json' => $corpo,
                'xml' => <<<'XML'
<?xml version="1.0" encoding="ISO-8859-1"?>
<Ruc><ROWSET><GROUPPERGUNTAS>
 <PERGUNTAS>
  <TIPO>2</TIPO>
  <CNPJ>05457349000170</CNPJ>
  <COD_PERGUNTA>352</COD_PERGUNTA>
  <PER_PERGUNTA><![CDATA[A atividade será desenvolvida no local?]]></PER_PERGUNTA>
  <PER_RESPOSTA>Sim, a atividade será desenvolvida no local.</PER_RESPOSTA>
 </PERGUNTAS>
</GROUPPERGUNTAS></ROWSET></Ruc>
XML,
            ],
        ]);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($recebimento);

        $this->assertNotNull($processo);
        $respostas = $processo->simulation_snapshot['respostas_tratamento_por_cnae']['4712100'] ?? [];
        $this->assertSame(true, $respostas[11] ?? null);
        $this->assertSame('352', $processo->simulation_snapshot['perguntas_regin'][0]['codigo'] ?? null);
    }

    public function test_snapshot_do_recebimento_reconstroi_as_respostas_pelo_xml_do_envelope(): void
    {
        $this->seedTratamentoPlanilha();

        $processo = ViabilityRequest::factory()->create(['external_reference' => 'BAP2601473733']);
        $cnae = Cnae::query()->firstOrCreate(
            ['code' => '4712100'],
            ['description' => 'Comercio', 'section_code' => 'G', 'section_description' => 'Comercio', 'division_code' => '47', 'division_description' => 'Comercio', 'group_code' => '471', 'group_description' => 'Varejo', 'class_code' => '47121', 'class_description' => 'Varejo', 'active' => true],
        );
        $processo->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $recebimento = new ReginRecebimento;
        $recebimento->protocolo = 'BAP2601473733';
        $recebimento->corpo = ['ROWSET' => $this->ruc()['rowset']];
        $recebimento->envelope = [
            'protocolo' => 'BAP2601473733',
            'xml' => <<<'XML'
<?xml version="1.0" encoding="ISO-8859-1"?>
<Ruc><ROWSET><GROUPPERGUNTAS>
 <PERGUNTAS>
  <TIPO>2</TIPO>
  <CNPJ>05457349000170</CNPJ>
  <COD_PERGUNTA>352</COD_PERGUNTA>
  <PER_PERGUNTA><![CDATA[A atividade será desenvolvida no local?]]></PER_PERGUNTA>
  <PER_RESPOSTA>Sim, a atividade será desenvolvida no local.</PER_RESPOSTA>
 </PERGUNTAS>
</GROUPPERGUNTAS></ROWSET></Ruc>
XML,
        ];

        $snapshot = app(ReginProcessoProtocolador::class)->snapshotDoRecebimento($recebimento);

        $this->assertNotNull($snapshot);
        $this->assertSame(true, $snapshot['respostas_tratamento_por_cnae']['4712100'][11] ?? null);
    }

    public function test_xml_declarado_iso8859_com_byte_latin1_entra_na_planilha(): void
    {
        $protocolador = app(ReginProcessoProtocolador::class);
        $metodo = new ReflectionMethod($protocolador, 'linhasDePerguntaDoXml');

        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>'
            .'<Ruc><ROWSET><GROUPPERGUNTAS><PERGUNTAS>'
            .'<TIPO>2</TIPO><CNPJ>05457349000170</CNPJ><COD_PERGUNTA>352</COD_PERGUNTA>'
            .'<PER_PERGUNTA><![CDATA[A atividade ser'.chr(0xE1).' desenvolvida no local?]]></PER_PERGUNTA>'
            .'<PER_RESPOSTA>Sim, a atividade ser'.chr(0xE1).' desenvolvida no local.</PER_RESPOSTA>'
            .'</PERGUNTAS></GROUPPERGUNTAS></ROWSET></Ruc>';

        $linhas = $metodo->invoke($protocolador, $xml);

        $this->assertCount(1, $linhas);
        $this->assertSame('352', $linhas[0]['COD_PERGUNTA']);
        $this->assertStringContainsString('será', $linhas[0]['PER_PERGUNTA']);
    }

    public function test_ruc_sem_cnae_principal_nao_protocola(): void
    {
        $corpo = $this->ruc();
        unset($corpo['rowset']['GROUPRUC_ACTV_ECON']);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($corpo));

        $this->assertNull($processo);
        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_area_zerada_no_regin_carrega_da_inscricao_imobiliaria(): void
    {
        $this->fakeInscricao(fn (string $inscricao): PropertyRegistryResult => $this->resultadoComArea($inscricao, '150,00'));

        $processo = app(ReginProcessoProtocolador::class)->protocolar(
            $this->recebimento($this->ruc(['RUC_ESTAB' => ['RES_AREA' => '0']])),
        );

        $this->assertNotNull($processo);
        $this->assertSame(150.0, (float) $processo->used_area_m2);
        $this->assertSame('12345678', $processo->property_registration);
    }

    public function test_area_zerada_sem_area_no_cadastro_nao_protocola(): void
    {
        $this->fakeInscricao(fn (string $inscricao): PropertyRegistryResult => $this->resultadoComArea($inscricao, null));

        $processo = app(ReginProcessoProtocolador::class)->protocolar(
            $this->recebimento($this->ruc(['RUC_ESTAB' => ['RES_AREA' => '0']])),
        );

        $this->assertNull($processo);
    }

    public function test_area_zerada_com_cadastro_indisponivel_nao_protocola(): void
    {
        $this->fakeInscricao(function (string $inscricao): PropertyRegistryResult {
            throw new PropertyRegistryUnavailableException($inscricao);
        });

        $processo = app(ReginProcessoProtocolador::class)->protocolar(
            $this->recebimento($this->ruc(['RUC_ESTAB' => ['RES_AREA' => '0']])),
        );

        $this->assertNull($processo);
    }

    public function test_ruc_sem_area_nao_protocola(): void
    {
        $corpo = $this->ruc(['RUC_ESTAB' => ['RES_AREA' => '']]);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($corpo));

        $this->assertNull($processo);
        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_empresa_e_reusada_pelo_cnpj(): void
    {
        $empresa = Company::factory()->create([
            'cnpj' => '13927801000149',
            'legal_name' => 'Empresa já cadastrada',
        ]);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($this->ruc()));

        $this->assertNotNull($processo);
        $this->assertSame($empresa->id, $processo->company_id);
        $this->assertSame(1, Company::query()->where('cnpj', '13927801000149')->count());
    }
}
