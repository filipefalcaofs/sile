<?php

namespace Tests\Feature\Expresso;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Deferimento automático de "exclusão" que não excluiu atividade nenhuma
 * (pedido igual à viabilidade) volta ao estado anterior: em análise, para
 * distribuir, sem decisão.
 */
class DesfazerDeferimentoSemExclusaoCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    private const MOTIVO = 'exclusão de atividade — deferimento automático, sem consulta de enquadramento';

    /**
     * @param  list<array<string, string>>  $perCnae
     */
    private function deferidoPeloMotor(array $perCnae = [], string $reason = self::MOTIVO): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->protocoled()->create(['protocol_number' => 'VIA-2026-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT)]);
        $processo->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();
        $processo->transitions()->create([
            'from_status' => ViabilityRequestStatus::Protocolada,
            'to_status' => ViabilityRequestStatus::EmAnalise,
            'reason' => 'atividade de alto risco — análise técnica',
        ]);
        $processo->forceFill([
            'analysis_stage_started_at' => now()->subDays(3),
            'status' => ViabilityRequestStatus::Deferida,
            'sector_id' => null,
        ])->save();
        $processo->transitions()->create([
            'from_status' => ViabilityRequestStatus::EmAnalise,
            'to_status' => ViabilityRequestStatus::Deferida,
        ]);

        ViabilityDecision::factory()->create([
            'viability_request_id' => $processo->id,
            'flow' => 'expresso',
            'per_cnae' => $perCnae,
            'reason' => $reason,
            'tvl_product_number' => 'TVL-'.(3003281 + $processo->id),
        ]);

        return $processo->fresh();
    }

    public function test_volta_para_a_analise_sem_decisao_e_com_o_motivo_anterior(): void
    {
        $processo = $this->deferidoPeloMotor();

        $this->artisan('processos:desfazer-deferimento-sem-exclusao')
            ->expectsOutputToContain('desfeitos 1')
            ->assertSuccessful();

        $processo->refresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->status);
        $this->assertSame(AnalysisStatus::ParaDistribuir, $processo->analysis_status);
        $this->assertSame(AnalysisStage::Distribuicao, $processo->analysis_stage);
        $this->assertNotNull($processo->analysis_due_at);
        $this->assertNull($processo->assigned_user_id);
        $this->assertNull($processo->decision()->first());
        $this->assertSame('atividade de alto risco — análise técnica', $processo->encaminhamentoAnalise->reason);
    }

    public function test_exclusao_de_verdade_e_outros_deferimentos_ficam(): void
    {
        $exclusao = $this->deferidoPeloMotor([['cnae' => '7490104', 'intencao' => 'excluir']]);
        $outro = $this->deferidoPeloMotor(reason: 'deferido pelo enquadramento');

        $this->artisan('processos:desfazer-deferimento-sem-exclusao')
            ->expectsOutputToContain('desfeitos 0')
            ->assertSuccessful();

        $this->assertSame(ViabilityRequestStatus::Deferida, $exclusao->fresh()->status);
        $this->assertSame(ViabilityRequestStatus::Deferida, $outro->fresh()->status);
    }

    public function test_dry_run_e_filtro_por_id(): void
    {
        $pedido = $this->deferidoPeloMotor();
        $outro = $this->deferidoPeloMotor();

        $this->artisan('processos:desfazer-deferimento-sem-exclusao', ['--id' => [$pedido->id], '--dry-run' => true])
            ->expectsOutputToContain('seria desfeito')
            ->assertSuccessful();

        $this->assertSame(ViabilityRequestStatus::Deferida, $pedido->fresh()->status);

        $this->artisan('processos:desfazer-deferimento-sem-exclusao', ['--id' => [$pedido->id]])->assertSuccessful();

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $pedido->fresh()->status);
        $this->assertSame(ViabilityRequestStatus::Deferida, $outro->fresh()->status);
    }
}
