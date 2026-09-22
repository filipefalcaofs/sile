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
 * Caixa do setor na retaguarda (HU-080/081): o analista vê e assume os processos
 * em análise do(s) seu(s) setor(es) (analisar-processos); o gestor distribui —
 * single ou lote — a um analista do setor (distribuir-processos). A caixa NÃO
 * tira o processo do setor (RN-004); a lista vem ordenada por prazo
 * (analysis_due_at). Sem permissão, a ação é bloqueada e auditada no ponto único
 * (CA-04). Espelha o ResultadoExpressoController (server-driven, props Inertia
 * inspecionadas sem exigir o .tsx — a tela é 10-16).
 */
class CaixaSetorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function gestor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    private function analistaDoSetor(Sector $sector): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach($sector);

        return $analista;
    }

    private function apoioDoSetor(Sector $sector): User
    {
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $apoio->sectors()->attach($sector);

        return $apoio;
    }

    private function processoNaCaixa(Sector $sector): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create();

        $request->forceFill([
            'sector_id' => $sector->id,
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_stage' => AnalysisStage::Distribuicao,
            'analysis_stage_started_at' => now(),
            'analysis_due_at' => now()->addDays(2),
        ])->save();

        return $request;
    }

    public function test_analista_ve_so_a_caixa_dos_seus_setores(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setorA);

        $doSetorA = $this->processoNaCaixa($setorA);
        $doSetorB = $this->processoNaCaixa($setorB);

        $response = $this->actingAs($analista, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/caixa-setor/index', $page['component']);

        $ids = collect($page['props']['processos']['data'])->pluck('id')->all();
        $this->assertContains($doSetorA->id, $ids);
        $this->assertNotContains($doSetorB->id, $ids);
    }

    public function test_index_lista_ordenada_por_prazo(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);

        $menosUrgente = $this->processoNaCaixa($setor);
        $menosUrgente->forceFill(['analysis_due_at' => now()->addDays(20)])->save();

        $maisUrgente = $this->processoNaCaixa($setor);
        $maisUrgente->forceFill(['analysis_due_at' => now()->addDay()])->save();

        $response = $this->actingAs($analista, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['processos']['data'])->pluck('id')->all();

        $this->assertLessThan(
            array_search($menosUrgente->id, $ids, true),
            array_search($maisUrgente->id, $ids, true),
            'O processo de prazo mais curto deve vir primeiro na caixa.',
        );
    }

    public function test_index_do_gestor_expoe_analistas_do_setor_e_pode_distribuir(): void
    {
        $setor = Sector::factory()->create();
        $gestor = $this->gestor();
        $gestor->sectors()->attach($setor);
        $analista = $this->analistaDoSetor($setor);
        $this->processoNaCaixa($setor);

        $response = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertTrue($props['podeDistribuir'], 'O gestor (distribuir-processos) deve poder distribuir.');
        $this->assertTrue($props['podeAssumir'], 'O gestor (analisar-processos) também pode assumir.');

        $analistaIds = collect($props['analistas'])->pluck('id')->all();
        $this->assertContains($analista->id, $analistaIds, 'A lista de analistas do setor deve alimentar o seletor de distribuição.');
    }

    public function test_apoio_ve_a_caixa_e_distribui_mas_nao_assume(): void
    {
        // Perfil Apoio (tramitação): vê a caixa do setor e distribui a um
        // analista específico, mas NÃO assume — assumir é de quem analisa.
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $response = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $props = $response->viewData('page')['props'];

        $ids = collect($props['processos']['data'])->pluck('id')->all();
        $this->assertContains($processo->id, $ids, 'O apoio deve ver os processos da caixa do seu setor.');
        $this->assertTrue($props['podeDistribuir'], 'O apoio (distribuir-processos) deve poder distribuir.');
        $this->assertFalse($props['podeAssumir'], 'O apoio (sem analisar-processos) não assume processos.');

        $analistaIds = collect($props['analistas'])->pluck('id')->all();
        $this->assertContains($analista->id, $analistaIds, 'O seletor de distribuição do apoio lista os analistas do setor.');
    }

    public function test_apoio_distribui_processo_a_um_analista_do_setor(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($analista->id, $processo->fresh()->assigned_user_id);
    }

    public function test_apoio_nao_pode_assumir_processo(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $this->actingAs($apoio, 'gestao')
            ->post("/gestao/caixa-setor/{$processo->id}/assumir")
            ->assertForbidden();

        $this->assertNull($processo->fresh()->assigned_user_id);
    }

    public function test_index_do_analista_nao_expoe_distribuicao(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $this->processoNaCaixa($setor);

        $response = $this->actingAs($analista, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertFalse($props['podeDistribuir'], 'O analista (sem distribuir-processos) não distribui — só assume.');
        $this->assertTrue($props['podeAssumir'], 'O analista (analisar-processos) pode assumir.');
        $this->assertSame([], $props['analistas'], 'Sem distribuição, não há lista de analistas no payload (minimização).');
    }

    public function test_index_exige_analisar_ou_distribuir_processos_e_audita_o_403(): void
    {
        // Usuário acessa a gestão mas NÃO tem analisar-processos nem
        // distribuir-processos (CA-04) — a caixa é de quem analisa ou tramita.
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_distribuir_exige_distribuir_processos(): void
    {
        // O analista tem analisar-processos, mas distribuir é do gestor (CA-04).
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $this->actingAs($analista, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertForbidden();

        $this->assertNull($processo->fresh()->assigned_user_id);
    }

    public function test_gestor_distribui_processo_via_service(): void
    {
        $setor = Sector::factory()->create();
        $gestor = $this->gestor();
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $this->actingAs($gestor, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($analista->id, $processo->fresh()->assigned_user_id);
    }

    public function test_gestor_distribui_em_lote(): void
    {
        $setor = Sector::factory()->create();
        $gestor = $this->gestor();
        $analista = $this->analistaDoSetor($setor);
        $p1 = $this->processoNaCaixa($setor);
        $p2 = $this->processoNaCaixa($setor);

        $this->actingAs($gestor, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$p1->id, $p2->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($analista->id, $p1->fresh()->assigned_user_id);
        $this->assertSame($analista->id, $p2->fresh()->assigned_user_id);
    }

    public function test_analista_assume_processo_da_sua_caixa(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/caixa-setor/{$processo->id}/assumir")
            ->assertSessionHas('status');

        $this->assertSame($analista->id, $processo->fresh()->assigned_user_id);
        $this->assertSame(AnalysisStage::Analise, $processo->fresh()->analysis_stage);
    }

    public function test_visao_padrao_lista_apenas_processos_sem_responsavel(): void
    {
        // Aba "Para distribuir" (padrão): só o que ainda não tem analista.
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);

        $livre = $this->processoNaCaixa($setor);
        $atribuido = $this->processoNaCaixa($setor);
        $atribuido->forceFill(['assigned_user_id' => $analista->id, 'assigned_at' => now()])->save();

        $response = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $props = $response->viewData('page')['props'];
        $ids = collect($props['processos']['data'])->pluck('id')->all();

        $this->assertContains($livre->id, $ids);
        $this->assertNotContains($atribuido->id, $ids, 'Processo já distribuído não aparece na aba Para distribuir.');
        $this->assertSame('para_distribuir', $props['visao']);
        $this->assertSame(1, $props['contadores']['para_distribuir']);
        $this->assertSame(1, $props['contadores']['distribuidos']);
    }

    public function test_visao_distribuidos_lista_atribuidos_com_flag_de_redistribuicao(): void
    {
        // Aba "Distribuídos": só atribuídos; pode_redistribuir é falso após a
        // conclusão da análise (regra única AnalysisStatus::permiteRedistribuicao).
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);

        $emAnalise = $this->processoNaCaixa($setor);
        $emAnalise->forceFill([
            'assigned_user_id' => $analista->id,
            'assigned_at' => now(),
            'analysis_status' => AnalysisStatus::EmAnalise,
        ])->save();

        $concluido = $this->processoNaCaixa($setor);
        $concluido->forceFill([
            'assigned_user_id' => $analista->id,
            'assigned_at' => now(),
            'analysis_status' => AnalysisStatus::AnaliseConcluida,
        ])->save();

        $livre = $this->processoNaCaixa($setor);

        $response = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?visao=distribuidos')
            ->assertOk();

        $props = $response->viewData('page')['props'];
        $itens = collect($props['processos']['data']);

        $this->assertSame('distribuidos', $props['visao']);
        $this->assertFalse($itens->contains('id', $livre->id), 'Processo sem responsável não aparece na aba Distribuídos.');
        $this->assertTrue($itens->firstWhere('id', $emAnalise->id)['pode_redistribuir']);
        $this->assertFalse($itens->firstWhere('id', $concluido->id)['pode_redistribuir']);
    }
}
