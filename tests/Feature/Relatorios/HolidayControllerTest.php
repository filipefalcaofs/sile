<?php

namespace Tests\Feature\Relatorios;

use App\Models\Holiday;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * CRUD administrável de feriados (HU-137): o administrador mantém o calendário de
 * feriados (criar/editar/ativar-inativar) pela retaguarda, atrás da permissão
 * manter-parametros (reuso — como setores/textos-padrão; sem permissão nova). A
 * data é única (não há feriado duplicado); inativar preserva o histórico (sem
 * destroy — RN-004). Toda alteração é auditada (RN-002 via HasAuditoria) e o 403
 * sem a permissão é auditado no ponto único (CA-04). Espelha o SectorController.
 */
class HolidayControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    public function test_lista_exige_permissao_manter_parametros(): void
    {
        // Analista acessa a gestão mas NÃO tem manter-parametros (CA-04).
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/feriados')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_administrador_lista_feriados(): void
    {
        Holiday::factory()->count(3)->create();

        $response = $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/feriados')
            ->assertOk();

        // A tela React é construída em 15-13/15-14; aqui (backend) inspecionamos
        // as props do Inertia sem exigir o arquivo .tsx em disco.
        $page = $response->viewData('page');
        $this->assertSame('gestao/feriados/index', $page['component']);
        $this->assertCount(3, $page['props']['holidays']['data']);
        $this->assertArrayHasKey('perPageOptions', $page['props']);
    }

    public function test_cria_feriado_auditado(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/feriados', [
                'date' => '2026-12-25',
                'name' => 'Natal',
                'recurring_annually' => '1',
                'active' => '1',
            ])
            ->assertSessionHas('status');

        $holiday = Holiday::query()->where('name', 'Natal')->first();
        $this->assertNotNull($holiday);
        $this->assertTrue($holiday->recurring_annually);
        $this->assertTrue($holiday->active);

        // RN-002: o cadastro de feriado é auditado (HasAuditoria → created).
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $holiday->getMorphClass(),
            'subject_id' => $holiday->id,
            'event' => 'created',
        ]);
    }

    public function test_data_duplicada_e_rejeitada_com_422(): void
    {
        Holiday::factory()->create(['date' => '2026-12-25', 'name' => 'Natal']);

        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/feriados', [
                'date' => '2026-12-25',
                'name' => 'Outro Natal',
            ])
            ->assertSessionHasErrors('date');

        // Não cria o duplicado (a data é única — sem feriado fantasma).
        $this->assertSame(1, Holiday::query()->whereDate('date', '2026-12-25')->count());
    }

    public function test_edita_feriado(): void
    {
        $holiday = Holiday::factory()->create(['name' => 'Feriado Antigo', 'date' => '2026-07-02']);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/feriados/{$holiday->id}", [
                'date' => '2026-07-02',
                'name' => 'Independência da Bahia',
                'recurring_annually' => '1',
                'active' => '1',
            ])
            ->assertSessionHas('status');

        $holiday->refresh();
        $this->assertSame('Independência da Bahia', $holiday->name);
        $this->assertTrue($holiday->recurring_annually);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $holiday->getMorphClass(),
            'subject_id' => $holiday->id,
            'event' => 'updated',
        ]);
    }

    public function test_data_propria_nao_colide_na_edicao(): void
    {
        // O unique ignora o próprio registro: reenviar a mesma data é válido.
        $holiday = Holiday::factory()->create(['date' => '2026-09-07', 'name' => 'Independência']);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/feriados/{$holiday->id}", [
                'date' => '2026-09-07',
                'name' => 'Independência do Brasil',
                'active' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
    }

    public function test_toggle_inativa_sem_excluir(): void
    {
        $holiday = Holiday::factory()->create(['active' => true]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/feriados/{$holiday->id}/ativacao")
            ->assertSessionHas('status');

        // Inativar preserva a linha (não exclui o histórico — RN-004).
        $this->assertDatabaseHas('holidays', ['id' => $holiday->id]);
        $this->assertFalse($holiday->fresh()->active);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $holiday->getMorphClass(),
            'subject_id' => $holiday->id,
            'event' => 'updated',
        ]);

        // Não existe rota destrutiva de feriado (HU-137 — preserva histórico).
        $this->assertFalse(Route::has('gestao.feriados.destroy'));
    }
}
