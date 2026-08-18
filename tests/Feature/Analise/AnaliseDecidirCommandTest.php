<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Comando de EVIDÊNCIA da decisão técnica humana (`analise:decidir {solicitacao}
 * {--finalizar}`): conclui o processo a partir da ficha do analista pelo serviço
 * REAL (AnaliseTecnicaDecisionService::decide) e imprime o desfecho de ponta a
 * ponta — status final, desfecho (deferida/indeferida), número TVL no
 * deferimento e a fundamentação/parecer. Útil para evidência/reprocesso manual,
 * espelhando o expresso:decidir.
 *
 * Sem fachada: ficha em RASCUNHO sem --finalizar sai honesto (exit 0, orienta o
 * --finalizar), processo já concluído sai honesto (exit 0) e só a solicitação
 * inexistente sai com exit 1. A decisão grava ViabilityDecision flow
 * 'analise_tecnica' com decided_by = analista (≠ null).
 */
class AnaliseDecidirCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Ficha (rascunho rev 1) de um processo em análise atribuído a um analista,
     * com o per_cnae escolhido informado.
     *
     * @param  list<array<string, mixed>>  $perCnae
     */
    private function processoEmAnalise(array $perCnae, ?User $analista = null): ViabilityRequest
    {
        $analista ??= User::factory()->analista()->create();

        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'assigned_user_id' => $analista->id,
        ])->save();

        AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => $perCnae,
        ]);

        return $request;
    }

    public function test_finaliza_e_defere_imprimindo_status_e_tvl(): void
    {
        // HU-086: --finalizar finaliza a ficha (rascunho) e DEFERE — imprime
        // DEFERIDA + o número TVL gerado. Decisão real (flow analise_tecnica).
        Event::fake([ResultadoEmitido::class]);

        $request = $this->processoEmAnalise([
            ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
        ]);

        $this->artisan('analise:decidir', ['solicitacao' => $request->id, '--finalizar' => true])
            ->expectsOutputToContain('DEFERIDA')
            ->expectsOutputToContain('TVL-')
            ->assertExitCode(0);

        $decision = $request->fresh()->decision;
        $this->assertNotNull($decision);
        $this->assertSame('analise_tecnica', $decision->flow);
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertNotNull($decision->decided_by_user_id);
        $this->assertSame(ViabilityRequestStatus::Deferida, $request->fresh()->status);
    }

    public function test_indefere_quando_ficha_tem_cnae_indeferida(): void
    {
        // RN-004: basta uma CNAE indeferida na ficha → INDEFERIDA, sem TVL.
        Event::fake([ResultadoEmitido::class]);

        $request = $this->processoEmAnalise([
            ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
            ['cnae' => '5611201', 'status_sugerido' => 'deferida', 'status_escolhido' => 'indeferida'],
        ]);

        $this->artisan('analise:decidir', ['solicitacao' => $request->id, '--finalizar' => true])
            ->expectsOutputToContain('INDEFERIDA')
            ->assertExitCode(0);

        $this->assertSame(ViabilityRequestStatus::Indeferida, $request->fresh()->status);
        $this->assertNull($request->fresh()->decision->tvl_product_number);
    }

    public function test_rascunho_sem_finalizar_sai_honesto_sem_decidir(): void
    {
        // Sem --finalizar, a ficha em rascunho NÃO decide (CA-03): orienta o uso
        // do --finalizar e sai com exit 0 (não é erro). Nenhuma decisão criada.
        $request = $this->processoEmAnalise([
            ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
        ]);

        $this->artisan('analise:decidir', ['solicitacao' => $request->id])
            ->expectsOutputToContain('--finalizar')
            ->assertExitCode(0);

        $this->assertDatabaseCount('viability_decisions', 0);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
    }

    public function test_decide_ficha_ja_finalizada_sem_a_flag(): void
    {
        // Ficha já finalizada decide direto, sem --finalizar (a flag é só para o
        // atalho de finalizar antes).
        Event::fake([ResultadoEmitido::class]);

        $analista = User::factory()->analista()->create();
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'assigned_user_id' => $analista->id,
        ])->save();
        AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'per_cnae' => [
                ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
            ],
        ]);

        $this->artisan('analise:decidir', ['solicitacao' => $request->id])
            ->expectsOutputToContain('DEFERIDA')
            ->assertExitCode(0);

        $this->assertSame(ViabilityRequestStatus::Deferida, $request->fresh()->status);
    }

    public function test_processo_ja_concluido_sai_honesto(): void
    {
        // Processo já decidido (não está mais em análise): sai honesto (exit 0),
        // sem redecidir nem estourar erro.
        $request = $this->processoEmAnalise([
            ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
        ]);
        $request->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();

        $this->artisan('analise:decidir', ['solicitacao' => $request->id, '--finalizar' => true])
            ->assertExitCode(0);
    }

    public function test_solicitacao_inexistente_sai_com_erro(): void
    {
        $this->artisan('analise:decidir', ['solicitacao' => 999999])
            ->expectsOutputToContain('não encontrada')
            ->assertExitCode(1);
    }
}
