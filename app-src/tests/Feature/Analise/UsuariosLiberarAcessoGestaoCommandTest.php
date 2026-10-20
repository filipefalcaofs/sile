<?php

namespace Tests\Feature\Analise;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Libera acesso à gestão aos usuários internos com perfil que ainda não o têm.
 * Nunca toca em administrador, conta do AD, inativo, sem perfil ou papel legado
 * de cidadão.
 */
class UsuariosLiberarAcessoGestaoCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function vistoriadorLocal(): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole('Vistoriador');

        return $usuario;
    }

    public function test_sem_aplicar_apenas_lista_e_nao_altera_nada(): void
    {
        $vistoriador = $this->vistoriadorLocal();

        $this->artisan('usuarios:liberar-acesso-gestao')->assertSuccessful();

        $this->assertFalse($vistoriador->fresh()->hasDirectPermission('acessar-gestao'));
        $this->assertTrue($vistoriador->fresh()->semAcessoAGestao());
    }

    public function test_aplicar_libera_o_usuario_interno_sem_o_acesso(): void
    {
        $vistoriador = $this->vistoriadorLocal();

        $this->artisan('usuarios:liberar-acesso-gestao', ['--aplicar' => true])->assertSuccessful();

        $this->assertTrue($vistoriador->fresh()->hasDirectPermission('acessar-gestao'));
        $this->assertFalse($vistoriador->fresh()->semAcessoAGestao());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuarios',
            'event' => 'acesso-gestao-liberado',
        ]);
    }

    public function test_nao_toca_em_administrador_portal_inativo_nem_em_quem_ja_tem_acesso(): void
    {
        $administrador = User::factory()->administrador()->create();
        $cidadao = User::factory()->papelCidadaoLegado()->create();
        $semPerfil = User::factory()->create();
        $inativo = User::factory()->inactive()->create();
        $inativo->assignRole('Vistoriador');
        $analista = User::factory()->analista()->create();
        $ad = User::factory()->ad()->create();
        $ad->assignRole('Vistoriador');

        $this->artisan('usuarios:liberar-acesso-gestao', ['--aplicar' => true])->assertSuccessful();

        foreach ([$administrador, $cidadao, $semPerfil, $inativo, $analista, $ad] as $usuario) {
            $this->assertFalse($usuario->fresh()->hasDirectPermission('acessar-gestao'));
        }
        $this->assertDatabaseMissing('activity_log', ['event' => 'acesso-gestao-liberado']);
    }

    public function test_segunda_execucao_nao_repete(): void
    {
        $this->vistoriadorLocal();

        $this->artisan('usuarios:liberar-acesso-gestao', ['--aplicar' => true])->assertSuccessful();
        $this->artisan('usuarios:liberar-acesso-gestao', ['--aplicar' => true])
            ->expectsOutputToContain('Nenhum usuário')
            ->assertSuccessful();
    }
}
