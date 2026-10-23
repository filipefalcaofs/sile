<?php

namespace Tests\Feature\PortalServicos;

use App\Models\AccessLog;
use App\Models\LegalTerm;
use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EntradaPortalServicosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CPF = '52998224725';

    private const TOKEN = 'tok-portal-123456789';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
        $this->ligarEntrada();
    }

    private function ligarEntrada(string $valor = '1'): void
    {
        Parameter::query()->where('key', 'features.login_portal_servicos')->update(['value' => $valor]);
        Cache::flush();
    }

    private function sessaoValida(string $cpf = self::CPF): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response([
            'cpf' => $cpf,
            'nome' => 'Fulana de Tal',
            'email' => 'fulana@exemplo.com',
        ])]);
    }

    private function cidadao(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create(['cpf' => self::CPF]);
    }

    private function entrar(?string $destino = 'renovacao', ?string $token = self::TOKEN)
    {
        return $this->get('/portal/entrar/portal-servicos?'.http_build_query(array_filter([
            'destino' => $destino,
            'token' => $token,
        ])));
    }

    public function test_token_valido_abre_sessao_e_vai_para_a_renovacao(): void
    {
        $this->sessaoValida();
        $user = $this->cidadao();

        $this->entrar('renovacao')->assertRedirect('/portal/renovacao');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertGuest('gestao');
        $this->assertTrue(AccessLog::query()->where('user_id', $user->id)->where('event', 'login')->where('origin', 'servicos')->exists());
    }

    public function test_destinos_consulta_e_convites(): void
    {
        $this->sessaoValida();
        $this->cidadao();

        $this->entrar('consulta')->assertRedirect('/portal/solicitacoes');
        $this->entrar('convites')->assertRedirect('/portal/solicitacoes?status=em_pendencia');
    }

    public function test_destino_invalido_vai_para_o_painel(): void
    {
        $this->sessaoValida();
        $this->cidadao();

        $this->entrar('https://malicioso.example')->assertRedirect('/portal/painel');
    }

    public function test_token_nao_aparece_no_redirect_nem_na_sessao_em_claro(): void
    {
        $this->sessaoValida();
        $this->cidadao();

        $response = $this->entrar('renovacao');

        $this->assertStringNotContainsString(self::TOKEN, (string) $response->headers->get('Location'));
        $guardado = session('portal_servicos.token');
        $this->assertNotSame(self::TOKEN, $guardado);
        $this->assertSame(self::TOKEN, Crypt::decryptString($guardado));
        $this->assertIsInt(session('portal_servicos.conferido_em'));
    }

    public function test_sem_token_vai_ao_login_do_portal_com_preencher_token(): void
    {
        Http::fake();

        $location = (string) $this->entrar('consulta', null)->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://sso-homo.salvador.ba.gov.br/govbr/authorize/?client_id=SedurMais-PortalServicos-Hml&state=', $location);
        $this->assertStringContainsString('PREENCHER_TOKEN', urldecode($location));
        $this->assertStringContainsString('destino=consulta', urldecode($location));
        Http::assertNothingSent();
    }

    public function test_marcador_sem_troca_vai_ao_login_do_portal(): void
    {
        Http::fake();

        $this->entrar('renovacao', 'PREENCHER_TOKEN')->assertRedirectContains('sso-homo.salvador.ba.gov.br');
        Http::assertNothingSent();
    }

    public function test_sessao_invalida_mostra_mensagem_e_nao_autentica(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response([], 401)]);

        $this->entrar()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/entrada-portal-servicos')
                ->where('mensagem', 'Sua entrada pelo Portal de Serviços expirou. Entre de novo pelo Portal de Serviços da SEDUR.')
                ->where('cartaUrl', 'https://servicos.sedur.salvador.ba.gov.br/carta-servicos/17'));

        $this->assertGuest('web');
    }

    public function test_bff_fora_mostra_indisponibilidade_e_nao_autentica(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => fn () => throw new ConnectionException('timeout')]);

        $this->entrar()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('mensagem', 'Não foi possível confirmar sua entrada agora. Tente de novo em alguns minutos.'));

        $this->assertGuest('web');
    }

    public function test_conta_inativa_mostra_a_mensagem_da_recusa(): void
    {
        $this->sessaoValida();
        User::factory()->inactive()->create(['cpf' => self::CPF]);

        $this->entrar()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('mensagem', 'Sua conta está inativa. Procure a SEDUR.'));

        $this->assertGuest('web');
    }

    public function test_chave_desligada_volta_ao_login_antigo(): void
    {
        $this->ligarEntrada('0');
        Http::fake();

        $this->entrar()->assertRedirect(route('login'));

        Http::assertNothingSent();
        $this->assertGuest('web');
    }

    public function test_outro_cidadao_logado_e_substituido(): void
    {
        $outro = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
        $this->actingAs($outro, 'web');
        $this->sessaoValida();
        $user = $this->cidadao();

        $this->entrar('consulta')->assertRedirect('/portal/solicitacoes');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_intended_de_fora_do_portal_e_ignorado(): void
    {
        $this->sessaoValida();
        $this->cidadao();

        $this->withSession(['url.intended' => url('/gestao/processos')])
            ->entrar('renovacao')
            ->assertRedirect('/portal/renovacao');
    }

    public function test_intended_do_portal_e_respeitado(): void
    {
        $this->sessaoValida();
        $this->cidadao();

        $this->withSession(['url.intended' => url('/portal/solicitacoes/15')])
            ->entrar('renovacao')
            ->assertRedirect('/portal/solicitacoes/15');
    }

    public function test_conta_nova_passa_pelo_termo_lgpd(): void
    {
        LegalTerm::factory()->published()->create();
        $this->sessaoValida();

        $this->entrar('consulta')->assertRedirect('/portal/solicitacoes');

        $this->get('/portal/painel')->assertRedirect(route('portal.termo-lgpd.show'));
    }
}
