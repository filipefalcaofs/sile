<?php

namespace Tests\Feature\Procuration;

use App\Http\Middleware\ResolveRepresentation;
use App\Models\Procuration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RevokeAttorneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Cidadão habilitado a navegar no portal (papel + termo LGPD aceito).
     */
    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @return array{0: User, 1: User, 2: Procuration}
     */
    private function activeProcuration(): array
    {
        $grantor = $this->portalUser();
        $attorney = $this->portalUser();

        $procuration = Procuration::factory()->create([
            'grantor_user_id' => $grantor->id,
            'attorney_user_id' => $attorney->id,
        ]);

        return [$grantor, $attorney, $procuration];
    }

    public function test_outorgante_revoga_procuracao(): void
    {
        [$grantor, , $procuration] = $this->activeProcuration();

        $this->actingAs($grantor)
            ->delete("/portal/procuracoes/{$procuration->id}")
            ->assertRedirect()
            ->assertSessionHas('status');

        $procuration->refresh();

        $this->assertNotNull($procuration->revoked_at);
        $this->assertSame($grantor->id, $procuration->revoked_by_user_id);
    }

    public function test_revogacao_gera_auditoria(): void
    {
        [$grantor, , $procuration] = $this->activeProcuration();

        $this->actingAs($grantor)->delete("/portal/procuracoes/{$procuration->id}");

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => (new Procuration)->getMorphClass(),
            'subject_id' => $procuration->id,
            'event' => 'updated',
        ]);
    }

    public function test_procurador_ativa_representacao(): void
    {
        [$grantor, $attorney, $procuration] = $this->activeProcuration();

        $this->actingAs($attorney)
            ->post('/portal/representacao', ['procuration_id' => $procuration->id])
            ->assertRedirect('/portal');

        $this->assertSame($procuration->id, session('acting_procuration_id'));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'representacao-iniciada',
            'acting_for_user_id' => $grantor->id,
        ]);
    }

    public function test_acoes_durante_representacao_carregam_em_nome_de(): void
    {
        [$grantor, $attorney, $procuration] = $this->activeProcuration();

        Route::middleware(['web', 'auth', 'verified', 'lgpd.accepted', ResolveRepresentation::class])
            ->get('/portal-teste/acao-em-representacao', function () {
                activity('teste')->log('Ação em representação');

                return redirect('/portal');
            });

        $this->actingAs($attorney)->post('/portal/representacao', [
            'procuration_id' => $procuration->id,
        ]);

        $this->actingAs($attorney)->get('/portal-teste/acao-em-representacao');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'teste',
            'description' => 'Ação em representação',
            'acting_for_user_id' => $grantor->id,
        ]);
    }

    public function test_representacao_compartilha_acting_for_com_inertia(): void
    {
        [$grantor, $attorney, $procuration] = $this->activeProcuration();

        $this->actingAs($attorney)->post('/portal/representacao', [
            'procuration_id' => $procuration->id,
        ]);

        $this->actingAs($attorney)
            ->get('/portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('actingFor.id', $grantor->id)
                ->where('actingFor.name', $grantor->name));
    }

    public function test_revogacao_tem_efeito_imediato(): void
    {
        [$grantor, $attorney, $procuration] = $this->activeProcuration();

        $this->actingAs($attorney)->post('/portal/representacao', [
            'procuration_id' => $procuration->id,
        ]);

        // O outorgante revoga em sessão própria (browser independente).
        $this->flushSession();
        $this->actingAs($grantor)->delete("/portal/procuracoes/{$procuration->id}");

        // O procurador segue navegando com a sessão dele: a representação cai na request seguinte.
        $this->flushSession();
        $this->withSession(['acting_procuration_id' => $procuration->id])
            ->actingAs($attorney)
            ->get('/portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('actingFor', null));

        $this->assertNull(session('acting_procuration_id'));
        $this->assertNotNull(session('status'));
    }

    public function test_procuracao_expirada_nao_ativa_representacao(): void
    {
        $grantor = $this->portalUser();
        $attorney = $this->portalUser();

        $procuration = Procuration::factory()->expired()->create([
            'grantor_user_id' => $grantor->id,
            'attorney_user_id' => $attorney->id,
        ]);

        $this->actingAs($attorney)
            ->post('/portal/representacao', ['procuration_id' => $procuration->id])
            ->assertSessionHasErrors('procuration_id');

        $this->assertNull(session('acting_procuration_id'));
    }

    public function test_terceiro_nao_revoga_procuracao_alheia(): void
    {
        [, , $procuration] = $this->activeProcuration();
        $thirdParty = $this->portalUser();

        $this->actingAs($thirdParty)
            ->delete("/portal/procuracoes/{$procuration->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);

        $procuration->refresh();
        $this->assertNull($procuration->revoked_at);
        $this->assertTrue($procuration->isActive());
    }

    public function test_attorney_encerra_a_propria_representacao(): void
    {
        [, $attorney, $procuration] = $this->activeProcuration();

        $this->actingAs($attorney)->post('/portal/representacao', [
            'procuration_id' => $procuration->id,
        ]);

        $this->assertSame($procuration->id, session('acting_procuration_id'));

        $this->actingAs($attorney)
            ->delete('/portal/representacao')
            ->assertRedirect();

        $this->assertNull(session('acting_procuration_id'));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'representacao-encerrada',
        ]);
    }
}
