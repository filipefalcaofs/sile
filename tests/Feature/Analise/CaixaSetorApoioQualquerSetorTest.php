<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Quem tem distribuir-qualquer-setor (o apoio) distribui sem olhar o setor: vê a
 * caixa de todos os setores, escolhe qualquer analista e o processo passa para
 * o setor do analista. Quem só tem distribuir-processos continua preso aos seus
 * setores.
 */
class CaixaSetorApoioQualquerSetorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function apoio(): User
    {
        return User::factory()->apoio()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  list<Sector>  $setores
     */
    private function analista(array $setores): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach(collect($setores)->pluck('id'));

        return $analista;
    }

    private function processoNaCaixa(?Sector $sector): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create();

        $request->forceFill([
            'sector_id' => $sector?->id,
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
            'analysis_stage' => AnalysisStage::Distribuicao,
            'analysis_stage_started_at' => now(),
            'analysis_due_at' => now()->addDays(2),
        ])->save();

        return $request;
    }

    public function test_apoio_e_administrador_recebem_a_permissao(): void
    {
        $this->assertTrue($this->apoio()->can('distribuir-qualquer-setor'));
        $this->assertTrue(User::factory()->administrador()->create()->can('distribuir-qualquer-setor'));
        $this->assertFalse(User::factory()->gestor()->create()->can('distribuir-qualquer-setor'));
    }

    public function test_apoio_sem_setor_ve_a_caixa_de_todos_os_setores_e_todos_os_analistas(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $doA = $this->processoNaCaixa($setorA);
        $doB = $this->processoNaCaixa($setorB);
        $semSetor = $this->processoNaCaixa(null);
        $analistaB = $this->analista([$setorB]);

        $props = $this->actingAs($this->apoio(), 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk()
            ->viewData('page')['props'];

        $ids = collect($props['processos']['data'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$doA->id, $doB->id, $semSetor->id], $ids);

        $analista = collect($props['analistas'])->firstWhere('id', $analistaB->id);
        $this->assertNotNull($analista);
        $this->assertSame([['id' => $setorB->id, 'name' => $setorB->name]], $analista['setores']);
    }

    public function test_gestor_continua_vendo_so_os_seus_setores(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $gestor->sectors()->attach($setorA);
        $doA = $this->processoNaCaixa($setorA);
        $this->processoNaCaixa($setorB);

        $ids = collect(
            $this->actingAs($gestor, 'gestao')->get('/gestao/caixa-setor')->viewData('page')['props']['processos']['data']
        )->pluck('id')->all();

        $this->assertSame([$doA->id], $ids);
    }

    public function test_apoio_distribui_para_analista_de_outro_setor_e_o_processo_vai_para_o_setor_dele(): void
    {
        $triagem = Sector::factory()->create();
        $setorAnalista = Sector::factory()->create();
        $analista = $this->analista([$setorAnalista]);
        $processo = $this->processoNaCaixa($triagem);

        $this->actingAs($this->apoio(), 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('status')
            ->assertSessionMissing('warning');

        $processo->refresh();
        $this->assertSame($analista->id, $processo->assigned_user_id);
        $this->assertSame($setorAnalista->id, $processo->sector_id);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'analise', 'event' => 'distribuir']);
    }

    public function test_processo_sem_setor_vai_para_o_setor_do_analista(): void
    {
        $setorAnalista = Sector::factory()->create();
        $analista = $this->analista([$setorAnalista]);
        $processo = $this->processoNaCaixa(null);

        $this->actingAs($this->apoio(), 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($setorAnalista->id, $processo->fresh()->sector_id);
    }

    public function test_analista_que_ja_esta_no_setor_do_processo_mantem_o_setor(): void
    {
        $setorProcesso = Sector::factory()->create();
        $outro = Sector::factory()->create();
        $analista = $this->analista([$outro, $setorProcesso]);
        $processo = $this->processoNaCaixa($setorProcesso);

        $this->actingAs($this->apoio(), 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($setorProcesso->id, $processo->fresh()->sector_id);
    }

    public function test_analista_com_varios_setores_exige_a_escolha_do_setor(): void
    {
        $triagem = Sector::factory()->create();
        $setor1 = Sector::factory()->create();
        $setor2 = Sector::factory()->create();
        $analista = $this->analista([$setor1, $setor2]);
        $processo = $this->processoNaCaixa($triagem);
        $apoio = $this->apoio();

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('warning');

        $this->assertNull($processo->fresh()->assigned_user_id);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
                'sector_id' => $setor2->id,
            ])
            ->assertSessionHas('status')
            ->assertSessionMissing('warning');

        $processo->refresh();
        $this->assertSame($analista->id, $processo->assigned_user_id);
        $this->assertSame($setor2->id, $processo->sector_id);
    }

    public function test_setor_escolhido_precisa_ser_do_analista(): void
    {
        $triagem = Sector::factory()->create();
        $setorAnalista = Sector::factory()->create();
        $alheio = Sector::factory()->create();
        $analista = $this->analista([$setorAnalista]);
        $processo = $this->processoNaCaixa($triagem);

        $this->actingAs($this->apoio(), 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
                'sector_id' => $alheio->id,
            ])
            ->assertSessionHas('warning');

        $this->assertNull($processo->fresh()->assigned_user_id);
        $this->assertSame($triagem->id, $processo->fresh()->sector_id);
    }

    public function test_analista_sem_setor_nao_recebe_processo(): void
    {
        $triagem = Sector::factory()->create();
        $analista = $this->analista([]);
        $processo = $this->processoNaCaixa($triagem);

        $this->actingAs($this->apoio(), 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('warning');

        $this->assertNull($processo->fresh()->assigned_user_id);
    }

    public function test_apoio_redistribui_para_analista_de_outro_setor(): void
    {
        $triagem = Sector::factory()->create();
        $setorNovo = Sector::factory()->create();
        $anterior = $this->analista([$triagem]);
        $nova = $this->analista([$setorNovo]);
        $processo = $this->processoNaCaixa($triagem);
        $processo->forceFill([
            'assigned_user_id' => $anterior->id,
            'analysis_status' => AnalysisStatus::Analisar,
        ])->save();

        $this->actingAs($this->apoio(), 'gestao')
            ->post('/gestao/caixa-setor/redistribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $nova->id,
            ])
            ->assertSessionHas('status');

        $processo->refresh();
        $this->assertSame($nova->id, $processo->assigned_user_id);
        $this->assertSame($setorNovo->id, $processo->sector_id);
    }

    public function test_apoio_distribui_em_lote_processos_de_setores_diferentes(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $setorAnalista = Sector::factory()->create();
        $analista = $this->analista([$setorAnalista]);
        $p1 = $this->processoNaCaixa($setorA);
        $p2 = $this->processoNaCaixa($setorB);

        $this->actingAs($this->apoio(), 'gestao')
            ->post('/gestao/caixa-setor/distribuir-lote', [
                'alocacoes' => [
                    ['analista_id' => $analista->id, 'request_ids' => [$p1->id, $p2->id]],
                ],
            ])
            ->assertRedirect('/gestao/caixa-setor')
            ->assertSessionHas('status');

        $this->assertSame($setorAnalista->id, $p1->fresh()->sector_id);
        $this->assertSame($setorAnalista->id, $p2->fresh()->sector_id);
        $this->assertSame($analista->id, $p2->fresh()->assigned_user_id);
    }

    public function test_lote_com_analista_de_varios_setores_e_rejeitado_inteiro(): void
    {
        $triagem = Sector::factory()->create();
        $analista = $this->analista([Sector::factory()->create(), Sector::factory()->create()]);
        $processo = $this->processoNaCaixa($triagem);

        $this->actingAs($this->apoio(), 'gestao')
            ->post('/gestao/caixa-setor/distribuir-lote', [
                'alocacoes' => [
                    ['analista_id' => $analista->id, 'request_ids' => [$processo->id]],
                ],
            ])
            ->assertSessionHas('error');

        $this->assertNull($processo->fresh()->assigned_user_id);
    }

    public function test_central_do_apoio_aceita_processos_de_setores_diferentes_e_lista_todos_os_analistas(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $p1 = $this->processoNaCaixa($setorA);
        $p2 = $this->processoNaCaixa($setorB);
        $analista = $this->analista([Sector::factory()->create()]);

        $props = $this->actingAs($this->apoio(), 'gestao')
            ->get('/gestao/caixa-setor/central?ids='.$p1->id.','.$p2->id)
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(2, $props['totalSelecionados']);
        $this->assertContains($analista->id, collect($props['analistas'])->pluck('analista_id')->all());
    }

    public function test_distribuicao_inteligente_do_apoio_sugere_analista_de_outro_setor(): void
    {
        $triagem = Sector::factory()->create();
        $processo = $this->processoNaCaixa($triagem);
        $analista = $this->analista([Sector::factory()->create()]);

        $props = $this->actingAs($this->apoio(), 'gestao')
            ->get('/gestao/caixa-setor/distribuicao-inteligente?ids='.$processo->id)
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame($analista->id, $props['alocacoes'][0]['analista_id']);
        $this->assertSame([$processo->id], collect($props['alocacoes'][0]['processos'])->pluck('id')->all());
    }
}
