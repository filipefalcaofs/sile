<?php

namespace Tests\Feature\Roles;

use App\Http\Controllers\Gestao\RoleController;
use App\Models\User;
use App\Services\Relatorios\Export\Sources\PerfisReportSource;
use App\Services\Relatorios\ReportFilters;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Role::users() do Spatie resolve o model pelo guard padrão, e o guard `web` agora é
 * `Cidadao`. Fora de uma rota da gestão (job de exportação, comando), a contagem de
 * servidores por perfil não pode depender disso.
 */
class ContagemServidoresPorPerfilTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        User::factory()->administrador()->create();
        auth()->shouldUse('web');
    }

    public function test_report_de_perfis_conta_servidores_com_guard_padrao_web(): void
    {
        $definicao = app(PerfisReportSource::class)->definition(ReportFilters::fromArray([]));

        $linhas = ($definicao->builder)()->get()->map($definicao->mapRow)->keyBy(0);

        $this->assertSame(1, $linhas['administrador'][2]);
        $this->assertSame(0, $linhas['apoio'][2]);
    }

    public function test_listagem_de_perfis_conta_servidores_com_guard_padrao_web(): void
    {
        $request = Request::create('/gestao/perfis', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        $props = app(RoleController::class)->index($request)->toResponse($request)->getData(true)['props'];
        $perfis = collect($props['roles'])->keyBy('name');

        $this->assertSame(1, $perfis['administrador']['users_count']);
        $this->assertSame(0, $perfis['apoio']['users_count']);
    }
}
