<?php

namespace Tests\Feature\Cidadao;

use App\Models\Activity;
use App\Models\Cidadao;
use App\Models\User;
use App\Services\Regin\ReginProcessoProtocolador;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GestaoCidadaosTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_aba_portal_lista_cidadaos(): void
    {
        Cidadao::factory()->create(['name' => 'Maria Cidadã']);

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/usuarios?tab=portal')
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/usuarios/index')
                ->where('users.data.0.name', 'Maria Cidadã')
                ->where('users.data.0.roles', [])
                ->where('users.data.0.sem_senha', false)
                ->where('users.data.0.cpf_masked', fn ($m) => preg_match('/^\*{3}\.\*{3}\.\*{3}-\d{2}$/', (string) $m) === 1)
                ->where('counts.portal', 1)
                ->where('counts.gestao', 1));
    }

    public function test_aba_portal_lista_cidadao_sem_senha_marcado_e_nao_lista_a_conta_da_integracao(): void
    {
        Cidadao::factory()->semSenha()->create(['name' => 'Registro Balcão']);
        app(ReginProcessoProtocolador::class)->integracao();

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/usuarios?tab=portal')
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Registro Balcão')
                ->where('users.data.0.sem_senha', true)
                ->where('counts.portal', 1));
    }

    public function test_abas_nao_se_misturam_mesmo_com_ids_iguais(): void
    {
        $admin = $this->admin();
        $cidadao = Cidadao::factory()->create(['name' => 'Só Cidadã']);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios?tab=portal')
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.data', fn ($d) => collect($d)->pluck('name')->all() === ['Só Cidadã']));

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios?tab=gestao')
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.data', fn ($d) => ! collect($d)->pluck('name')->contains($cidadao->name)));
    }

    public function test_inativar_cidadao_com_auditoria_e_sem_afetar_servidor_de_mesmo_id(): void
    {
        $admin = $this->admin();
        $cidadao = Cidadao::factory()->create(['id' => 500]);
        User::factory()->analista()->create(['id' => 500]);

        $this->actingAs($admin, 'gestao')
            ->put(route('gestao.cidadaos.inativacao.update', $cidadao))
            ->assertRedirect();

        $this->assertTrue($cidadao->fresh()->isInactive());
        $this->assertNull(User::find($cidadao->id)->inactivated_at);

        $activity = Activity::where('event', 'cidadao-inativado')->firstOrFail();
        $this->assertSame($cidadao->id, $activity->properties['target_cidadao_id']);
        $this->assertTrue((bool) $activity->personal_data);
        $this->assertSame($admin->id, $activity->causer_id);
    }

    public function test_inativar_servidor_nao_inativa_cidadao_de_mesmo_id(): void
    {
        $admin = $this->admin();
        $cidadao = Cidadao::factory()->create(['id' => 500]);
        User::factory()->analista()->create(['id' => 500]);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/usuarios/{$cidadao->id}/inativacao")
            ->assertRedirect();

        $this->assertNotNull(User::find($cidadao->id)->inactivated_at);
        $this->assertFalse($cidadao->fresh()->isInactive());
    }

    public function test_conta_da_integracao_regin_nao_e_alcancada_pela_rota_de_cidadaos(): void
    {
        $admin = $this->admin();
        $integracao = app(ReginProcessoProtocolador::class)->integracao();

        $this->actingAs($admin, 'gestao')
            ->put(route('gestao.cidadaos.inativacao.update', $integracao->id))
            ->assertNotFound();

        $this->assertNull($integracao->fresh()->inactivated_at);
        $this->assertDatabaseMissing('cidadaos', ['email' => User::EMAIL_INTEGRACAO_REGIN]);
        $this->assertDatabaseMissing('activity_log', ['event' => 'cidadao-inativado']);
    }

    public function test_reativar_cidadao(): void
    {
        $cidadao = Cidadao::factory()->inactive()->create();

        $this->actingAs($this->admin(), 'gestao')
            ->put(route('gestao.cidadaos.inativacao.update', $cidadao))
            ->assertRedirect();

        $this->assertFalse($cidadao->fresh()->isInactive());
        $this->assertDatabaseHas('activity_log', ['event' => 'cidadao-reativado', 'personal_data' => true]);
    }

    public function test_servidor_sem_manter_usuarios_nao_inativa_cidadao(): void
    {
        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $cidadao = Cidadao::factory()->create();

        $this->actingAs($gestor, 'gestao')
            ->put(route('gestao.cidadaos.inativacao.update', $cidadao))
            ->assertForbidden();

        $this->assertFalse($cidadao->fresh()->isInactive());
    }

    public function test_export_csv_da_aba_portal_traz_cidadaos_e_nao_servidores(): void
    {
        Cidadao::factory()->create(['name' => 'Maria Exportada', 'cpf' => '52998224725']);
        app(ReginProcessoProtocolador::class)->integracao();
        User::factory()->analista()->create(['name' => 'Servidor Fora']);

        $response = $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/usuarios?tab=portal&formato=csv')
            ->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Maria Exportada', $csv);
        $this->assertStringContainsString('***.***.***-25', $csv);
        $this->assertStringNotContainsString('52998224725', $csv);
        $this->assertStringNotContainsString('Servidor Fora', $csv);
        $this->assertStringNotContainsString('JUCEB (integração REGIN)', $csv);
    }
}
