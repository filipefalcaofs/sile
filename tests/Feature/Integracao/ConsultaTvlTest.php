<?php

namespace Tests\Feature\Integracao;

use App\Enums\DecisionOutcome;
use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Enums\TvlKind;
use App\Models\AnalysisRecord;
use App\Models\Company;
use App\Models\RuleVersion;
use App\Models\TllValor;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class ConsultaTvlTest extends TestCase
{
    use RefreshDatabase;

    private const USUARIO = 'sefaz_tvl';

    private const SENHA = 'senha-de-teste';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'viabiliza.integrations.sefaz_tvl.ativo' => true,
            'viabiliza.integrations.sefaz_tvl.usuario' => self::USUARIO,
            'viabiliza.integrations.sefaz_tvl.senha' => self::SENHA,
            'viabiliza.integrations.sefaz_tvl.validade_minutos' => 60,
        ]);
    }

    private function consultar(string $numero, ?string $token = null): TestResponse
    {
        $token ??= $this->autenticar()->json('token');

        return $this->getJson(
            "/api_integracao/tvl/{$numero}",
            $token === '' ? [] : ['Authorization' => "Bearer {$token}"],
        );
    }

    private function autenticar(string $usuario = self::USUARIO, string $senha = self::SENHA): TestResponse
    {
        return $this->postJson('/api_integracao/tvl/autenticar', ['usuario' => $usuario, 'senha' => $senha]);
    }

    private function decisaoDeferida(array $decisao = [], array $processo = []): ViabilityDecision
    {
        $company = Company::factory()->create([
            'cnpj' => '12345678000195',
            'legal_name' => 'IMPERIO DO SAMPA',
            'size' => 'MICRO EMPRESA',
        ]);

        $request = ViabilityRequest::factory()->protocoled()->create(array_merge([
            'company_id' => $company->id,
            'address_street' => 'Avenida Octávio Mangabeira',
            'address_number' => '00JDN4',
            'address_neighborhood' => 'PITUBA',
            'address_zip' => '41830-050',
            'property_registration' => '115355',
            'used_area_m2' => 511,
            'is_virtual_office' => false,
        ], $processo));

        $request->forceFill([
            'zona_codigo' => 'ZCMe-1/02',
            'cod_log' => '1140',
            'external_reference' => 'BAP2401445619',
        ])->save();

        return ViabilityDecision::factory()->create(array_merge([
            'viability_request_id' => $request->id,
            'tvl_product_number' => 'TVL-3000123',
            'tvl_kind' => TvlKind::Definitiva,
            'tvl_valid_until' => '2027-10-13',
            'decided_at' => '2026-10-13 14:07:13',
            'per_cnae' => [
                ['cnae' => '5611204', 'cnae_formatado' => '5611-2/04', 'is_primary' => true],
                ['cnae' => '5611205', 'cnae_formatado' => '5611-2/05', 'is_primary' => false],
            ],
        ], $decisao));
    }

    public function test_tvl_concedido_responde_no_contrato_do_sigs(): void
    {
        $decisao = $this->decisaoDeferida();
        AnalysisRecord::factory()->create([
            'viability_request_id' => $decisao->viability_request_id,
            'conditions' => ['Atender a Lei Federal 13.425/2017.', 'Dispor de vagas de estacionamento.'],
        ]);

        $response = $this->consultar('3000123');

        $response->assertOk()->assertJsonPath('resposta.protocolo', '3000123')
            ->assertJsonPath('resposta.status', '01')
            ->assertJsonPath('resposta.mensagem', 'TVL concedido.')
            ->assertJsonPath('resposta.cnpj_instituicao', '12345678000195')
            ->assertJsonPath('empresa.nome_requerente', 'IMPERIO DO SAMPA')
            ->assertJsonPath('empresa.inscricao_imobiliaria', '115355')
            ->assertJsonPath('empresa.area_utilizada', '511,00')
            ->assertJsonPath('empresa.porte_empresa', 'MICRO EMPRESA')
            ->assertJsonPath('empresa.cep', '41830050')
            ->assertJsonPath('empresa.uf', 'BA')
            ->assertJsonPath('empresa.cod_municipio', '38490')
            ->assertJsonPath('empresa.municipio', 'SALVADOR')
            ->assertJsonPath('empresa.bairro', 'PITUBA')
            ->assertJsonPath('empresa.cod_log', '1140')
            ->assertJsonPath('empresa.tipo_logradouro', 'Avenida')
            ->assertJsonPath('empresa.logradouro', 'Octávio Mangabeira')
            ->assertJsonPath('empresa.numero', '00JDN4')
            ->assertJsonPath('empresa.zona', 'ZCMe-1/02')
            ->assertJsonPath('empresa.tipoTVL', 'Definitivo')
            ->assertJsonPath('empresa.data_vencimento', '13/10/2027 00:00:00')
            ->assertJsonPath('empresa.data_viabilidade', '13/10/2026 14:07:13')
            ->assertJsonPath('empresa.data_fim_viabilidade', '20261013 140713')
            ->assertJsonPath('empresa.nome_impresso_TVL', 'IMPERIO DO SAMPA')
            ->assertJsonPath('empresa.tipo_viabilidade', 1)
            ->assertJsonPath('empresa.sede_escritorio_virtual', '0')
            ->assertJsonPath('empresa.tvlSimplifica', false)
            ->assertJsonPath('empresa.bap', 'BAP2401445619')
            ->assertJsonPath('empresa.grupo_cnae.0.codigo_cnae', '5611204')
            ->assertJsonPath('empresa.grupo_cnae.1.codigo_cnae', '5611205')
            ->assertJsonCount(2, 'empresa.grupo_cnae')
            ->assertJsonPath('empresa.taxas', []);

        $this->assertSame(
            "1-Atender a Lei Federal 13.425/2017.\r\n2-Dispor de vagas de estacionamento.",
            $response->json('empresa.condicionantes'),
        );
        $this->assertSame($response->json('empresa.condicionantes'), $response->json('empresa.observacao'));
    }

    public function test_tipo_provisorio_e_pre_operacional_saem_no_json(): void
    {
        $decisao = $this->decisaoDeferida(['tvl_kind' => TvlKind::Provisoria, 'tvl_valid_until' => '2027-01-31']);

        $this->consultar('3000123')->assertOk()
            ->assertJsonPath('empresa.tipoTVL', 'Provisório')
            ->assertJsonPath('empresa.tipo_viabilidade', 2)
            ->assertJsonPath('empresa.data_vencimento', '31/01/2027 00:00:00');

        $decisao->forceFill(['tvl_kind' => TvlKind::PreOperacional])->save();

        $this->consultar('3000123')->assertOk()->assertJsonPath('empresa.tipoTVL', 'Pré-operacional');
    }

    public function test_condicionantes_por_cnae_somam_as_da_ficha_sem_repetir(): void
    {
        $decisao = $this->decisaoDeferida(['per_cnae' => [
            ['cnae' => '5611204', 'condicionantes' => ['Dispor de vagas.', 'Manter alvará do bombeiro.']],
            ['cnae' => '5611205', 'condicionantes' => [['descricao' => 'Manter alvará do bombeiro.']]],
        ]]);
        AnalysisRecord::factory()->create([
            'viability_request_id' => $decisao->viability_request_id,
            'conditions' => ['Dispor de vagas.', 'Atender a Lei 13.425/2017.'],
        ]);

        $this->assertSame(
            "1-Dispor de vagas.\r\n2-Atender a Lei 13.425/2017.\r\n3-Manter alvará do bombeiro.",
            $this->consultar('3000123')->json('empresa.condicionantes'),
        );
    }

    public function test_sem_condicionantes_o_campo_sai_null(): void
    {
        $this->decisaoDeferida();

        $this->consultar('3000123')->assertOk()->assertJsonPath('empresa.condicionantes', null);
    }

    public function test_valor_da_taxa_sai_com_duas_casas_decimais(): void
    {
        RuleVersion::factory()->create([
            'domain' => RuleDomain::TllValores,
            'version' => '2026',
            'status' => RuleVersionStatus::Vigente,
            'valid_to' => null,
        ]);
        TllValor::factory()->create(['codigo_tll' => '1.01', 'exercicio' => 2026, 'valor' => 200, 'taxa_servico' => 6.1]);
        $this->decisaoDeferida(['per_cnae' => [['cnae' => '5611204', 'codigo_tll' => '1.01']]]);

        $conteudo = $this->consultar('3000123')->assertOk()->getContent();
        $this->assertStringContainsString('"valor":206.10,', $conteudo);
        $this->assertSame(
            ViabilityDecision::query()->first()->viabilityRequest->protocol_number,
            json_decode($conteudo, true)['empresa']['taxas'][0]['documento'],
        );

        TllValor::query()->update(['valor' => 300, 'taxa_servico' => 0]);
        $this->assertStringContainsString('"valor":300.00,', $this->consultar('3000123')->getContent());
    }

    public function test_tvl_aceita_o_numero_com_o_prefixo_gravado(): void
    {
        $this->decisaoDeferida();

        $this->consultar('TVL-3000123')->assertOk()->assertJsonPath('resposta.status', '01');
    }

    public function test_sede_de_escritorio_virtual_sai_como_um(): void
    {
        $this->decisaoDeferida(['is_virtual_office_hq' => true]);

        $this->consultar('3000123')->assertOk()->assertJsonPath('empresa.sede_escritorio_virtual', '1');
    }

    public function test_tvl_inexistente_responde_404_com_status_00(): void
    {
        $this->consultar('999999')->assertNotFound()
            ->assertJsonPath('resposta.protocolo', '999999')
            ->assertJsonPath('resposta.status', '00')
            ->assertJsonPath('resposta.mensagem', 'TVL não encontrado.')
            ->assertJsonMissingPath('empresa');
    }

    public function test_tvl_cassado_responde_status_02(): void
    {
        $this->decisaoDeferida(['outcome' => DecisionOutcome::Cassado]);

        $this->consultar('3000123')->assertOk()
            ->assertJsonPath('resposta.status', '02')
            ->assertJsonPath('resposta.mensagem', 'TVL Inválido, não deferido.');
    }

    public function test_login_correto_devolve_token_bearer_com_validade(): void
    {
        $this->autenticar()->assertOk()
            ->assertJsonPath('tipo', 'Bearer')
            ->assertJsonPath('expira_em_segundos', 3600)
            ->assertJsonStructure(['token']);
    }

    public function test_login_com_credencial_errada_responde_401(): void
    {
        $this->autenticar(self::USUARIO, 'errada')->assertUnauthorized()->assertJsonMissingPath('token');
        $this->autenticar('outro', self::SENHA)->assertUnauthorized();
    }

    public function test_consulta_sem_token_ou_com_token_invalido_responde_401(): void
    {
        $this->decisaoDeferida();

        $this->consultar('3000123', '')->assertUnauthorized()->assertJsonPath('resposta.status', '99');
        $this->consultar('3000123', 'lixo')->assertUnauthorized();
    }

    public function test_token_expirado_responde_401(): void
    {
        $this->decisaoDeferida();
        $token = $this->autenticar()->json('token');

        $this->travel(61)->minutes();

        $this->consultar('3000123', $token)->assertUnauthorized();
    }

    public function test_integracao_desligada_responde_503_no_login_e_na_consulta(): void
    {
        $this->decisaoDeferida();
        $token = $this->autenticar()->json('token');
        config(['viabiliza.integrations.sefaz_tvl.ativo' => false]);

        $this->autenticar()->assertStatus(503);
        $this->consultar('3000123', $token)->assertStatus(503)
            ->assertJsonPath('resposta.status', '99')
            ->assertJsonPath('resposta.mensagem', 'Erro no processamento.');
    }

    public function test_sem_credencial_configurada_a_integracao_nao_atende(): void
    {
        config(['viabiliza.integrations.sefaz_tvl.senha' => '']);

        $this->autenticar(self::USUARIO, '')->assertStatus(503);
    }

    public function test_consulta_fica_registrada_na_trilha_de_integracao(): void
    {
        $this->decisaoDeferida();

        $this->consultar('3000123')->assertOk();

        $this->assertDatabaseHas('integration_calls', ['direction' => 'entrada', 'http_status' => 200]);
    }

    public function test_swagger_unico_lista_regin_e_tvl(): void
    {
        $this->get('/api_integracao/docs')->assertOk()
            ->assertSee('swagger-ui', false)
            ->assertSee('/swagger-ui/swagger-ui-standalone-preset.js', false)
            ->assertSee('StandaloneLayout', false)
            ->assertSee('/api_integracao/docs/openapi.yaml', false)
            ->assertSee('/api_integracao/tvl/docs/openapi.yaml', false);

        $this->get('/api_integracao/tvl/docs')->assertRedirect('/api_integracao/docs');
    }

    public function test_especificacao_de_tvl_publica_os_dois_endpoints(): void
    {
        $spec = Yaml::parse((string) $this->get('/api_integracao/tvl/docs/openapi.yaml')->getContent());

        $this->assertArrayHasKey('/api_integracao/tvl/autenticar', $spec['paths']);
        $this->assertArrayHasKey('/api_integracao/tvl/{numero}', $spec['paths']);

        foreach (['tipoTVL', 'data_vencimento', 'condicionantes', 'grupo_cnae', 'taxas'] as $campo) {
            $this->assertArrayHasKey($campo, $spec['components']['schemas']['Empresa']['properties']);
        }
    }
}
