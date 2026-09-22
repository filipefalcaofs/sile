<?php

namespace Tests\Feature\Seeders;

use App\Models\GeoServerLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\PropertyType;
use App\Models\TratamentoCnaeBinding;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\Zona;
use App\Support\DemoMode;
use Database\Seeders\DemonstracaoClienteSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemonstracaoClienteSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ignora_quando_demo_data_desligado(): void
    {
        config(['sile.demo_data' => false]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(DemonstracaoClienteSeeder::class);

        $this->assertDatabaseMissing('users', [
            'email' => DemonstracaoClienteSeeder::CLIENTE_GESTAO_EMAIL,
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'cidadao@sile.dev']);
    }

    public function test_cria_usuarios_e_perfis_quando_demo_data_ligado(): void
    {
        config(['sile.demo_data' => true]);

        $this->seed(DemonstracaoClienteSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => DemonstracaoClienteSeeder::CLIENTE_GESTAO_EMAIL,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => DemonstracaoClienteSeeder::CLIENTE_PORTAL_EMAIL,
        ]);
        $this->assertTrue(Role::where('name', 'validacao-fase-completa')->exists());
        $this->assertTrue(DemoMode::enabled());
    }

    public function test_perfis_de_validacao_liberam_crud_de_cnaes(): void
    {
        config(['sile.demo_data' => true]);

        $this->seed(DemonstracaoClienteSeeder::class);

        $validadora = User::query()
            ->where('email', DemonstracaoClienteSeeder::CLIENTE_GESTAO_EMAIL)
            ->firstOrFail();

        $this->assertTrue($validadora->hasRole('administrador'));
        $this->assertTrue($validadora->hasPermissionTo('manter-cnaes'));
        $this->assertTrue($validadora->hasPermissionTo('manter-perfis'));
        $this->assertTrue($validadora->hasPermissionTo('manter-usuarios'));

        foreach ([
            'validacao-fase-02',
            'validacao-fase-04',
            'validacao-fase-07',
            'validacao-fase-10',
            'validacao-fase-completa',
        ] as $perfil) {
            $this->assertTrue(
                Role::findByName($perfil)->hasPermissionTo('manter-cnaes'),
                "Esperava manter-cnaes no perfil {$perfil}."
            );
        }
    }

    public function test_reseed_promove_validadora_existente_para_administrador(): void
    {
        config(['sile.demo_data' => true]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $existente = User::factory()->create([
            'email' => DemonstracaoClienteSeeder::CLIENTE_GESTAO_EMAIL,
        ]);
        $existente->syncRoles(['gestor']);

        $this->seed(DemonstracaoClienteSeeder::class);

        $existente->refresh();

        $this->assertTrue($existente->hasRole('administrador'));
        $this->assertFalse($existente->hasRole('gestor'));
    }

    public function test_nao_planta_processos_de_demonstracao(): void
    {
        config(['sile.demo_data' => true]);

        $this->seed(DemonstracaoClienteSeeder::class);

        $this->assertSame(0, ViabilityRequest::query()->count());
        $this->assertDatabaseHas('users', ['email' => DemonstracaoClienteSeeder::CLIENTE_GESTAO_EMAIL]);
    }

    /**
     * O stack do Portainer roda ESTE seeder no deploy: sem o catálogo de tipos
     * de imóvel carregado, todo valor do REGIN vira desconhecido e o processo
     * degrada para análise no ambiente de demonstração.
     */
    public function test_carrega_o_catalogo_de_tipos_de_imovel(): void
    {
        config(['sile.demo_data' => true]);

        $this->seed(DemonstracaoClienteSeeder::class);

        $galpao = PropertyType::query()->where('code', 'galpao')->first();

        $this->assertNotNull($galpao, 'Esperava o catálogo de tipos de imóvel no seed de homologação.');
        $this->assertTrue($galpao->drives_rule);
        $this->assertContains('galpao', $galpao->aliases->pluck('alias')->all());
    }

    /**
     * O stack do Portainer roda ESTE seeder no deploy: sem o catálogo de
     * camadas do GeoServer, a identificação de zona (DB-first) responde
     * indisponível; sem o cadastro de zonas, a publicação do Quadro 10 fica
     * bloqueada. O ZonaSeeder deriva da vigente do Quadro 10 — por isso entra
     * DEPOIS dos seeders LOUOS na lista de homologação.
     */
    public function test_carrega_os_catalogos_de_territorio(): void
    {
        config(['sile.demo_data' => true]);

        $this->seed(DemonstracaoClienteSeeder::class);

        $this->assertSame(20, GeoServerLayer::query()->count());
        $this->assertSame(
            Zona::query()->count(),
            LouosQuadro10Permissao::query()
                ->join('rule_versions', 'rule_versions.id', '=', 'louos_quadro10_permissoes.rule_version_id')
                ->where('rule_versions.status', 'vigente')
                ->distinct()->count('zona'),
            'Esperava uma zona cadastrada por zona distinta da vigente do Quadro 10.',
        );
        $this->assertGreaterThan(0, Zona::query()->count());
    }

    /**
     * Sem a planilha 20.08.26 no seed de homologação, o simulador REGIN não
     * pede as perguntas de tratamento e o ramo fica nulo — o motor cai em
     * pendência locacional genérica (caso 33072 em produção).
     */
    public function test_carrega_a_planilha_de_tratamento(): void
    {
        config(['sile.demo_data' => true]);

        $this->seed(DemonstracaoClienteSeeder::class);

        $this->assertSame(
            3114,
            TratamentoCnaeBinding::query()->count(),
            'Esperava os vínculos CNAE da planilha 20.08.26 no seed de homologação.',
        );
    }

    public function test_rotaciona_senhas_dos_usuarios_dev_para_a_senha_demo(): void
    {
        config(['sile.demo_data' => true]);

        $this->seed(DemonstracaoClienteSeeder::class);

        foreach (['admin@sile.dev', 'cidadao@sile.dev', 'analista@sile.dev', 'gestor@sile.dev'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertTrue(
                Hash::check(DemonstracaoClienteSeeder::DEMO_PASSWORD, $user->password),
                "Esperava a senha demo rotacionada para {$email}."
            );
        }
    }
}
