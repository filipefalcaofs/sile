<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fila de trabalho do analista (HU-144): entrega o trabalho ordenado por prazo
 * restante (analysis_due_at) com semáforo de SLA on-the-fly (AnalysisSlaService,
 * 10-05) e contadores por status. Dois modos: "meus processos" (atribuídos ao
 * analista) e "caixa do setor" (processos do(s) setor(es) do analista — respeita
 * o vínculo, RN-005). O gestor (distribuir-processos) vê a visão agregada do
 * setor (carga por analista + processos em vermelho — CA-03). Gated por
 * consultar-solicitacoes; o 403 é auditado no ponto único. Props inspecionadas
 * sem exigir o .tsx (a tela é 10-16), como o CaixaSetorTest.
 */
class ProcessoFilaTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analistaDoSetor(Sector $sector): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach($sector);

        return $analista;
    }

    private function gestorDoSetor(Sector $sector): User
    {
        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $gestor->sectors()->attach($sector);

        return $gestor;
    }

    /**
     * Cria um processo da fila com os atributos de análise (fora do fillable).
     *
     * @param  array<string, mixed>  $analysis
     */
    private function processo(array $analysis = []): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);

        $request->forceFill(array_merge([
            'analysis_stage' => AnalysisStage::Analise,
            'analysis_stage_started_at' => now(),
            'analysis_due_at' => now()->addDays(5),
        ], $analysis))->save();

        return $request;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function filaProps(User $user, string $modo): array
    {
        $response = $this->actingAs($user, 'gestao')
            ->get("/gestao/processos/fila?modo={$modo}")
            ->assertOk();

        return $response->viewData('page')['props'];
    }

    public function test_sem_permissao_consultar_solicitacoes_recebe_403_auditado(): void
    {
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/processos/fila')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_fila_meus_lista_apenas_do_analista_ordenada_por_prazo(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $outro = $this->analistaDoSetor($setor);

        $menosUrgente = $this->processo(['assigned_user_id' => $analista->id, 'analysis_due_at' => now()->addDays(20)]);
        $maisUrgente = $this->processo(['assigned_user_id' => $analista->id, 'analysis_due_at' => now()->addDay()]);
        $deOutro = $this->processo(['assigned_user_id' => $outro->id, 'analysis_due_at' => now()->addDay()]);

        $props = $this->filaProps($analista, 'meus');
        $ids = collect($props['processos'])->pluck('id')->all();

        $this->assertContains($maisUrgente->id, $ids);
        $this->assertContains($menosUrgente->id, $ids);
        $this->assertNotContains($deOutro->id, $ids);

        $this->assertLessThan(
            array_search($menosUrgente->id, $ids, true),
            array_search($maisUrgente->id, $ids, true),
            'O processo de prazo mais curto deve vir primeiro na fila.',
        );
    }

    public function test_cada_item_traz_o_semaforo_on_the_fly(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);

        $verde = $this->processo([
            'assigned_user_id' => $analista->id,
            'analysis_stage_started_at' => now(),
            'analysis_due_at' => now()->addDays(10),
        ]);
        $vermelho = $this->processo([
            'assigned_user_id' => $analista->id,
            'analysis_stage_started_at' => now()->subDays(20),
            'analysis_due_at' => now()->subDay(),
        ]);

        $props = $this->filaProps($analista, 'meus');
        $porId = collect($props['processos'])->keyBy('id');

        $this->assertSame('verde', $porId[$verde->id]['sla']['status']);
        $this->assertSame('vermelho', $porId[$vermelho->id]['sla']['status']);
    }

    public function test_fila_setor_respeita_o_vinculo_do_analista(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setorA);

        $doSetorA = $this->processo(['sector_id' => $setorA->id]);
        $doSetorB = $this->processo(['sector_id' => $setorB->id]);

        $props = $this->filaProps($analista, 'setor');
        $ids = collect($props['processos'])->pluck('id')->all();

        $this->assertContains($doSetorA->id, $ids);
        $this->assertNotContains($doSetorB->id, $ids);
    }

    public function test_contadores_por_status_no_modo_setor(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);

        // Aguardando análise: em_analise no setor, sem analista.
        $this->processo(['sector_id' => $setor->id, 'assigned_user_id' => null, 'analysis_due_at' => now()->addDays(5)]);
        // Em análise: em_analise atribuído.
        $this->processo(['sector_id' => $setor->id, 'assigned_user_id' => $analista->id, 'analysis_due_at' => now()->addDays(5)]);
        // Em pendência (atribuído, mas conta como pendência — buckets por status
        // não se sobrepõem: não entra em "em análise").
        $this->processo(['sector_id' => $setor->id, 'assigned_user_id' => $analista->id, 'analysis_due_at' => now()->addDays(5)])
            ->forceFill(['status' => ViabilityRequestStatus::EmPendencia])->save();
        // Vencendo hoje (em_analise atribuído, prazo hoje).
        $this->processo(['sector_id' => $setor->id, 'assigned_user_id' => $analista->id, 'analysis_due_at' => now()->endOfDay()]);

        $contadores = $this->filaProps($analista, 'setor')['contadores'];

        $this->assertSame(1, $contadores['aguardando_analise']);
        $this->assertSame(2, $contadores['em_analise']);
        $this->assertSame(1, $contadores['em_pendencia']);
        $this->assertSame(1, $contadores['vencendo_hoje']);
    }

    public function test_gestor_ve_visao_agregada_do_setor(): void
    {
        $setor = Sector::factory()->create();
        $gestor = $this->gestorDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);

        $this->processo(['sector_id' => $setor->id, 'assigned_user_id' => $analista->id, 'analysis_due_at' => now()->addDays(3)]);
        // Em vermelho (vencido) no setor.
        $this->processo(['sector_id' => $setor->id, 'assigned_user_id' => $analista->id, 'analysis_stage_started_at' => now()->subDays(20), 'analysis_due_at' => now()->subDay()]);

        $props = $this->filaProps($gestor, 'setor');

        $this->assertNotNull($props['visaoSetor']);
        $this->assertGreaterThanOrEqual(1, $props['visaoSetor']['vermelhos']);

        $cargaAnalistas = collect($props['visaoSetor']['carga'])->pluck('analista_id')->all();
        $this->assertContains($analista->id, $cargaAnalistas);
    }

    public function test_analista_nao_recebe_a_visao_do_gestor(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);

        $props = $this->filaProps($analista, 'setor');

        $this->assertNull($props['visaoSetor']);
    }
}
