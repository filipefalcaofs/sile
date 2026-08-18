<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * CRUD de setores da SEDUR (HU-138): o gestor/administrador mantém a "caixa de
 * análise" (criar/editar nome+situação) e o vínculo analista↔setor N:N pela
 * retaguarda, atrás da permissão manter-setores. Sem ela a ação é bloqueada e
 * auditada (CA-04). O setor com processos em aberto NÃO é excluído — só
 * inativado (RN-004), preservando histórico e vínculo. Um analista pode
 * pertencer a mais de um setor (RN-005). Toda alteração é auditada (RN-002).
 * Espelha o ViabilityServiceTypeController.
 */
class SectorCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function gestor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    public function test_lista_exige_permissao_manter_setores(): void
    {
        // Analista acessa a gestão mas NÃO tem manter-setores (HU-138 CA-04).
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/setores')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_gestor_lista_setores(): void
    {
        Sector::factory()->count(3)->create();

        $response = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/setores')
            ->assertOk();

        // A tela de console é construída em 10-17; aqui (backend) inspecionamos
        // as props do Inertia sem exigir o arquivo .tsx em disco.
        $page = $response->viewData('page');
        $this->assertSame('gestao/setores/index', $page['component']);
        $this->assertCount(3, $page['props']['sectors']['data']);
        $this->assertArrayHasKey('perPageOptions', $page['props']);
    }

    public function test_cria_setor_auditado(): void
    {
        $this->actingAs($this->gestor(), 'gestao')
            ->post('/gestao/setores', [
                'name' => 'Setor Centro',
                'active' => '1',
            ])
            ->assertSessionHas('status');

        $sector = Sector::query()->where('name', 'Setor Centro')->first();
        $this->assertNotNull($sector);
        $this->assertTrue($sector->active);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'setores',
            'event' => 'criar',
            'subject_type' => Sector::class,
            'subject_id' => $sector->id,
        ]);
    }

    public function test_nome_duplicado_e_rejeitado(): void
    {
        Sector::factory()->create(['name' => 'Setor Centro']);

        $this->actingAs($this->gestor(), 'gestao')
            ->post('/gestao/setores', ['name' => 'Setor Centro'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Sector::query()->where('name', 'Setor Centro')->count());
    }

    public function test_edita_nome_e_situacao(): void
    {
        $sector = Sector::factory()->create(['name' => 'Setor Antigo', 'active' => true]);

        $this->actingAs($this->gestor(), 'gestao')
            ->put("/gestao/setores/{$sector->id}", [
                'name' => 'Setor Renomeado',
                'active' => '0',
            ])
            ->assertSessionHas('status');

        $sector->refresh();
        $this->assertSame('Setor Renomeado', $sector->name);
        $this->assertFalse($sector->active);
    }

    public function test_nome_proprio_nao_colide_na_edicao(): void
    {
        // O unique ignora o próprio registro: reenviar o mesmo nome é válido.
        $sector = Sector::factory()->create(['name' => 'Setor Itapuã']);

        $this->actingAs($this->gestor(), 'gestao')
            ->put("/gestao/setores/{$sector->id}", [
                'name' => 'Setor Itapuã',
                'active' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
    }

    public function test_vincula_e_desvincula_analistas_auditado(): void
    {
        $sector = Sector::factory()->create();
        $a = $this->analista();
        $b = $this->analista();
        $gestor = $this->gestor();

        // Vincula os dois.
        $this->actingAs($gestor, 'gestao')
            ->put("/gestao/setores/{$sector->id}/analistas", [
                'analyst_ids' => [$a->id, $b->id],
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('sector_user', ['sector_id' => $sector->id, 'user_id' => $a->id]);
        $this->assertDatabaseHas('sector_user', ['sector_id' => $sector->id, 'user_id' => $b->id]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'setores',
            'event' => 'vincular-analistas',
            'subject_id' => $sector->id,
        ]);

        // Sincroniza com apenas A: B é desvinculado (intenção = conjunto exato).
        $this->actingAs($gestor, 'gestao')
            ->put("/gestao/setores/{$sector->id}/analistas", [
                'analyst_ids' => [$a->id],
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('sector_user', ['sector_id' => $sector->id, 'user_id' => $a->id]);
        $this->assertDatabaseMissing('sector_user', ['sector_id' => $sector->id, 'user_id' => $b->id]);
    }

    public function test_analista_pode_pertencer_a_dois_setores(): void
    {
        // RN-005: vínculo N:N — sincronizar o analista num setor não o tira de outro.
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $analista = $this->analista();
        $gestor = $this->gestor();

        $this->actingAs($gestor, 'gestao')
            ->put("/gestao/setores/{$setorA->id}/analistas", ['analyst_ids' => [$analista->id]])
            ->assertSessionHas('status');

        $this->actingAs($gestor, 'gestao')
            ->put("/gestao/setores/{$setorB->id}/analistas", ['analyst_ids' => [$analista->id]])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('sector_user', ['sector_id' => $setorA->id, 'user_id' => $analista->id]);
        $this->assertDatabaseHas('sector_user', ['sector_id' => $setorB->id, 'user_id' => $analista->id]);
        $this->assertSame(2, $analista->sectors()->count());
    }

    public function test_inativar_preserva_setor_e_nao_ha_rota_destrutiva(): void
    {
        // RN-004: o setor não é excluído — só inativado; permanece consultável.
        $sector = Sector::factory()->create(['active' => true]);

        $this->actingAs($this->gestor(), 'gestao')
            ->put("/gestao/setores/{$sector->id}/ativacao")
            ->assertSessionHas('status');

        $this->assertDatabaseHas('sectors', ['id' => $sector->id]);
        $this->assertFalse($sector->fresh()->active);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'setores',
            'event' => 'ativacao',
            'subject_id' => $sector->id,
        ]);

        // Não existe rota destrutiva de setor (HU-138 RN-004).
        $this->assertFalse(Route::has('gestao.setores.destroy'));
    }

    public function test_inativar_setor_com_processo_em_aberto_avisa_mas_nao_bloqueia(): void
    {
        // RN-004: inativar é permitido mesmo com processo em aberto — só avisa
        // (a redistribuição é operação separada da distribuição). Nunca exclui.
        $sector = Sector::factory()->create(['active' => true]);

        $request = ViabilityRequest::factory()->create();
        $request->forceFill([
            'sector_id' => $sector->id,
            'status' => ViabilityRequestStatus::EmAnalise,
        ])->save();

        $this->actingAs($this->gestor(), 'gestao')
            ->put("/gestao/setores/{$sector->id}/ativacao")
            ->assertSessionHas('status')
            ->assertSessionHas('warning');

        $this->assertFalse($sector->fresh()->active);
        // O processo continua vinculado ao setor (histórico preservado).
        $this->assertSame($sector->id, $request->fresh()->sector_id);
    }
}
