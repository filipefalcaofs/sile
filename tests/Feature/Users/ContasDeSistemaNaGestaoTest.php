<?php

namespace Tests\Feature\Users;

use App\Enums\AuthType;
use App\Models\User;
use App\Services\Regin\ReginProcessoProtocolador;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContasDeSistemaNaGestaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config()->set('viabiliza.tramitacao.atores_ocultos.emails', ['Suporte@Exemplo.test']);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create(['name' => 'Admin Gestor']);
    }

    /**
     * @return list<User>
     */
    private function contasDeSistema(): array
    {
        return [
            app(ReginProcessoProtocolador::class)->integracao(),
            User::factory()->create(['name' => 'Robo Tecnico', 'email' => 'robo@sistema.invalid']),
            User::factory()->create(['name' => 'Suporte Oculto', 'email' => 'suporte@exemplo.test']),
        ];
    }

    public function test_aba_gestao_nao_lista_nem_conta_as_contas_de_sistema(): void
    {
        $admin = $this->admin();
        $this->contasDeSistema();
        User::factory()->analista()->create(['name' => 'Analista Real']);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.data', fn ($data) => collect($data)->pluck('name')->sort()->values()->all() === ['Admin Gestor', 'Analista Real'])
                ->where('counts.gestao', 2));
    }

    public function test_export_da_aba_gestao_nao_traz_as_contas_de_sistema(): void
    {
        $admin = $this->admin();
        $this->contasDeSistema();
        User::factory()->analista()->create(['name' => 'Analista Real']);

        $csv = $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios?tab=gestao&formato=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Analista Real', $csv);
        $this->assertStringNotContainsString('JUCEB (integração REGIN)', $csv);
        $this->assertStringNotContainsString('Robo Tecnico', $csv);
        $this->assertStringNotContainsString('Suporte Oculto', $csv);
    }

    public function test_conta_de_sistema_nao_pode_ser_inativada_nem_receber_perfil_nem_mudar_autenticacao(): void
    {
        $admin = $this->admin();

        foreach ($this->contasDeSistema() as $conta) {
            $this->actingAs($admin, 'gestao')
                ->put(route('gestao.usuarios.inativacao.update', $conta))
                ->assertForbidden();

            $this->actingAs($admin, 'gestao')
                ->put(route('gestao.usuarios.papel.update', $conta), ['role' => 'administrador'])
                ->assertForbidden();

            $this->actingAs($admin, 'gestao')
                ->put(route('gestao.usuarios.autenticacao.update', $conta), ['auth_type' => 'ad', 'ad_username' => 'conta.'.$conta->id])
                ->assertForbidden();

            $conta->refresh();
            $this->assertFalse($conta->isInactive());
            $this->assertSame([], $conta->getRoleNames()->all());
            $this->assertSame(AuthType::Local, $conta->auth_type);
        }
    }

    public function test_servidor_comum_continua_gerenciavel(): void
    {
        $admin = $this->admin();
        $analista = User::factory()->analista()->create();

        $this->actingAs($admin, 'gestao')
            ->put(route('gestao.usuarios.inativacao.update', $analista))
            ->assertRedirect();

        $this->assertTrue($analista->fresh()->isInactive());
    }

    public function test_regra_de_conta_de_sistema_e_a_mesma_da_separacao(): void
    {
        $contas = $this->contasDeSistema();
        $analista = User::factory()->analista()->create();

        $this->assertTrue($contas[0]->isContaDeSistema());
        $this->assertTrue($contas[1]->isContaDeSistema());
        $this->assertTrue($contas[2]->isContaDeSistema());
        $this->assertFalse($analista->isContaDeSistema());
        $this->assertSame([$analista->id], User::query()->semContasDeSistema()->whereKey([$analista->id, ...array_map(fn (User $u) => $u->id, $contas)])->pluck('id')->all());
    }
}
