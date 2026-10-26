<?php

namespace Tests\Feature\PortalServicos;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Cidadao;
use App\Models\Parameter;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConfereSessaoPortalServicosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CPF = '52998224725';

    private const TOKEN = 'tok-portal-123456789';

    private Cidadao $cidadao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
        Parameter::query()->where('key', 'features.login_portal_servicos')->update(['value' => '1']);
        Cache::flush();

        $this->cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create(['cpf' => self::CPF]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function entrouPeloPortal(int $conferidoHaSegundos, array $extra = []): static
    {
        return $this->actingAs($this->cidadao, 'web')->withSession([
            'portal_servicos.token' => Crypt::encryptString(self::TOKEN),
            'portal_servicos.conferido_em' => now()->timestamp - $conferidoHaSegundos,
            ...$extra,
        ]);
    }

    private function sessaoDoPortal(string $cpf = self::CPF): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response(['cpf' => $cpf])]);
    }

    public function test_dentro_do_intervalo_nao_chama_o_bff(): void
    {
        Http::fake();

        $this->entrouPeloPortal(60)->get('/portal/painel')->assertOk();

        Http::assertNothingSent();
    }

    public function test_token_ainda_valido_mantem_a_sessao(): void
    {
        $this->sessaoDoPortal();

        $this->entrouPeloPortal(400)->get('/portal/painel')->assertOk();

        $this->assertAuthenticatedAs($this->cidadao, 'web');
        $this->assertGreaterThanOrEqual(now()->timestamp - 5, session('portal_servicos.conferido_em'));
        Http::assertSentCount(1);
    }

    public function test_token_invalido_encerra_a_sessao_e_manda_ao_portal(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response([], 401)]);

        $this->entrouPeloPortal(400)->get('/portal/painel')
            ->assertRedirectContains('https://sso-homo.salvador.ba.gov.br/govbr/authorize/');

        $this->assertGuest('web');
        $this->assertNull(session('portal_servicos.token'));
    }

    public function test_queda_da_sessao_guarda_a_pagina_para_a_volta(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response([], 401)]);

        $this->entrouPeloPortal(400)->get('/portal/acessos')->assertRedirect();

        $this->assertStringEndsWith('/portal/acessos', (string) session('url.intended'));
    }

    public function test_com_a_chave_desligada_nao_confere_nem_derruba(): void
    {
        Parameter::query()->where('key', 'features.login_portal_servicos')->update(['value' => '0']);
        Cache::flush();
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response([], 401)]);

        $this->entrouPeloPortal(400)->get('/portal/painel')->assertOk();

        $this->assertAuthenticatedAs($this->cidadao, 'web');
        Http::assertNothingSent();
    }

    public function test_requisicao_inertia_recebe_409_com_location(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response([], 401)]);

        $versao = (string) app(HandleInertiaRequests::class)->version(request());

        $response = $this->entrouPeloPortal(400)->get('/portal/painel', ['X-Inertia' => 'true', 'X-Inertia-Version' => $versao]);

        $response->assertStatus(409);
        $this->assertStringStartsWith('https://sso-homo.salvador.ba.gov.br/govbr/authorize/', (string) $response->headers->get('X-Inertia-Location'));
    }

    public function test_bff_fora_nao_derruba_e_tenta_no_maximo_uma_vez_por_minuto(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => fn () => throw new ConnectionException('timeout')]);

        $this->entrouPeloPortal(400)->get('/portal/painel')->assertOk();
        $this->get('/portal/painel')->assertOk();

        $this->assertAuthenticatedAs($this->cidadao, 'web');
        $this->assertNotNull(session('portal_servicos.tentado_em'));
    }

    public function test_bff_fora_conta_uma_chamada_por_minuto(): void
    {
        $chamadas = 0;
        Http::fake(['*/v1/acesso/buscar-sessao' => function () use (&$chamadas) {
            $chamadas++;
            throw new ConnectionException('timeout');
        }]);

        $this->entrouPeloPortal(400)->get('/portal/painel')->assertOk();
        $this->get('/portal/painel')->assertOk();

        // Duas tentativas de conexão na primeira conferência (uma nova tentativa no cliente), nenhuma na segunda.
        $this->assertSame(2, $chamadas);
    }

    public function test_cpf_diferente_encerra_a_sessao(): void
    {
        $this->sessaoDoPortal('11144477735');

        $this->entrouPeloPortal(400)->get('/portal/painel')->assertRedirect();

        $this->assertGuest('web');
    }

    public function test_quem_nao_entrou_pelo_portal_de_servicos_nao_e_conferido(): void
    {
        Http::fake();

        $this->actingAs($this->cidadao, 'web')->get('/portal/painel')->assertOk();

        Http::assertNothingSent();
    }

    public function test_termo_lgpd_tambem_e_conferido(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response([], 401)]);

        $this->entrouPeloPortal(400)->get('/portal/termo-lgpd')->assertRedirect();

        $this->assertGuest('web');
    }
}
