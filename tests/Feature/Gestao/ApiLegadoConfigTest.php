<?php

namespace Tests\Feature\Gestao;

use App\Models\Parameter;
use App\Models\User;
use App\Services\Legado\LegadoIndisponivelException;
use App\Services\Legado\LegadoViabilidadeClient;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Aba da API do Simplifica (legado) na tela de APIs: URLs de homologação e
 * produção, token ou usuário e senha. O cliente da API lê esses parâmetros.
 */
class ApiLegadoConfigTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const HML = 'https://legado-hml.test/v1';

    private const PRD = 'https://legado-prd.test/v1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
        config([
            'viabiliza.integrations.legado_api.base_url' => '',
            'viabiliza.integrations.legado_api.token' => '',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  array<string, string>  $campos
     */
    private function salvar(array $campos): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-apis/legado', ['campos' => $campos + [
                'em_producao' => '0',
                'url_homologacao' => self::HML,
                'url_producao' => self::PRD,
                'token' => '',
                'usuario' => '',
                'senha_acesso' => '',
                'host' => '',
                'comparacao' => '0',
            ]])
            ->assertRedirect(route('gestao.config-apis.edit', ['aba' => 'legado']));
    }

    private function valor(string $chave): mixed
    {
        return Parameter::query()->where('key', $chave)->firstOrFail()->value;
    }

    public function test_aba_do_simplifica_aparece_na_tela_sem_credencial_em_claro(): void
    {
        Parameter::query()->where('key', 'integrations.legado_api.token')->first()->update(['value' => 'lg_segredo_do_token']);

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/config-apis?aba=legado')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aba', 'legado')
                ->where('abas.9.id', 'legado')
                ->where('abas.9.nome', 'Simplifica (legado)')
                ->where('abas.9.teste.url', '/gestao/config-apis/legado/testar')
                ->where('abas.9.campos.3.id', 'token')
                ->where('abas.9.campos.3.valor', null)
                ->where('abas.9.campos.3.tem_valor', true))
            ->assertDontSee('lg_segredo_do_token');
    }

    public function test_salva_urls_e_espelha_a_url_do_ambiente_ativo(): void
    {
        $this->salvar(['em_producao' => '0']);

        $this->assertSame(self::HML, $this->valor('integrations.legado_api.base_url'));

        $this->salvar(['em_producao' => '1']);

        $this->assertSame(self::PRD, $this->valor('integrations.legado_api.base_url'));
    }

    public function test_token_e_senha_ficam_criptografados_e_em_branco_nao_apaga(): void
    {
        $this->salvar(['token' => 'lg_token_secreto', 'usuario' => 'maria', 'senha_acesso' => 'senha-secreta']);

        foreach (['token', 'senha'] as $chave) {
            $parametro = Parameter::query()->where('key', 'integrations.legado_api.'.$chave)->first();
            $this->assertTrue($parametro->sensitive);
            $this->assertNotSame($parametro->value, $parametro->getRawOriginal('value'));
        }

        $this->assertSame('maria', $this->valor('integrations.legado_api.usuario'));

        $this->salvar(['token' => '', 'senha_acesso' => '']);

        $this->assertSame('lg_token_secreto', $this->valor('integrations.legado_api.token'));
        $this->assertSame('senha-secreta', $this->valor('integrations.legado_api.senha'));
    }

    public function test_url_invalida_e_rejeitada(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->from('/gestao/config-apis?aba=legado')
            ->put('/gestao/config-apis/legado', ['campos' => [
                'em_producao' => '0',
                'url_homologacao' => 'nao-e-url',
                'url_producao' => self::PRD,
            ]])
            ->assertSessionHasErrors('campos.url_homologacao');
    }

    public function test_cliente_usa_a_url_e_o_token_configurados_na_tela(): void
    {
        $this->salvar(['token' => 'lg_token_da_tela']);
        Http::fake([self::HML.'/provisorias*' => Http::response(['data' => []]), self::HML.'/processos*' => Http::response(['data' => []])]);

        app(LegadoViabilidadeClient::class)->processosPorChave('numero_tvl', '2104040');

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), self::HML.'/processos')
            && $request->hasHeader('Authorization', 'Bearer lg_token_da_tela'));
    }

    public function test_sem_token_o_cliente_entra_com_usuario_e_senha_e_guarda_a_sessao(): void
    {
        Cache::flush();
        $this->salvar(['usuario' => 'maria', 'senha_acesso' => 'senha-secreta']);
        Http::fake([
            self::HML.'/auth' => Http::response(['token' => 'lgs_sessao_da_maria', 'expira_em' => now()->addHours(8)->toIso8601String()]),
            self::HML.'/processos*' => Http::response(['data' => []]),
        ]);

        $cliente = app(LegadoViabilidadeClient::class);
        $cliente->processosPorChave('numero_tvl', '2104040');
        $cliente->processosPorChave('numero_tvl', '2104041');

        Http::assertSent(fn (Request $request): bool => $request->url() === self::HML.'/auth'
            && $request['identificador'] === 'maria' && $request['senha'] === 'senha-secreta');
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), self::HML.'/processos')
            && $request->hasHeader('Authorization', 'Bearer lgs_sessao_da_maria'));
        Http::assertSentCount(3);
    }

    public function test_senha_recusada_vira_erro_claro(): void
    {
        Cache::flush();
        $this->salvar(['usuario' => 'maria', 'senha_acesso' => 'errada']);
        Http::fake([self::HML.'/auth' => Http::response(['mensagem' => 'Credenciais inválidas.'], 401)]);

        $this->expectException(LegadoIndisponivelException::class);
        $this->expectExceptionMessage('Credenciais inválidas.');

        app(LegadoViabilidadeClient::class)->processosPorChave('numero_tvl', '2104040');
    }

    public function test_testar_conexao_informa_sucesso(): void
    {
        $this->salvar(['token' => 'lg_token_da_tela']);
        Http::fake([self::HML.'/provisorias*' => Http::response(['data' => []]), self::HML.'/processos*' => Http::response(['data' => []])]);

        $this->actingAs($this->admin(), 'gestao')
            ->post('/gestao/config-apis/legado/testar')
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();
    }

    public function test_testar_conexao_informa_falha(): void
    {
        $this->salvar(['token' => 'lg_token_ruim']);
        Http::fake([self::HML.'/*' => Http::response(['mensagem' => 'Token inválido.'], 401)]);

        $this->actingAs($this->admin(), 'gestao')
            ->post('/gestao/config-apis/legado/testar')
            ->assertRedirect()
            ->assertSessionHasErrors('legado_connection');
    }

    public function test_analista_nao_testa_a_conexao(): void
    {
        $this->actingAs(User::factory()->analista()->withAcceptedLgpdTerm()->create(), 'gestao')
            ->post('/gestao/config-apis/legado/testar')
            ->assertForbidden();
    }

    public function test_testar_conexao_confere_as_provisorias_da_comparacao_e_os_processos(): void
    {
        $this->salvar(['token' => 'lg_token_da_tela']);
        Http::fake([
            self::HML.'/provisorias*' => Http::response(['mensagem' => 'Sem escopo provisorias:ler.'], 403),
            self::HML.'/processos*' => Http::response(['data' => []]),
        ]);

        $this->actingAs($this->admin(), 'gestao')
            ->post('/gestao/config-apis/legado/testar')
            ->assertSessionHasErrors('legado_connection');
    }

    public function test_cabecalho_host_configurado_vai_junto_com_a_url_por_ip(): void
    {
        $this->salvar(['token' => 'lg_token_da_tela', 'url_homologacao' => 'http://172.22.0.132', 'host' => 'simplifica-legado.pms.ba.gov.br']);
        Http::fake(['172.22.0.132/*' => Http::response(['data' => []])]);

        app(LegadoViabilidadeClient::class)->processosPorChave('numero_tvl', '2104040');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Host', 'simplifica-legado.pms.ba.gov.br')
            && $request->hasHeader('Authorization', 'Bearer lg_token_da_tela'));
    }

    public function test_sem_cabecalho_host_configurado_nao_envia_host_proprio(): void
    {
        $this->salvar(['token' => 'lg_token_da_tela', 'url_homologacao' => 'http://172.22.0.132']);
        Http::fake(['172.22.0.132/*' => Http::response(['data' => []])]);

        app(LegadoViabilidadeClient::class)->processosPorChave('numero_tvl', '2104040');

        Http::assertSent(fn (Request $request): bool => ! in_array('simplifica-legado.pms.ba.gov.br', $request->header('Host'), true));
    }

    public function test_host_invalido_e_rejeitado(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-apis/legado', ['campos' => [
                'em_producao' => '0', 'url_homologacao' => self::HML, 'url_producao' => self::PRD,
                'token' => '', 'usuario' => '', 'senha_acesso' => '', 'host' => 'http://com-esquema/', 'comparacao' => '0',
            ]])
            ->assertSessionHasErrors('campos.host');
    }
}
