<?php

namespace Tests\Feature\Roles;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Atribui uma permissão (por padrão `analisar-processos`) a todos os perfis que
 * ainda não a têm, exceto os protegidos (administrador e cidadão).
 */
class AtribuirPermissaoAosPerfisCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function perfil(string $nome): Role
    {
        return Role::findByName($nome, 'web');
    }

    public function test_sem_aplicar_apenas_lista_e_nao_altera_nada(): void
    {
        $this->artisan('perfis:atribuir-permissao')->assertSuccessful();

        $this->assertFalse($this->perfil('Vistoriador')->hasPermissionTo('analisar-processos'));
    }

    public function test_aplicar_atribui_aos_perfis_que_nao_tem(): void
    {
        $this->artisan('perfis:atribuir-permissao', ['--aplicar' => true])->assertSuccessful();

        foreach (['Vistoriador', 'Padrão', 'apoio', 'Diretor'] as $nome) {
            $this->assertTrue($this->perfil($nome)->fresh()->hasPermissionTo('analisar-processos'), $nome);
        }
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'perfis',
            'event' => 'permissao-atribuida',
        ]);
    }

    public function test_nao_toca_nos_perfis_protegidos(): void
    {
        $this->artisan('perfis:atribuir-permissao', ['--aplicar' => true])->assertSuccessful();

        $cidadao = $this->perfil('cidadao');
        $this->assertTrue((bool) $cidadao->protegido);
        $this->assertFalse($cidadao->fresh()->hasPermissionTo('analisar-processos'));
    }

    public function test_segunda_execucao_nao_repete(): void
    {
        $this->artisan('perfis:atribuir-permissao', ['--aplicar' => true])->assertSuccessful();

        $this->artisan('perfis:atribuir-permissao', ['--aplicar' => true])
            ->expectsOutputToContain('Nenhum perfil')
            ->assertSuccessful();
    }

    public function test_aceita_outra_permissao_e_rejeita_permissao_inexistente(): void
    {
        $this->artisan('perfis:atribuir-permissao', ['permissao' => 'preencher-ficha-vistoria', '--aplicar' => true])
            ->assertSuccessful();
        $this->assertTrue($this->perfil('Padrão')->fresh()->hasPermissionTo('preencher-ficha-vistoria'));

        $this->artisan('perfis:atribuir-permissao', ['permissao' => 'nao-existe'])->assertFailed();
    }
}
