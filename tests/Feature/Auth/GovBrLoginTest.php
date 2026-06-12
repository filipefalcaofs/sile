<?php

namespace Tests\Feature\Auth;

use App\Models\Activity;
use App\Models\GovBrAccount;
use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GovBrLoginTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_CPF = '52998224725';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
    }

    private function enableGovBr(): void
    {
        $this->setParameter('features.govbr_login', '1');
        $this->setParameter('integrations.govbr.client_id', 'sile-client');
        $this->setParameter('integrations.govbr.client_secret', 'segredo-govbr');
    }

    private function setParameter(string $key, string $value): void
    {
        Parameter::query()->where('key', $key)->first()->update(['value' => $value]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function govBrClaims(array $overrides = []): array
    {
        return array_merge([
            'sub' => self::VALID_CPF,
            'name' => 'Cidadã GOV.BR',
            'email' => 'cidada.govbr@example.com',
            'email_verified' => 'true',
            'amr' => ['passwd'],
            'reliability_info' => [
                'level' => 'bronze',
                'reliabilities' => [['id' => '601', 'updatedAt' => '2026-01-01T00:00:00.000-0300']],
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function mockSocialiteUser(array $claims): void
    {
        $socialiteUser = (new SocialiteUser)
            ->setRaw($claims)
            ->map([
                'id' => $claims['sub'] ?? null,
                'name' => $claims['name'] ?? null,
                'email' => $claims['email'] ?? null,
            ]);

        Socialite::shouldReceive('driver')
            ->with('govbr')
            ->andReturn(Mockery::mock(Provider::class, function (MockInterface $mock) use ($socialiteUser) {
                $mock->shouldReceive('user')->andReturn($socialiteUser);
            }));
    }

    public function test_botao_oculto_e_rotas_bloqueadas_com_toggle_desligado(): void
    {
        $this->get('/portal/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canLoginWithGovBr', false));

        $this->get('/portal/login/govbr')
            ->assertRedirect('/portal/login');

        $this->get('/portal/login/govbr/callback?code=abc&state=xyz')
            ->assertRedirect('/portal/login');

        $this->assertGuest();
    }

    public function test_botao_oculto_quando_toggle_ligado_sem_credenciais(): void
    {
        $this->setParameter('features.govbr_login', '1');

        $this->get('/portal/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canLoginWithGovBr', false));

        $this->get('/portal/login/govbr')->assertRedirect('/portal/login');
    }

    public function test_botao_visivel_com_toggle_e_credenciais(): void
    {
        $this->enableGovBr();

        $this->get('/portal/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canLoginWithGovBr', true));

        $this->get('/portal/register')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canLoginWithGovBr', true));
    }

    public function test_redirect_monta_url_do_login_unico_com_pkce_state_e_nonce(): void
    {
        $this->enableGovBr();

        $response = $this->get('/portal/login/govbr');

        $response->assertRedirect();

        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith('https://sso.staging.acesso.gov.br/authorize?', $location);
        $this->assertStringContainsString('client_id=sile-client', $location);
        $this->assertStringContainsString('code_challenge_method=S256', $location);
        $this->assertStringContainsString('code_challenge=', $location);
        $this->assertStringContainsString('state=', $location);
        $this->assertStringContainsString('nonce=', $location);
        $this->assertStringContainsString('response_type=code', $location);
        $this->assertStringContainsString(urlencode('portal/login/govbr/callback'), $location);
        $this->assertStringContainsString('govbr_confiabilidades', $location);
    }

    public function test_url_do_provedor_muda_por_parametro_sem_deploy(): void
    {
        $this->enableGovBr();
        $this->setParameter('integrations.govbr.base_url', 'https://sso.acesso.gov.br');

        $location = (string) $this->get('/portal/login/govbr')->headers->get('Location');

        $this->assertStringStartsWith('https://sso.acesso.gov.br/authorize?', $location);
    }

    public function test_callback_cria_conta_real_no_primeiro_acesso(): void
    {
        $this->enableGovBr();
        $this->mockSocialiteUser($this->govBrClaims());

        $response = $this->get('/portal/login/govbr/callback?code=abc&state=xyz');

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticated();

        $user = User::query()->where('cpf', self::VALID_CPF)->first();

        $this->assertNotNull($user);
        $this->assertSame('Cidadã GOV.BR', $user->name);
        $this->assertSame('cidada.govbr@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('cidadao'));
        $this->assertNotNull($user->password);

        $account = GovBrAccount::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($account);
        $this->assertSame('bronze', $account->reliability_level);
        $this->assertNotNull($account->last_authenticated_at);

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'event' => 'login',
            'channel' => 'portal',
        ]);

        $this->assertTrue(
            Activity::query()->where('log_name', 'acessos')->where('event', 'login-govbr')->exists(),
        );
        $this->assertTrue(
            Activity::query()->where('log_name', 'usuarios')->where('event', 'cadastro-govbr')->exists(),
        );
    }

    public function test_callback_vincula_conta_existente_pelo_cpf(): void
    {
        $this->enableGovBr();

        $user = User::factory()->create();

        $this->mockSocialiteUser($this->govBrClaims([
            'sub' => $user->cpf,
            'email' => 'outro-email@example.com',
        ]));

        $this->get('/portal/login/govbr/callback?code=abc&state=xyz')
            ->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->count());

        $user->refresh();

        // Dados locais preservados: o e-mail do gov.br nunca sobrescreve o local.
        $this->assertNotSame('outro-email@example.com', $user->email);
        $this->assertNotNull($user->govBrAccount);
    }

    public function test_login_govbr_de_usuario_existente_dispensa_email_do_govbr(): void
    {
        $this->enableGovBr();

        $user = User::factory()->create();

        $claims = $this->govBrClaims(['sub' => $user->cpf]);
        unset($claims['email'], $claims['email_verified']);

        $this->mockSocialiteUser($claims);

        $this->get('/portal/login/govbr/callback?code=abc&state=xyz')
            ->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_primeiro_acesso_sem_email_verificado_e_bloqueado(): void
    {
        $this->enableGovBr();

        $claims = $this->govBrClaims();
        unset($claims['email'], $claims['email_verified']);

        $this->mockSocialiteUser($claims);

        $this->get('/portal/login/govbr/callback?code=abc&state=xyz')
            ->assertRedirect('/portal/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_email_de_outro_cpf_nunca_vincula_automaticamente(): void
    {
        $this->enableGovBr();

        User::factory()->create(['email' => 'em-uso@example.com']);

        $this->mockSocialiteUser($this->govBrClaims(['email' => 'em-uso@example.com']));

        $this->get('/portal/login/govbr/callback?code=abc&state=xyz')
            ->assertRedirect('/portal/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull(User::query()->where('cpf', self::VALID_CPF)->first());
        $this->assertSame(0, GovBrAccount::query()->count());
    }

    public function test_conta_inativada_nao_autentica_via_govbr(): void
    {
        $this->enableGovBr();

        $user = User::factory()->create();
        $user->forceFill(['inactivated_at' => now()])->save();

        $this->mockSocialiteUser($this->govBrClaims(['sub' => $user->cpf]));

        $this->get('/portal/login/govbr/callback?code=abc&state=xyz')
            ->assertRedirect('/portal/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'event' => 'inativada',
        ]);
    }

    public function test_nivel_abaixo_do_minimo_parametrizado_e_bloqueado(): void
    {
        $this->enableGovBr();
        $this->setParameter('security.govbr.minimum_level', 'prata');

        $this->mockSocialiteUser($this->govBrClaims());

        $this->get('/portal/login/govbr/callback?code=abc&state=xyz')
            ->assertRedirect('/portal/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());

        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'acessos')
                ->where('event', 'acesso-negado')
                ->where('result', 'bloqueado')
                ->exists(),
        );
    }

    public function test_nivel_prata_do_govbr_satisfaz_minimo_prata(): void
    {
        $this->enableGovBr();
        $this->setParameter('security.govbr.minimum_level', 'prata');

        $this->mockSocialiteUser($this->govBrClaims([
            'reliability_info' => [
                'level' => 'silver',
                'reliabilities' => [['id' => '601'], ['id' => '301']],
            ],
        ]));

        $this->get('/portal/login/govbr/callback?code=abc&state=xyz')
            ->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticated();

        $account = GovBrAccount::query()->first();

        $this->assertSame('prata', $account->reliability_level);
        $this->assertSame(['601', '301'], $account->reliability_levels);
    }

    public function test_state_invalido_falha_sem_criar_sessao(): void
    {
        $this->enableGovBr();

        Socialite::shouldReceive('driver')
            ->with('govbr')
            ->andReturn(Mockery::mock(Provider::class, function (MockInterface $mock) {
                $mock->shouldReceive('user')->andThrow(new InvalidStateException);
            }));

        $this->get('/portal/login/govbr/callback?code=abc&state=forjado')
            ->assertRedirect('/portal/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_negativa_do_cidadao_no_provedor_redireciona_com_aviso(): void
    {
        $this->enableGovBr();

        $this->get('/portal/login/govbr/callback?error=access_denied')
            ->assertRedirect('/portal/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
