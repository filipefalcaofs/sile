<?php

namespace Tests\Feature\Gestao;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

    public function test_texto_que_nao_e_chave_do_legado_nao_consulta_a_api(): void
    {
        Http::fake();

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?busca=padaria')
            ->assertOk()
            ->assertJson(['consultado' => false]);

        Http::assertNothingSent();
    }

    public function test_cnpj_consulta_por_documento_e_devolve_os_processos(): void
    {
        Http::fake([self::BASE.'/processos*' => Http::response(['data' => [$this->processoLegado()]])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?busca='.urlencode('12.345.678/0001-95'))
            ->assertOk()
            ->assertJson([
                'consultado' => true,
                'erro' => null,
                'processos' => [[
                    'numero' => '0000012345-00067890/2024',
                    'situacao' => 'Em análise',
                    'finalizado' => false,
                    'tvls' => [2104040, 2104041],
                ]],
            ]);

        Http::assertSent(fn (Request $request): bool => $request['documento'] === '12345678000195');
    }

    public function test_campo_cnpj_do_filtro_avancado_tem_prioridade_sobre_a_busca(): void
    {
        Http::fake([self::BASE.'/processos*' => Http::response(['data' => []])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?cnpj=12345678000195&busca=2104040')
            ->assertOk()
            ->assertJson(['consultado' => true, 'processos' => []]);

        Http::assertSent(fn (Request $request): bool => $request['documento'] === '12345678000195');
    }

    public function test_sete_digitos_consulta_por_numero_da_tvl(): void
    {
        Http::fake([self::BASE.'/processos*' => Http::response(['data' => [$this->processoLegado()]])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?busca=2104040')
            ->assertOk()
            ->assertJsonCount(1, 'processos');

        Http::assertSent(fn (Request $request): bool => $request['numero_tvl'] === '2104040');
    }

    public function test_numero_do_processo_do_legado_consulta_por_numero_do_processo(): void
    {
        Http::fake([self::BASE.'/processos*' => Http::response(['data' => [$this->processoLegado()]])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?busca='.urlencode('0000012345-00067890/2024'))
            ->assertOk()
            ->assertJsonCount(1, 'processos');

        Http::assertSent(fn (Request $request): bool => $request['numero_processo'] === '0000012345-00067890/2024');
    }

    public function test_inscricao_consulta_por_inscricao(): void
    {
        Http::fake([self::BASE.'/processos*' => Http::response(['data' => []])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?inscricao=100200300')
            ->assertOk()
            ->assertJson(['consultado' => true, 'processos' => []]);

        Http::assertSent(fn (Request $request): bool => $request['inscricao'] === '100200300');
    }

    public function test_api_fora_do_ar_devolve_erro_sem_derrubar_a_tela(): void
    {
        Http::fake([self::BASE.'/processos*' => Http::response(['mensagem' => 'falhou'], 500)]);

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

    public function test_protocolo_regin_consulta_por_numero_do_processo_e_a_lista_traz_o_protocolo(): void
    {
        Http::fake([self::BASE.'/processos*' => Http::response(['data' => [
            $this->processoLegado() + ['protocolo' => 'BAP2601568762'],
        ]])]);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/legado?busca=bap2601568762')
            ->assertOk()
            ->assertJsonPath('processos.0.protocolo', 'BAP2601568762');

        Http::assertSent(fn (Request $request): bool => $request['numero_processo'] === 'bap2601568762');
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
