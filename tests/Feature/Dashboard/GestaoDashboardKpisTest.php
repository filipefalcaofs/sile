<?php

namespace Tests\Feature\Dashboard;

use App\Models\AccessLog;
use App\Models\Cnae;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * KPIs reais do painel de gestão (Fase 2.4): contagens calculadas sobre
 * os dados existentes, condicionadas à permissão de cada módulo.
 */
class GestaoDashboardKpisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrador_ve_kpis_calculados_sobre_dados_reais(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        Cnae::factory()->count(2)->create();
        Cnae::factory()->inactive()->create();

        $ativo = User::factory()->cidadao()->create();
        $inativado = User::factory()->cidadao()->create();
        $inativado->forceFill(['inactivated_at' => now()])->save();

        AccessLog::factory()->for($ativo)->create(['created_at' => now()]);
        AccessLog::factory()->for($ativo)->create(['created_at' => now()->subDays(2)]);
        AccessLog::factory()->for($ativo)->create(['created_at' => now()->subDays(30)]);
        AccessLog::factory()->for($ativo)->create(['event' => 'falha', 'created_at' => now()]);

        $this->actingAs($admin)
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/dashboard')
                ->where('kpis.cnaes.ativos', 2)
                ->where('kpis.cnaes.total', 3)
                ->where('kpis.usuarios.ativos', 2)
                ->where('kpis.usuarios.total', 3)
                ->where('kpis.perfis.total', Role::count())
                ->where('kpis.perfis.permissoes', Permission::count())
                ->where('kpis.acessos.logins', 2)
                ->where('kpis.acessos.janela_dias', 7));
    }

    public function test_kpis_sao_condicionados_a_permissao_do_modulo(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        Cnae::factory()->create();

        $this->actingAs($analista)
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kpis.cnaes.ativos', 1)
                ->where('kpis.usuarios', null)
                ->where('kpis.perfis', null)
                ->where('kpis.acessos', null));
    }
}
