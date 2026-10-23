<?php

namespace Tests\Feature\PortalServicos;

use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EntradaLocalDesativadaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const LOGIN_DO_PORTAL = 'https://sso-homo.salvador.ba.gov.br/govbr/authorize/?client_id=SedurMais-PortalServicos-Hml&state=';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
    }

    private function ligarEntrada(string $valor = '1'): void
    {
        Parameter::query()->where('key', 'features.login_portal_servicos')->update(['value' => $valor]);
        Cache::flush();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rotasDeEntradaLocal(): array
    {
        return [
            'login' => ['/portal/login'],
            'cadastro' => ['/portal/register'],
            'esqueci a senha' => ['/portal/forgot-password'],
            'redefinir senha' => ['/portal/reset-password/qualquer-token'],
            'login gov.br direto' => ['/portal/login/govbr'],
        ];
    }

    #[DataProvider('rotasDeEntradaLocal')]
    public function test_com_a_chave_ligada_login_cadastro_e_senha_vao_ao_portal_de_servicos(string $rota): void
    {
        $this->ligarEntrada();

        $this->get($rota)->assertRedirectContains(self::LOGIN_DO_PORTAL);
    }

    public function test_troca_de_senha_do_cidadao_vai_ao_portal_de_servicos(): void
    {
        $this->ligarEntrada();
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao, 'web')->get('/portal/conta/senha')->assertRedirectContains(self::LOGIN_DO_PORTAL);
        $this->actingAs($cidadao, 'web')->put('/portal/conta/senha', [])->assertNotFound();
    }

    public function test_post_de_login_com_cpf_e_senha_e_recusado(): void
    {
        $this->ligarEntrada();
        $cidadao = User::factory()->cidadao()->create(['password' => bcrypt('Senha@12345')]);

        $this->post('/portal/login', ['cpf' => $cidadao->cpf, 'password' => 'Senha@12345'])->assertNotFound();

        $this->assertGuest('web');
    }

    public function test_visitante_em_rota_protegida_vai_ao_portal_e_guarda_o_destino(): void
    {
        $this->ligarEntrada();

        $location = (string) $this->get('/portal/solicitacoes/15')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith(self::LOGIN_DO_PORTAL, $location);
        $this->assertStringContainsString('PREENCHER_TOKEN', urldecode($location));
        $this->assertStringEndsWith('/portal/solicitacoes/15', (string) session('url.intended'));
    }

    public function test_console_nao_muda(): void
    {
        $this->ligarEntrada();

        $this->get('/gestao/login')->assertOk();
        $this->get('/gestao/processos')->assertRedirect(route('gestao.login'));
    }

    public function test_com_a_chave_desligada_tudo_segue_como_hoje(): void
    {
        $this->ligarEntrada('0');

        $this->get('/portal/login')->assertOk();
        $this->get('/portal/register')->assertOk();
        $this->get('/portal/painel')->assertRedirect(route('login'));
    }

    public function test_prop_entrada_portal_servicos_no_inertia(): void
    {
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao, 'web')->get('/portal/conta/perfil')
            ->assertInertia(fn (Assert $page) => $page->where('entradaPortalServicos', false));

        $this->ligarEntrada();

        $this->actingAs($cidadao, 'web')->get('/portal/conta/perfil')
            ->assertInertia(fn (Assert $page) => $page->where('entradaPortalServicos', true));
    }
}
