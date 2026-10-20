<?php

namespace Tests\Feature\Renovacao;

use App\Enums\CompanyLinkRole;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\Procuration;
use App\Models\User;
use App\Services\Cnpj\CnpjData;
use App\Services\Cnpj\CnpjLookup;
use App\Services\Cnpj\CnpjLookupException;
use App\Services\Legado\LegadoIndisponivelException;
use App\Services\Legado\LegadoViabilidadeClient;
use App\Services\Legado\TvlLegada;
use App\Services\Legado\TvlLegadaDetalhe;
use App\Services\Renovacao\ResolvedorEnderecoImovel;
use App\Services\Sedur\SedurContatoClient;
use App\Services\Sedur\SedurPessoaJuridica;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class RenovacaoLegadaPortalTest extends TestCase
{
    use FakeCadastroImobiliario;
    use LazilyRefreshDatabase;

    private const CNPJ = '11222333000181';

    private const OUTRO_CNPJ = '99888777000166';

    private const RAZAO = 'PADARIA ESTRELA LTDA';

    private const CODIGO_CNAE = '4721102';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Cnae::factory()->create(['code' => self::CODIGO_CNAE]);
        $this->app->instance(CnpjLookup::class, new class implements CnpjLookup
        {
            public function lookup(string $cnpj): CnpjData
            {
                throw new CnpjLookupException('fora do ar');
            }
        });
    }

    public function test_index_traz_provisorias_legadas_das_empresas_do_cidadao(): void
    {
        $cidadao = $this->portalUser();
        $this->empresaDe($cidadao);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('provisoriasPorCnpj')->with(self::CNPJ)
                ->andReturn([$this->resumo(1111111), $this->resumo(2222222)]);
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111));
            $client->shouldReceive('tvlPorNumero')->with(2222222)->andReturn($this->detalhe(2222222, renovadaDepois: true));
        });

        $this->actingAs($cidadao)
            ->get('/portal/renovacao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/renovacao/index')
                ->has('tvls', 0)
                ->has('legado', 2)
                ->where('legado.0.numero', 1111111)
                ->where('legado.0.elegivel', true)
                ->where('legado.1.elegivel', false)
                ->where('legado.1.motivo', 'Esta viabilidade já foi renovada.')
                ->where('legado_indisponivel', false)
            );
    }

    public function test_index_traz_as_empresas_vinculadas_para_a_consulta_por_numero(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $this->empresaDe($this->portalUser(), self::OUTRO_CNPJ);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('provisoriasPorCnpj')->with(self::CNPJ)->andReturn([]);
        });

        $this->actingAs($cidadao)
            ->get('/portal/renovacao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('empresas', 1)
                ->where('empresas.0.id', $empresa->id)
                ->where('empresas.0.nome', $empresa->legal_name)
                ->where('empresas.0.cnpj', $empresa->formatted_cnpj)
            );
    }

    public function test_index_com_api_legada_fora_do_ar_mantem_a_lista_do_viabiliza(): void
    {
        config([
            'viabiliza.integrations.legado_api.base_url' => 'https://legado.test/api',
            'viabiliza.integrations.legado_api.token' => 'token-teste',
        ]);
        Http::fake(['legado.test/*' => Http::response([], 503)]);
        $cidadao = $this->portalUser();
        $this->empresaDe($cidadao);

        $this->actingAs($cidadao)
            ->get('/portal/renovacao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/renovacao/index')
                ->has('tvls', 0)
                ->has('legado', 0)
                ->where('legado_indisponivel', true)
            );
    }

    public function test_procurador_ve_provisorias_das_empresas_do_outorgante(): void
    {
        $outorgante = $this->portalUser();
        $procurador = $this->portalUser();
        $procuracao = Procuration::factory()->create([
            'grantor_user_id' => $outorgante->id,
            'attorney_user_id' => $procurador->id,
        ]);
        $this->empresaDe($outorgante);
        $this->empresaDe($procurador, self::OUTRO_CNPJ);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('provisoriasPorCnpj')->with(self::CNPJ)->andReturn([$this->resumo(1111111)]);
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111));
            $client->shouldNotReceive('provisoriasPorCnpj')->with(self::OUTRO_CNPJ);
        });

        $this->actingAs($procurador)
            ->withSession(['acting_procuration_id' => $procuracao->id])
            ->get('/portal/renovacao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('legado', 1)
                ->where('legado.0.numero', 1111111)
            );
    }

    public function test_consultar_com_empresa_nao_vinculada_e_proibido(): void
    {
        $cidadao = $this->portalUser();
        $alheia = Company::factory()->create(['cnpj' => self::CNPJ]);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldNotReceive('tvlPorNumero');
        });

        $this->actingAs($cidadao)
            ->postJson('/portal/renovacao/legado/consultar', [
                'empresa_id' => $alheia->id,
                'numero_viabilidade' => '1234567',
            ])
            ->assertForbidden();
    }

    public function test_consultar_sem_numero_ou_com_formato_invalido_devolve_422(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);

        $this->actingAs($cidadao)
            ->postJson('/portal/renovacao/legado/consultar', ['empresa_id' => $empresa->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numero_viabilidade']);

        $resposta = $this->actingAs($cidadao)
            ->postJson('/portal/renovacao/legado/consultar', [
                'empresa_id' => $empresa->id,
                'numero_viabilidade' => '12345',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numero_viabilidade']);

        $this->assertStringContainsString('7 dígitos', $resposta->json('errors.numero_viabilidade.0'));
    }

    public function test_consultar_valido_devolve_os_itens(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)->andReturn($this->detalhe(1234567));
        });

        $this->actingAs($cidadao)
            ->postJson('/portal/renovacao/legado/consultar', [
                'empresa_id' => $empresa->id,
                'numero_viabilidade' => ' 1234567 ',
            ])
            ->assertOk()
            ->assertJsonPath('indisponivel', false)
            ->assertJsonPath('itens.0.numero', 1234567)
            ->assertJsonPath('itens.0.elegivel', true)
            ->assertJsonPath('itens.0.empresa_id', $empresa->id);
    }

    public function test_show_de_tvl_inelegivel_volta_ao_indice_com_o_motivo(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalhe(1234567, renovadaDepois: true));
        });

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$empresa->id}/1234567")
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', 'Esta viabilidade já foi renovada.');
    }

    public function test_show_de_tvl_de_outro_titular_nao_vaza_a_existencia(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalhe(1234567, titular: self::OUTRO_CNPJ));
            $client->shouldReceive('tvlPorNumero')->with(7654321)->andReturn(null);
        });

        $generica = 'Esta viabilidade não pôde ser carregada para esta empresa.';

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$empresa->id}/1234567")
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', $generica);
        $mensagemOutroTitular = session('error');

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$empresa->id}/7654321")
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', $generica);
        $mensagemInexistente = session('error');

        $this->assertSame($mensagemInexistente, $mensagemOutroTitular);
    }

    public function test_show_com_conferencia_cadastral_indisponivel_pede_nova_tentativa(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalhe(1234567, titular: null));
        });

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$empresa->id}/1234567")
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', 'Não foi possível conferir o CNPJ agora. Tente novamente em instantes.');
    }

    public function test_show_com_api_indisponivel_redireciona_com_aviso(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->andThrow(new LegadoIndisponivelException('fora'));
        });

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$empresa->id}/1234567")
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', 'O serviço de consulta de viabilidades anteriores está indisponível. Tente novamente em instantes.');
    }

    public function test_show_de_empresa_nao_vinculada_e_proibido(): void
    {
        $cidadao = $this->portalUser();
        $alheia = Company::factory()->create(['cnpj' => self::CNPJ]);

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$alheia->id}/1234567")
            ->assertForbidden();
    }

    public function test_show_de_tvl_elegivel_renderiza_o_wizard_com_origem_legada(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)->andReturn($this->detalhe(1234567));
        });

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$empresa->id}/1234567")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/renovacao/wizard')
                ->where('origem.legado', true)
                ->where('origem.numero', 1234567)
                ->where('origem.empresa_id', $empresa->id)
                ->where('origem.endereco', 'RUA DA ENGOMADEIRA, 508, 41200000')
                ->where('origem.validade', '2026-12-31')
                ->where('origem.atividades.0', self::CODIGO_CNAE.' Padaria')
                ->has('declaracoes')
                ->where('contato.email', $empresa->email)
            );
    }

    public function test_assistente_mostra_o_endereco_do_imovel(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $chamadas = $this->cadastroImobiliario('Rua do Cadastro, 77', '77', '40000111');
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalheComInscricao(1234567, '0123456789'));
        });

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$empresa->id}/1234567")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('origem.endereco', 'Rua do Cadastro, 77, 40000111')
            );

        $this->assertSame(['0123456789'], $chamadas->getArrayCopy());
    }

    public function test_assistente_com_cadastro_fora_do_ar_volta_ao_indice_com_a_mensagem(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $this->cadastroImobiliario(falha: 'indisponivel');
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalheComInscricao(1234567, '0123456789'));
        });

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$empresa->id}/1234567")
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', ResolvedorEnderecoImovel::M_INDISPONIVEL);
    }

    public function test_assistente_com_inscricao_nao_encontrada_volta_ao_indice_com_a_mensagem(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $this->cadastroImobiliario(falha: 'nao_encontrada');
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalheComInscricao(1234567, '0123456789'));
        });

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$empresa->id}/1234567")
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', ResolvedorEnderecoImovel::M_NAO_ENCONTRADA);
    }

    public function test_assistente_sem_cnpj_gravado_consulta_o_cadastro_uma_vez_para_titularidade_e_endereco(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $this->cnpjDaEmpresaConfere();
        $chamadas = $this->cadastroImobiliario('Rua do Cadastro', '77', '40000111');
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalheComInscricao(1234567, '0123456789', null));
        });

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$empresa->id}/1234567")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('origem.endereco', 'Rua do Cadastro, 77, 40000111'));

        $this->assertCount(1, $chamadas);
    }

    public function test_assistente_de_quem_nao_e_titular_nao_consulta_o_cadastro_alem_da_titularidade(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $this->cnpjDaEmpresaConfere('OUTRA RAZAO SOCIAL LTDA');
        $chamadas = $this->cadastroImobiliario('Rua do Cadastro', '77', '40000111');
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalheComInscricao(1234567, '0123456789', null));
        });

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$empresa->id}/1234567")
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', 'Esta viabilidade não pôde ser carregada para esta empresa.');

        $this->assertCount(0, $chamadas);
    }

    public function test_consultar_cnpj_devolve_legado_da_empresa_vinculada(): void
    {
        $cidadao = $this->portalUser();
        $this->empresaDe($cidadao);
        $this->cadastroSedur(self::CNPJ);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('provisoriasPorCnpj')->with(self::CNPJ)->andReturn([$this->resumo(1111111)]);
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111));
        });

        $this->actingAs($cidadao)
            ->postJson('/portal/renovacao/consultar-cnpj', ['cnpj' => self::CNPJ])
            ->assertOk()
            ->assertJsonCount(1, 'legado')
            ->assertJsonPath('legado.0.numero', 1111111);
    }

    public function test_consultar_cnpj_nao_vinculado_traz_legado_vazio(): void
    {
        $cidadao = $this->portalUser();
        $this->cadastroSedur('11444777000161');
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldNotReceive('provisoriasPorCnpj');
            $client->shouldNotReceive('tvlPorNumero');
        });

        $this->actingAs($cidadao)
            ->postJson('/portal/renovacao/consultar-cnpj', ['cnpj' => '11444777000161'])
            ->assertOk()
            ->assertJson(['legado' => []]);
    }

    public function test_procurador_consulta_com_empresa_do_outorgante(): void
    {
        [$procurador, $outorgante, $procuracao] = $this->procuracao();
        $empresaDoOutorgante = $this->empresaDe($outorgante);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)->andReturn($this->detalhe(1234567));
        });

        $this->actingAs($procurador)
            ->withSession(['acting_procuration_id' => $procuracao->id])
            ->postJson('/portal/renovacao/legado/consultar', [
                'empresa_id' => $empresaDoOutorgante->id,
                'numero_viabilidade' => '1234567',
            ])
            ->assertOk()
            ->assertJsonPath('itens.0.numero', 1234567);
    }

    public function test_procurador_atuando_nao_usa_a_propria_empresa_em_consultar_nem_show(): void
    {
        [$procurador, $outorgante, $procuracao] = $this->procuracao();
        $this->empresaDe($outorgante);
        $propria = $this->empresaDe($procurador, self::OUTRO_CNPJ);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldNotReceive('tvlPorNumero');
        });

        $this->actingAs($procurador)
            ->withSession(['acting_procuration_id' => $procuracao->id])
            ->postJson('/portal/renovacao/legado/consultar', [
                'empresa_id' => $propria->id,
                'numero_viabilidade' => '1234567',
            ])
            ->assertForbidden();

        $this->actingAs($procurador)
            ->withSession(['acting_procuration_id' => $procuracao->id])
            ->get("/portal/renovacao/legado/{$propria->id}/1234567")
            ->assertForbidden();
    }

    public function test_vinculo_encerrado_nao_autoriza_consultar_nem_show(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $empresa->links()->update(['ended_at' => now()->subDay()]);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldNotReceive('tvlPorNumero');
        });

        $this->actingAs($cidadao)
            ->postJson('/portal/renovacao/legado/consultar', [
                'empresa_id' => $empresa->id,
                'numero_viabilidade' => '1234567',
            ])
            ->assertForbidden();

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$empresa->id}/1234567")
            ->assertForbidden();
    }

    public function test_show_nao_enumera_ids_de_empresa(): void
    {
        $cidadao = $this->portalUser();
        $alheia = Company::factory()->create(['cnpj' => self::CNPJ]);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldNotReceive('tvlPorNumero');
        });

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/legado/{$alheia->id}/1234567")
            ->assertForbidden();

        $this->actingAs($cidadao)
            ->get('/portal/renovacao/legado/999999/1234567')
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User, 2: Procuration}
     */
    private function procuracao(): array
    {
        $outorgante = $this->portalUser();
        $procurador = $this->portalUser();
        $procuracao = Procuration::factory()->create([
            'grantor_user_id' => $outorgante->id,
            'attorney_user_id' => $procurador->id,
        ]);

        return [$procurador, $outorgante, $procuracao];
    }

    private function cadastroSedur(string $cnpj): void
    {
        $this->mock(SedurContatoClient::class, function (MockInterface $contato) use ($cnpj): void {
            $contato->shouldReceive('consultarCnpj')->with($cnpj)->andReturn(new SedurPessoaJuridica(
                cnpj: $cnpj,
                razaoSocial: self::RAZAO,
                nomeFantasia: null,
                telefone: null,
                endereco: null,
            ));
        });
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    private function empresaDe(User $user, string $cnpj = self::CNPJ): Company
    {
        $empresa = Company::factory()->create(['cnpj' => $cnpj]);
        $empresa->links()->create([
            'user_id' => $user->id,
            'role' => CompanyLinkRole::Responsavel,
            'started_at' => now(),
        ]);

        return $empresa;
    }

    private function mockClient(callable $expectativas): void
    {
        $this->mock(LegadoViabilidadeClient::class, $expectativas);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $numero, ?string $titular, bool $renovadaDepois): array
    {
        return [
            'numero' => $numero,
            'tipo' => 'provisoria',
            'deferida' => true,
            'ativa' => true,
            'validade' => '2026-12-31',
            'situacao' => 'vigente',
            'dias_para_vencer' => 73,
            'renovada_depois' => $renovadaDepois,
            'titular' => ['documento' => $titular],
            'razao_social' => self::RAZAO,
            'endereco' => ['logradouro' => 'RUA DA ENGOMADEIRA', 'numero' => '508', 'bairro' => '', 'cep' => '41200000'],
            'atividades' => [['codigo' => self::CODIGO_CNAE, 'descricao' => 'Padaria']],
        ];
    }

    private function detalhe(int $numero, ?string $titular = self::CNPJ, bool $renovadaDepois = false): TvlLegadaDetalhe
    {
        return TvlLegadaDetalhe::fromArray($this->payload($numero, $titular, $renovadaDepois));
    }

    private function detalheComInscricao(int $numero, string $inscricao, ?string $titular = self::CNPJ): TvlLegadaDetalhe
    {
        return TvlLegadaDetalhe::fromArray($this->payload($numero, $titular, false) + ['inscricao_imobiliaria' => $inscricao]);
    }

    private function cnpjDaEmpresaConfere(string $razao = self::RAZAO): void
    {
        $this->app->instance(CnpjLookup::class, new class($razao) implements CnpjLookup
        {
            public function __construct(private string $razao) {}

            public function lookup(string $cnpj): CnpjData
            {
                return new CnpjData($cnpj, $this->razao, null, null, null, null, null, null, [], null, null, null, null, null, null, '40000111', null, null, null);
            }
        });
    }

    private function resumo(int $numero): TvlLegada
    {
        return TvlLegada::fromArray($this->payload($numero, self::CNPJ, false));
    }
}
