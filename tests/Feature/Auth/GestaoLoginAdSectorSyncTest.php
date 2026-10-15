<?php

namespace Tests\Feature\Auth;

use App\Models\Sector;
use App\Models\User;
use App\Models\UserSectorAssignment;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Login AD chama discovery na mesma request e sincroniza lotação×setor
 * sem derrubar o login se a sincronização falhar.
 */
class GestaoLoginAdSectorSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['viabiliza.integrations.sedur_auth.base_url' => 'https://sedur.test/k8s/prd']);
        Cache::flush();
    }

    public function test_login_ad_sincroniza_setor_a_partir_do_discovery(): void
    {
        Sector::factory()->create([
            'code' => '0231400300',
            'name' => 'SUCOM-GERAE-STD-Setor de Triagem e Distribuição',
        ]);

        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-fake']),
            '*/ws-autenticacao/discovery' => Http::response([
                'lotacao' => [
                    'codigo' => '0231400300',
                    'nome' => 'SUCOM-GERAE-STD-Setor de Triagem e Distribuição',
                ],
            ]),
            '*/pessoaView/buscarPorCPF/*' => Http::response(['nome' => 'Nome Sincronizado']),
        ]);

        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create([
            'name' => 'Nome Antigo',
        ]);

        $this->post('/gestao/login', [
            'identifier' => $servidor->email,
            'password' => 'senha-corporativa',
        ])->assertRedirect('/gestao');

        $this->assertAuthenticatedAs($servidor, 'gestao');
        $this->assertSame('Nome Sincronizado', $servidor->fresh()->name);
        $this->assertTrue(
            $servidor->fresh()->sectors()->where('code', '0231400300')->exists(),
        );
        $this->assertDatabaseHas('user_sector_assignments', [
            'user_id' => $servidor->id,
            'source' => 'discovery',
            'ended_at' => null,
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://sedur.test/k8s/prd/ws-autenticacao/discovery'
            && $request->hasHeader('Authorization', 'Bearer tk-fake'));
    }

    public function test_falha_no_discovery_nao_derruba_login_e_fica_auditada(): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-fake']),
            '*/ws-autenticacao/discovery' => Http::response('erro', 502),
            '*/pessoaView/buscarPorCPF/*' => Http::response(['nome' => 'Nome Ok']),
        ]);

        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create();

        $this->post('/gestao/login', [
            'identifier' => $servidor->email,
            'password' => 'senha-corporativa',
        ])->assertRedirect('/gestao');

        $this->assertAuthenticatedAs($servidor, 'gestao');
        $this->assertSame(0, UserSectorAssignment::query()->count());
        $this->assertDatabaseHas('access_logs', [
            'user_id' => $servidor->id,
            'event' => 'ad-sincronizacao-falhou',
            'channel' => 'gestao',
        ]);
    }

    public function test_login_ad_cria_setor_inexistente_indicado_pelo_discovery(): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-fake']),
            '*/ws-autenticacao/discovery' => Http::response([
                'ceinNumero' => '8888888888',
                'ceinDescricao' => 'Setor Descoberto Em Homologacao',
            ]),
            '*/pessoaView/buscarPorCPF/*' => Http::response(['nome' => 'Servidor']),
        ]);

        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create();

        $this->post('/gestao/login', [
            'identifier' => 'seuusuarioad',
            'password' => 'senha-corporativa',
        ])->assertRedirect('/gestao');

        $this->assertDatabaseHas('sectors', [
            'code' => '8888888888',
            'name' => 'Setor Descoberto Em Homologacao',
            'active' => true,
        ]);
        $this->assertTrue(
            $servidor->fresh()->sectors()->where('code', '8888888888')->exists(),
        );
    }

    public function test_login_ad_vincula_o_setor_do_centro_de_informacao_da_pessoa_view(): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-fake']),
            '*/ws-autenticacao/discovery' => Http::response(['usuario' => 'seuusuarioad']),
            '*/pessoaView/buscarPorCPF/*' => Http::response([
                'nome' => 'Maria Analista',
                'telefone' => '71988887777',
                'centroInformacaoNumero' => '0231400300',
                'centroInformacaoDescricao' => 'SEDUR-DLI-CAP-ATIV-Subcoordenadoria de Atividade',
            ]),
        ]);

        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create([
            'phone' => null,
        ]);

        $this->post('/gestao/login', [
            'identifier' => 'seuusuarioad',
            'password' => 'senha-corporativa',
        ])->assertRedirect('/gestao');

        $servidor->refresh();
        $this->assertSame('Maria Analista', $servidor->name);
        $this->assertSame('71988887777', $servidor->phone);
        $this->assertTrue($servidor->sectors()->where('code', '0231400300')->exists());
        $this->assertDatabaseMissing('sectors', ['name' => 'Maria Analista']);
        $this->assertDatabaseHas('user_sector_assignments', [
            'user_id' => $servidor->id,
            'source' => 'discovery',
            'ended_at' => null,
        ]);
    }

    public function test_login_ad_atualiza_o_email_pela_pessoa_view(): void
    {
        $this->fakePessoaComEmail('servidor@salvador.ba.gov.br');
        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create(['email' => 'antigo@example.com']);

        $this->post('/gestao/login', ['identifier' => 'seuusuarioad', 'password' => 'senha-corporativa'])->assertRedirect('/gestao');

        $this->assertSame('servidor@salvador.ba.gov.br', $servidor->fresh()->email);
    }

    public function test_login_ad_nao_copia_email_de_outra_conta(): void
    {
        $this->fakePessoaComEmail('ocupado@salvador.ba.gov.br');
        User::factory()->create(['email' => 'ocupado@salvador.ba.gov.br']);
        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create(['email' => 'antigo@example.com']);

        $this->post('/gestao/login', ['identifier' => 'seuusuarioad', 'password' => 'senha-corporativa'])->assertRedirect('/gestao');

        $this->assertSame('antigo@example.com', $servidor->fresh()->email);
    }

    private function fakePessoaComEmail(string $email): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-fake']),
            '*/ws-autenticacao/discovery' => Http::response([]),
            '*/pessoaView/buscarPorCPF/*' => Http::response(['nome' => 'Servidor', 'email' => $email]),
        ]);
    }
}
