<?php

namespace Tests\Feature\Gestao;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Consulta de processos do Simplifica na tela de processos da gestão: só a
 * API do legado, sem gravar nada na base. O servidor decide qual chave da API
 * usar pelo que foi digitado.
 */
class ProcessosLegadoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE = 'https://legado.test/v1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        config([
            'viabiliza.integrations.legado_api.base_url' => self::BASE,
            'viabiliza.integrations.legado_api.token' => 'lg_token_de_teste',
        ]);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function processoLegado(string $numero = '0000012345-00067890/2024'): array
    {
        return [
            'numero' => $numero,
            'criado_em' => '2024-03-10T09:00:00-03:00',
            'status' => ['nome' => 'Em análise', 'fim' => false],
            'numeros_tvl' => [2104040, 2104041],
        ];
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        Http::fake();

        $this->actingAs($semPermissao, 'gestao')
            ->getJson('/gestao/processos/legado?busca=12345678000195')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_sem_busca_nao_consulta_a_api(): void
    {
        Http::fake();

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado')
            ->assertOk()
            ->assertJson(['consultado' => false, 'processos' => [], 'erro' => null]);

        Http::assertNothingSent();
    }

    public function test_texto_curto_demais_nao_consulta_a_api(): void
    {
        Http::fake();

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?busca=ab&nome=xy')
            ->assertOk()
            ->assertJson(['consultado' => false, 'meta' => null]);

        Http::assertNothingSent();
    }

    public function test_filtros_que_dependem_de_listas_do_viabiliza_nao_viram_consulta(): void
    {
        Http::fake();

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?status=em_analise&grupo=concluido&setor=3&servico=5&analista=9&categoria=x')
            ->assertOk()
            ->assertJson(['consultado' => false]);

        Http::assertNothingSent();
    }

    public function test_pesquisa_combina_os_filtros_da_tela_na_api_do_legado(): void
    {
        Http::fake([self::BASE.'/processos/pesquisa*' => Http::response([
            'data' => [$this->processoLegado()],
            'meta' => ['total' => 31, 'pagina' => 2, 'por_pagina' => 15, 'paginas' => 3],
        ])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?'.http_build_query([
                'busca' => 'padaria',
                'protocolo' => '0000012345-00067890/2024',
                'bap' => 'BAP26',
                'produto_tvl' => '2104040',
                'cnpj' => '12.345.678/0001-95',
                'inscricao' => '100.200.300',
                'nome' => 'Empresa Teste',
                'cnae' => '4711',
                'complemento' => 'Sala 2',
                'logradouro' => 'Rua A',
                'bairro' => 'Centro',
                'cep' => '40.000-000',
                'analysis_status' => 'deferido',
                'fluxo' => 'expresso',
                'data_de' => '2026-03-01',
                'data_ate' => '2026-03-31',
                'pagina' => 2,
                'per_page' => 15,
            ]))
            ->assertOk()
            ->assertJson([
                'consultado' => true,
                'erro' => null,
                'meta' => ['total' => 31, 'pagina' => 2, 'por_pagina' => 15, 'paginas' => 3],
                'processos' => [[
                    'numero' => '0000012345-00067890/2024',
                    'situacao' => 'Em análise',
                    'finalizado' => false,
                    'tvls' => [2104040, 2104041],
                ]],
            ]);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/processos/pesquisa')
            && ! isset($request['busca'])
            && $request['numero_processo'] === '0000012345-00067890/2024'
            && $request['protocolo'] === 'BAP26'
            && $request['numero_tvl'] === '2104040'
            && $request['documento'] === '12345678000195'
            && $request['inscricao'] === '100200300'
            && $request['empresa'] === 'Empresa Teste'
            && $request['cnae'] === '4711'
            && $request['complemento'] === 'Sala 2'
            && $request['logradouro'] === 'Rua A'
            && $request['bairro'] === 'Centro'
            && $request['cep'] === '40000000'
            && $request['situacao_analise'] === 'deferido'
            && $request['fluxo'] === 'expresso'
            && $request['protocolado_de'] === '2026-03-01'
            && $request['protocolado_ate'] === '2026-03-31'
            && $request['pagina'] === '2'
            && $request['por_pagina'] === '15');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function buscasEstruturadas(): array
    {
        return [
            'protocolo REGIN completo' => ['BAP2680139693', 'protocolo', 'BAP2680139693'],
            'protocolo REGIN parcial' => ['bap2601', 'protocolo', 'bap2601'],
            'número do processo' => ['5921000030-00062208/2026', 'numero_processo', '5921000030-00062208/2026'],
            'CNPJ com máscara' => ['53.666.248/0001-52', 'documento', '53666248000152'],
            'CPF' => ['123.456.789-09', 'documento', '12345678909'],
            'número da TVL' => ['2104040', 'numero_tvl', '2104040'],
            'inscrição' => ['100200300', 'inscricao', '100200300'],
            'nome da empresa' => ['Padaria Central', 'empresa', 'Padaria Central'],
        ];
    }

    #[DataProvider('buscasEstruturadas')]
    public function test_caixa_de_busca_vira_o_filtro_especifico_e_nao_usa_a_busca_generica_da_api(string $busca, string $parametro, string $valor): void
    {
        Http::fake([self::BASE.'/processos/pesquisa*' => Http::response(['data' => [], 'meta' => ['total' => 0, 'pagina' => 1, 'por_pagina' => 15, 'paginas' => 0]])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?busca='.urlencode($busca))
            ->assertOk()
            ->assertJson(['consultado' => true]);

        Http::assertSent(fn (Request $request): bool => $request[$parametro] === $valor && ! isset($request['busca']));
    }

    public function test_campo_proprio_vale_mais_que_a_caixa_de_busca(): void
    {
        Http::fake([self::BASE.'/processos/pesquisa*' => Http::response(['data' => [], 'meta' => ['total' => 0, 'pagina' => 1, 'por_pagina' => 15, 'paginas' => 0]])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?cnpj=53666248000152&busca='.urlencode('12.345.678/0001-95'))
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => $request['documento'] === '53666248000152');
    }

    public function test_protocolo_digitado_no_campo_do_processo_vai_como_protocolo(): void
    {
        Http::fake([self::BASE.'/processos/pesquisa*' => Http::response(['data' => [], 'meta' => ['total' => 0, 'pagina' => 1, 'por_pagina' => 15, 'paginas' => 0]])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?protocolo=BAP2680139693')
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => $request['protocolo'] === 'BAP2680139693' && ! isset($request['numero_processo']));
    }

    public function test_pesquisa_tem_tempo_proprio_nao_repete_e_informa_o_motivo_da_demora(): void
    {
        config(['viabiliza.integrations.legado_api.timeout_pesquisa' => 30]);
        $tentativas = 0;
        Http::fake(function () use (&$tentativas) {
            $tentativas++;

            throw new ConnectionException('cURL error 28: Operation timed out after 30001 milliseconds');
        });

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?cnpj=53666248000152')
            ->assertOk()
            ->assertJson([
                'consultado' => true,
                'erro' => 'Não foi possível consultar o Simplifica agora.',
                'motivo' => 'A API do legado demorou mais de 30 s para responder.',
            ]);

        $this->assertSame(1, $tentativas);
    }

    public function test_motivo_da_api_vai_para_a_resposta(): void
    {
        Http::fake([self::BASE.'/processos/pesquisa*' => Http::response(['mensagem' => 'Token ausente.'], 401)]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?cnpj=53666248000152')
            ->assertOk()
            ->assertJson(['erro' => 'Não foi possível consultar o Simplifica agora.', 'motivo' => 'Token ausente.']);
    }

    public function test_valores_fora_do_vocabulario_do_legado_nao_sao_enviados(): void
    {
        Http::fake([self::BASE.'/processos/pesquisa*' => Http::response(['data' => [], 'meta' => ['total' => 0, 'pagina' => 1, 'por_pagina' => 15, 'paginas' => 0]])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?cnpj=123&inscricao=abc&fluxo=em_analise&analysis_status=para_distribuir&data_de=01/03/2026&busca=padaria')
            ->assertOk()
            ->assertJson(['consultado' => true, 'processos' => []]);

        Http::assertSent(fn (Request $request): bool => $request['empresa'] === 'padaria'
            && ! isset($request['documento'])
            && ! isset($request['inscricao'])
            && ! isset($request['fluxo'])
            && ! isset($request['situacao_analise'])
            && ! isset($request['protocolado_de']));
    }

    public function test_api_fora_do_ar_devolve_erro_sem_derrubar_a_tela(): void
    {
        Http::fake([self::BASE.'/processos/pesquisa*' => Http::response(['mensagem' => 'falhou'], 500)]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?busca=12345678000195')
            ->assertOk()
            ->assertJson([
                'consultado' => true,
                'processos' => [],
                'erro' => 'Não foi possível consultar o Simplifica agora.',
            ]);
    }

    public function test_api_nao_configurada_devolve_erro(): void
    {
        config(['viabiliza.integrations.legado_api.base_url' => '']);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?busca=12345678000195')
            ->assertOk()
            ->assertJson(['consultado' => true, 'erro' => 'Não foi possível consultar o Simplifica agora.']);
    }

    public function test_lista_traz_o_protocolo_regin(): void
    {
        Http::fake([self::BASE.'/processos/pesquisa*' => Http::response(['data' => [
            $this->processoLegado() + ['protocolo' => 'BAP2601568762'],
        ], 'meta' => ['total' => 1, 'pagina' => 1, 'por_pagina' => 15, 'paginas' => 1]])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?bap=BAP2601568762')
            ->assertOk()
            ->assertJsonPath('processos.0.protocolo', 'BAP2601568762');

        Http::assertSent(fn (Request $request): bool => $request['protocolo'] === 'BAP2601568762');
    }

    public function test_consulta_na_fonte_simplifica_nao_busca_na_base_do_viabiliza(): void
    {
        Http::fake();

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?fonte=simplifica&busca=padaria')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filtros.fonte', 'simplifica')
                ->has('processos.data', 0));

        Http::assertNothingSent();
    }

    public function test_consulta_sem_fonte_e_a_do_viabiliza(): void
    {
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('filtros.fonte', 'viabiliza'));
    }

    /**
     * @return array<string, mixed>
     */
    private function detalheLegado(): array
    {
        return [
            'numero' => '5921000030-00055276/2026',
            'protocolo' => 'BAP2601568762',
            'criado_em' => '2026-03-10T09:00:00-03:00',
            'status' => ['id' => 4, 'nome' => 'Deferido', 'fim' => true],
            'solicitante' => ['nome' => 'MARIA DA SILVA', 'documento' => '12345678909'],
            'localizacao' => ['latitude' => -12.97, 'longitude' => -38.5],
            'parecer' => [
                'deferido' => true,
                'texto' => 'Deferido conforme a ficha.',
                'atualizado_em' => '2026-03-12T10:00:00-03:00',
                'tipo' => 'provisoria',
                'valido_ate' => '2027-03-12T00:00:00-03:00',
            ],
            'fichas' => [[
                'atualizado_em' => '2026-03-12T10:00:00-03:00',
                'finalizada' => true,
                'deferido' => true,
                'parecer' => 'Ok',
                'numero_tvl' => 2104040,
                'condicionante' => 'Horário comercial',
                'itens' => [['campo' => 'cep', 'deferido' => true, 'em_analise' => false]],
                'atividades' => [[
                    'codigo' => '4711-3/02',
                    'descricao' => 'Supermercados',
                    'deferido' => true,
                    'em_analise' => false,
                    'incluida' => true,
                    'excluida' => false,
                    'enquadrado' => true,
                    'enquadramento' => 'Comércio local',
                ]],
            ]],
            'tvls' => [[
                'numero' => 2104040,
                'tipo' => 'provisoria',
                'deferida' => true,
                'ativa' => true,
                'emissao' => '2026-03-12T00:00:00-03:00',
                'validade' => '2027-03-12T00:00:00-03:00',
                'situacao' => 'vigente',
                'dias_para_vencer' => 140,
                'titular' => ['documento' => '12345678000195', 'tipo' => 'cnpj', 'origem' => 'tvl'],
                'razao_social' => 'EMPRESA TESTE LTDA',
                'inscricao_imobiliaria' => '100200300',
                'endereco' => ['logradouro' => 'Rua A', 'numero' => '10', 'bairro' => 'Centro', 'cep' => '40000000'],
                'escritorio_virtual' => ['tipo' => 'sede', 'tvl_sede' => 2104040],
            ]],
            'historico' => [[
                'em' => '2026-03-10T09:00:00-03:00',
                'titulo' => 'Protocolado',
                'mensagem' => 'Processo protocolado.',
                'status' => 'Em análise',
                'por' => 'Ana Analista',
            ]],
        ];
    }

    public function test_detalhe_traz_a_ficha_completa_do_processo(): void
    {
        Http::fake([self::BASE.'/processos/*' => Http::response(['data' => $this->detalheLegado()])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado/detalhe?numero='.urlencode('5921000030-00055276/2026'))
            ->assertOk()
            ->assertJsonPath('erro', null)
            ->assertJsonPath('processo.numero', '5921000030-00055276/2026')
            ->assertJsonPath('processo.protocolo', 'BAP2601568762')
            ->assertJsonPath('processo.situacao', 'Deferido')
            ->assertJsonPath('processo.finalizado', true)
            ->assertJsonPath('processo.solicitante.nome', 'MARIA DA SILVA')
            ->assertJsonPath('processo.solicitante.documento', '12345678909')
            ->assertJsonPath('processo.localizacao.latitude', -12.97)
            ->assertJsonPath('processo.parecer.texto', 'Deferido conforme a ficha.')
            ->assertJsonPath('processo.parecer.tipo', 'provisoria')
            ->assertJsonPath('processo.fichas.0.condicionante', 'Horário comercial')
            ->assertJsonPath('processo.fichas.0.itens.0.campo', 'cep')
            ->assertJsonPath('processo.fichas.0.atividades.0.enquadramento', 'Comércio local')
            ->assertJsonPath('processo.tvls.0.numero', 2104040)
            ->assertJsonPath('processo.tvls.0.razao_social', 'EMPRESA TESTE LTDA')
            ->assertJsonPath('processo.tvls.0.escritorio_virtual.tipo', 'sede')
            ->assertJsonPath('processo.historico.0.por', 'Ana Analista')
            ->assertJsonPath('processo.historico.0.mensagem', 'Processo protocolado.');
    }

    public function test_detalhe_por_protocolo_regin(): void
    {
        Http::fake([self::BASE.'/processos/*' => Http::response(['data' => $this->detalheLegado()])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado/detalhe?numero=BAP2601568762')
            ->assertOk()
            ->assertJsonPath('processo.numero', '5921000030-00055276/2026');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/processos/BAP2601568762'));
    }

    public function test_detalhe_de_processo_inexistente_devolve_nulo(): void
    {
        Http::fake([self::BASE.'/processos/*' => Http::response(['mensagem' => 'Processo não encontrado.'], 404)]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado/detalhe?numero=5921000030-00055276/2026')
            ->assertOk()
            ->assertJson(['processo' => null, 'erro' => null]);
    }

    public function test_detalhe_com_numero_fora_do_formato_nao_consulta_a_api(): void
    {
        Http::fake();

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado/detalhe?numero=../tvls/1')
            ->assertOk()
            ->assertJson(['processo' => null, 'erro' => null]);

        Http::assertNothingSent();
    }

    public function test_detalhe_com_api_fora_do_ar_devolve_erro(): void
    {
        Http::fake([self::BASE.'/processos/*' => Http::response([], 500)]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado/detalhe?numero=BAP2601568762')
            ->assertOk()
            ->assertJson(['processo' => null, 'erro' => 'Não foi possível consultar o Simplifica agora.']);
    }

    public function test_detalhe_sem_permissao_recebe_403(): void
    {
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        Http::fake();

        $this->actingAs($semPermissao, 'gestao')
            ->getJson('/gestao/processos/legado/detalhe?numero=BAP2601568762')
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
