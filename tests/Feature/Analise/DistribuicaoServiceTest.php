<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\DistribuicaoException;
use App\Services\Analise\DistribuicaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Caixa do setor (HU-080/081): a distribuição (gestor) e a assunção (analista)
 * dos processos em análise. O processo é atribuído a um analista do setor SEM
 * sair da caixa (RN-004 — sector_id intacto, status em_analise), recalculando o
 * SLA para a etapa de análise (HU-144) e auditando por processo de forma
 * SÍNCRONA (RN-006 — não depende de evento). Só analista VINCULADO ao setor pode
 * ser distribuído/assumir; o lote (RN-007) audita item a item e isola falhas.
 */
class DistribuicaoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): DistribuicaoService
    {
        return app(DistribuicaoService::class);
    }

    /**
     * Processo na caixa de um setor: em_analise, etapa de distribuição com o SLA
     * inicial (como sai do encaminhamento — Task 1).
     */
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

    private function analistaDoSetor(Sector $sector): User
    {
        $analista = User::factory()->create();
        $analista->sectors()->attach($sector);

        return $analista;
    }

    public function test_distribuir_atribui_analista_vinculado_e_recalcula_o_sla_sem_sair_da_caixa(): void
    {
        // HU-080 RN-004 + HU-144: atribui o analista, recalcula o prazo para a
        // etapa de análise (≈ +10 dias) e NÃO altera sector_id nem o status.
        Carbon::setTestNow('2026-03-10 09:00:00');

        $sector = Sector::factory()->create();
        $analista = $this->analistaDoSetor($sector);
        $gestor = User::factory()->create();
        $request = $this->processoNaCaixa($sector);

        $this->service()->distribuir($request, $analista, $gestor);

        $fresh = $request->fresh();
        $this->assertSame($analista->id, $fresh->assigned_user_id);
        $this->assertNotNull($fresh->assigned_at);
        $this->assertSame(AnalysisStage::Analise, $fresh->analysis_stage);
        $this->assertSame(
            Carbon::now()->addDays(10)->toDateTimeString(),
            $fresh->analysis_due_at->toDateTimeString(),
        );
        // Não saiu da caixa (RN-004) nem mudou o status.
        $this->assertSame($sector->id, $fresh->sector_id);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $fresh->status);

        // RN-006: histórico de atribuição auditado SÍNCRONO com o setor.
        $activity = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'distribuir')
            ->where('subject_id', $request->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($sector->id, $activity->properties['sector_id']);
        $this->assertSame($analista->id, $activity->properties['assigned_user_id']);
        $this->assertSame($gestor->id, $activity->properties['ator_id']);

        Carbon::setTestNow();
    }

    public function test_analista_nao_vinculado_ao_setor_nao_e_distribuido(): void
    {
        // RN-004/HU-081: só analista vinculado ao setor recebe o processo.
        $sector = Sector::factory()->create();
        $request = $this->processoNaCaixa($sector);
        $forasteiro = User::factory()->create();

        try {
            $this->service()->distribuir($request, $forasteiro);
            $this->fail('Esperava DistribuicaoException para analista fora do setor.');
        } catch (DistribuicaoException) {
            // esperado
        }

        $this->assertNull($request->fresh()->assigned_user_id);
        $this->assertDatabaseMissing('activity_log', [
            'log_name' => 'analise',
            'event' => 'distribuir',
            'subject_id' => $request->id,
        ]);
    }

    public function test_distribuir_em_lote_atribui_e_audita_por_processo(): void
    {
        // RN-007: lote distribui N processos a um analista, auditando cada item.
        $sector = Sector::factory()->create();
        $analista = $this->analistaDoSetor($sector);
        $gestor = User::factory()->create();

        $requests = collect(range(1, 3))->map(fn () => $this->processoNaCaixa($sector));

        $resumo = $this->service()->distribuirLote($requests, $analista, $gestor);

        $this->assertSame(3, $resumo['ok']);
        $this->assertSame([], $resumo['falhas']);

        foreach ($requests as $request) {
            $this->assertSame($analista->id, $request->fresh()->assigned_user_id);
        }

        $this->assertSame(
            3,
            Activity::query()->where('log_name', 'analise')->where('event', 'distribuir')->count(),
        );
    }

    public function test_lote_isola_falha_de_analista_fora_do_setor(): void
    {
        // RN-007: uma falha (analista não vinculado a um dos setores) não aborta
        // os demais — o resumo registra ok e falhas por item.
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setorA);

        $valido = $this->processoNaCaixa($setorA);
        $invalido = $this->processoNaCaixa($setorB); // analista não pertence ao setor B

        $resumo = $this->service()->distribuirLote([$valido, $invalido], $analista);

        $this->assertSame(1, $resumo['ok']);
        $this->assertCount(1, $resumo['falhas']);
        $this->assertSame($invalido->id, $resumo['falhas'][0]['viability_request_id']);
        $this->assertSame($analista->id, $valido->fresh()->assigned_user_id);
        $this->assertNull($invalido->fresh()->assigned_user_id);
    }

    public function test_assumir_pelo_analista_do_setor_atribui_a_si_e_audita(): void
    {
        // HU-081: o analista vinculado pega o processo da caixa para si.
        $sector = Sector::factory()->create();
        $analista = $this->analistaDoSetor($sector);
        $request = $this->processoNaCaixa($sector);

        $this->service()->assumir($request, $analista);

        $fresh = $request->fresh();
        $this->assertSame($analista->id, $fresh->assigned_user_id);
        $this->assertSame(AnalysisStage::Analise, $fresh->analysis_stage);
        $this->assertSame($sector->id, $fresh->sector_id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'assumir',
            'subject_id' => $request->id,
        ]);
    }

    public function test_assumir_por_analista_fora_do_setor_e_bloqueado(): void
    {
        // HU-081: quem não está no setor não pode assumir o processo.
        $sector = Sector::factory()->create();
        $request = $this->processoNaCaixa($sector);
        $forasteiro = User::factory()->create();

        try {
            $this->service()->assumir($request, $forasteiro);
            $this->fail('Esperava DistribuicaoException ao assumir fora do setor.');
        } catch (DistribuicaoException) {
            // esperado
        }

        $this->assertNull($request->fresh()->assigned_user_id);
    }
}
