<?php

namespace Tests\Feature\Seeders;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\AnalysisRecordStatus;
use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\AnaliseDevSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Prova @group postgis dos processos de exemplo da análise técnica montados pelo
 * AnaliseDevSeeder com a cadeia REAL (PostGIS): a transição protocolada→em_analise
 * reexecuta os motores territoriais (só roda em pgsql), e a partir daí os
 * SERVIÇOS REAIS levam cada processo ao seu estágio — distribuído/em análise,
 * deferido pelo analista (ViabilityDecision flow 'analise_tecnica' + TVL) com
 * malha fina, e em pendência.
 *
 * É a contraparte PostGIS do DatabaseSeederTest (SQLite, onde os estágios são
 * pulados — degradação honesta). Confirma entrega-funcional: dados fictícios,
 * lógica REAL.
 */
#[Group('postgis')]
class AnaliseSeedPostgisTest extends PostgisTestCase
{
    public function test_seed_monta_processos_da_analise_em_cada_estagio(): void
    {
        $this->seed();

        $this->assertSame('pgsql', DB::connection()->getDriverName());

        $cidadao = User::query()->where('email', 'cidadao@sile.dev')->firstOrFail();
        $analista = User::query()->where('email', 'analista@sile.dev')->firstOrFail();

        // 1) EM ANÁLISE: distribuído ao analista, na caixa do setor, com a ficha
        // rev 1 preenchida (status escolhido) e ainda em rascunho — finalizável.
        $emAnalise = $this->processo($cidadao, AnaliseDevSeeder::MARK_EM_ANALISE);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $emAnalise->status);
        $this->assertNotNull($emAnalise->sector_id, 'O processo deveria estar na caixa de triagem (sector_id).');
        $this->assertSame($analista->id, $emAnalise->assigned_user_id);

        $ficha = $emAnalise->currentAnalysisRecord()->first();
        $this->assertNotNull($ficha, 'Esperava a ficha rev 1 pré-analisada.');
        $this->assertSame(1, $ficha->revision);
        $this->assertSame(AnalysisRecordStatus::Rascunho, $ficha->status);
        $this->assertSame(
            DecisionOutcome::Deferida->value,
            $ficha->per_cnae[0]['status_escolhido'] ?? null,
            'A ficha deveria ter o status escolhido preenchido (pronta para decidir).',
        );

        // 2) DEFERIDO PELO ANALISTA: decisão humana real (flow analise_tecnica +
        // TVL) e, sobre o deferido, um encaminhamento à malha fina (HU-136 RN-001).
        $deferido = $this->processo($cidadao, AnaliseDevSeeder::MARK_DEFERIDO);
        $this->assertSame(ViabilityRequestStatus::Deferida, $deferido->status);

        $decision = $deferido->decision;
        $this->assertNotNull($decision, 'O exemplo deferido deveria ter uma decisão.');
        $this->assertSame('analise_tecnica', $decision->flow);
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertSame($analista->id, $decision->decided_by_user_id);
        $this->assertNotNull($decision->tvl_product_number);
        $this->assertStringStartsWith('TVL-', (string) $decision->tvl_product_number);

        $this->assertTrue($deferido->in_fine_mesh, 'O deferido deveria estar em malha fina.');
        $this->assertSame(1, $deferido->fineMeshReferrals()->whereNull('resolved_at')->count());

        // 3) EM PENDÊNCIA: pendência aberta pelo analista (ciclo HU-083/084).
        $pendencia = $this->processo($cidadao, AnaliseDevSeeder::MARK_PENDENCIA);
        $this->assertSame(ViabilityRequestStatus::EmPendencia, $pendencia->status);
        $this->assertSame(
            1,
            $pendencia->pendencies()->where('status', AnalysisPendencyStatus::Aberta)->count(),
        );
    }

    public function test_seed_da_analise_e_idempotente(): void
    {
        $this->seed();
        $this->seed();

        $cidadao = User::query()->where('email', 'cidadao@sile.dev')->firstOrFail();

        // Cada estágio tem um único processo (marcador estável) e uma única
        // decisão/pendência/encaminhamento — re-seed não duplica.
        foreach ([AnaliseDevSeeder::MARK_EM_ANALISE, AnaliseDevSeeder::MARK_DEFERIDO, AnaliseDevSeeder::MARK_PENDENCIA] as $marker) {
            $this->assertSame(
                1,
                ViabilityRequest::query()
                    ->where('requester_user_id', $cidadao->id)
                    ->where('address_reference', $marker)
                    ->count(),
                "O exemplo '{$marker}' não pode duplicar no re-seed.",
            );
        }

        $deferido = $this->processo($cidadao, AnaliseDevSeeder::MARK_DEFERIDO);
        $this->assertSame(1, $deferido->decision()->count());
        $this->assertSame(1, $deferido->fineMeshReferrals()->count());
    }

    private function processo(User $cidadao, string $marker): ViabilityRequest
    {
        return ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('address_reference', $marker)
            ->firstOrFail();
    }
}
