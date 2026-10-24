<?php

namespace Tests\Feature\PortalServicos;

use App\Models\AccessLog;
use App\Models\LegalTerm;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\VinculosCpfProcesso;
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

    private function entrar(?string $destino = 'renovacao', ?string $token = self::TOKEN, ?string $numero = null)
    {
        return $this->get('/portal/entrar/portal-servicos?'.http_build_query(array_filter([
            'destino' => $destino,
            'numero' => $numero,
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

    public function test_destinos_nova_e_autonomo_abrem_a_nova_solicitacao(): void
    {
        $this->sessaoValida();
        $this->cidadao();

        $this->entrar('nova')->assertRedirect('/portal/solicitacoes/nova');
        $this->entrar('autonomo')->assertRedirect('/portal/solicitacoes/nova');
    }

    public function test_consulta_e_convites_nao_sao_mais_destinos(): void
    {
        $this->sessaoValida();
        $this->cidadao();

        $this->entrar('consulta')->assertRedirect('/portal/painel');
        $this->entrar('convites')->assertRedirect('/portal/painel');
    }

    public function test_detalhar_pelo_numero_do_processo_abre_o_do_proprio_cpf(): void
    {
        $this->sessaoValida();
        $user = $this->cidadao();
        $processo = ViabilityRequest::factory()->protocoled()->create(['requester_user_id' => $user->id]);

        $this->entrar('processo', numero: strtolower($processo->protocol_number))
            ->assertRedirect('/portal/solicitacoes/'.$processo->id);
    }

    public function test_detalhar_pelo_numero_da_viabilidade(): void
    {
        $this->sessaoValida();
        $user = $this->cidadao();
        $processo = ViabilityRequest::factory()->protocoled()->create(['requester_user_id' => $user->id]);
        ViabilityDecision::factory()->create(['viability_request_id' => $processo->id, 'tvl_product_number' => 'TVL-2026-004512']);

        $this->entrar('processo', numero: 'tvl-2026-004512')->assertRedirect('/portal/solicitacoes/'.$processo->id);
    }

    public function test_detalhar_pelo_numero_do_bap(): void
    {
        $this->sessaoValida();
        $user = $this->cidadao();
        $processo = ViabilityRequest::factory()->protocoled()->create(['requester_user_id' => $user->id, 'external_reference' => 'BAP2600012345']);

        $this->entrar('processo', numero: 'bap2600012345')->assertRedirect('/portal/solicitacoes/'.$processo->id);
    }

    public function test_numero_que_casa_com_processo_de_outra_pessoa_e_com_o_proprio_abre_o_proprio(): void
    {
        $this->sessaoValida();
        $user = $this->cidadao();
        ViabilityRequest::factory()->protocoled()->create(['protocol_number' => 'VIA-2026-000010', 'external_reference' => 'BAP-COMUM']);
        $proprio = ViabilityRequest::factory()->protocoled()->create(['protocol_number' => 'VIA-2026-000011', 'requester_user_id' => $user->id, 'external_reference' => 'BAP-COMUM']);

        $this->entrar('processo', numero: 'BAP-COMUM')->assertRedirect('/portal/solicitacoes/'.$proprio->id);
    }

    public function test_detalhar_abre_processo_regin_para_o_socio(): void
    {
        $this->sessaoValida();
        $this->cidadao();
        $processo = ViabilityRequest::factory()->protocoled()->create(['requester_user_id' => null, 'external_reference' => 'BAP2600099999']);
        app(VinculosCpfProcesso::class)->sincronizar($processo, [self::CPF]);

        $this->entrar('processo', numero: 'BAP2600099999')->assertRedirect('/portal/solicitacoes/'.$processo->id);
    }

    public function test_detalhar_processo_de_outra_pessoa_vai_para_a_lista_com_aviso(): void
    {
        $this->sessaoValida();
        $this->cidadao();
        $deOutro = ViabilityRequest::factory()->protocoled()->create();

        $this->entrar('processo', numero: $deOutro->protocol_number)
            ->assertRedirect('/portal/solicitacoes')
            ->assertSessionHas('error', 'Não encontramos esse processo entre os seus. Confira a lista abaixo.');
    }

    public function test_detalhar_numero_inexistente_ou_invalido_vai_para_a_lista_com_aviso(): void
    {
        $this->sessaoValida();
        $this->cidadao();

        $this->entrar('processo', numero: 'VIA-2026-999999')
            ->assertRedirect('/portal/solicitacoes')
            ->assertSessionHas('error');
        $this->entrar('processo', numero: '../../gestao')
            ->assertRedirect('/portal/solicitacoes')
            ->assertSessionHas('error');
        $this->entrar('processo')
            ->assertRedirect('/portal/solicitacoes')
            ->assertSessionHas('error');
    }

    public function test_sem_token_preserva_o_numero_na_volta(): void
    {
        Http::fake();

        $location = urldecode((string) $this->entrar('processo', null, 'VIA-2026-000123')->headers->get('Location'));

        $this->assertStringContainsString('destino=processo', $location);
        $this->assertStringContainsString('numero=VIA-2026-000123', $location);
        $this->assertStringContainsString('token=PREENCHER_TOKEN', $location);
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

        $location = (string) $this->entrar('nova', null)->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://sso-homo.salvador.ba.gov.br/govbr/authorize/?client_id=SedurMais-PortalServicos-Hml&state=', $location);
        $this->assertStringContainsString('PREENCHER_TOKEN', urldecode($location));
        $this->assertStringContainsString('destino=nova', urldecode($location));
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

        $this->entrar('nova')->assertRedirect('/portal/solicitacoes/nova');

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
            ->entrar(null)
            ->assertRedirect('/portal/solicitacoes/15');
    }

    public function test_destino_explicito_vence_a_pagina_guardada(): void
    {
        $this->sessaoValida();
        $this->cidadao();

        $this->withSession(['url.intended' => url('/portal/solicitacoes/15')])
            ->entrar('renovacao')
            ->assertRedirect('/portal/renovacao');
    }

    public function test_conta_nova_passa_pelo_termo_lgpd(): void
    {
        LegalTerm::factory()->published()->create();
        $this->sessaoValida();

        $this->entrar('nova')->assertRedirect('/portal/solicitacoes/nova');

        $this->get('/portal/painel')->assertRedirect(route('portal.termo-lgpd.show'));
    }
}
