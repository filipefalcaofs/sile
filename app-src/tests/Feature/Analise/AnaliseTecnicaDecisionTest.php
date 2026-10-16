<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Decisão técnica HUMANA (HU-086/087/088/089, RN-004) a partir da ficha
 * FINALIZADA: o AnaliseTecnicaDecisionService defere quando TODAS as CNAEs estão
 * deferidas na ficha e indefere quando QUALQUER uma está indeferida — gravando na
 * MESMA viability_decisions da Fase 9 com flow 'analise_tecnica' e
 * decided_by_user_id = analista (≠ null, o que distingue do fluxo expresso).
 * Diferente do FluxoExpressoService, o humano PODE deferir o caso PENDENTE (sem
 * zona oficial) com fundamentação própria da ficha — é exatamente o caso que a
 * lei manda o humano decidir. Rascunho NÃO decide (CA-03 bloqueio). A decisão é
 * o encerramento (HU-089): transição em_analise→deferida|indeferida (final).
 */
class AnaliseTecnicaDecisionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): AnaliseTecnicaDecisionService
    {
        return app(AnaliseTecnicaDecisionService::class);
    }

    private function analista(): User
    {
        return User::factory()->create();
    }

    /**
     * Ficha FINALIZADA (revisão 1) de um processo em análise, com o per_cnae
     * escolhido pelo analista. $override permite simular o motor indisponível / o
     * caso pendente (sem zona).
     *
     * @param  list<array<string, mixed>>  $perCnae
     * @param  array<string, mixed>  $override
     */
    private function fichaFinalizada(array $perCnae, array $override = []): AnalysisRecord
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        return AnalysisRecord::factory()->finalizada()->create(array_merge([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'per_cnae' => $perCnae,
        ], $override));
    }

    public function test_defere_quando_todas_as_cnaes_estao_deferidas_na_ficha(): void
    {
        // HU-086 RN-004: todas as atividades deferidas na ficha → DEFERE. Cria a
        // ViabilityDecision (flow analise_tecnica, decided_by analista) com número
        // TVL, transiciona para deferida (encerramento HU-089) e dispara
        // ResultadoEmitido após o commit.
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada([
            ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
            ['cnae' => '5611201', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
        ]);
        $analista = $this->analista();

        $result = $this->service()->decide($ficha, $analista);

        $this->assertSame(DecisionOutcome::Deferida, $result->outcome);
        $this->assertTrue($result->emitted);

        $this->assertDatabaseCount('viability_decisions', 1);
        $decision = $ficha->viabilityRequest->fresh()->decision;
        $this->assertSame('analise_tecnica', $decision->flow);
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertSame($analista->id, $decision->decided_by_user_id);
        $this->assertNotNull($decision->tvl_product_number);
        $this->assertStringStartsWith('TVL-', $decision->tvl_product_number);

        $this->assertSame(ViabilityRequestStatus::Deferida, $ficha->viabilityRequest->fresh()->status);

        Event::assertDispatched(
            ResultadoEmitido::class,
            fn (ResultadoEmitido $e): bool => $e->request->is($ficha->viabilityRequest) && $e->decision->is($decision),
        );
    }

    public function test_indefere_quando_alguma_cnae_esta_indeferida_na_ficha(): void
    {
        // HU-087 RN-004: basta uma atividade indeferida na ficha → INDEFERE o
        // processo inteiro, sem número TVL.
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada([
            ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
            ['cnae' => '5611201', 'status_sugerido' => 'deferida', 'status_escolhido' => 'indeferida'],
        ]);

        $result = $this->service()->decide($ficha, $this->analista());

        $this->assertSame(DecisionOutcome::Indeferida, $result->outcome);
        $decision = $ficha->viabilityRequest->fresh()->decision;
        $this->assertSame(DecisionOutcome::Indeferida, $decision->outcome);
        $this->assertNull($decision->tvl_product_number);
        $this->assertSame(ViabilityRequestStatus::Indeferida, $ficha->viabilityRequest->fresh()->status);

        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_indeferimento_usa_o_parecer_da_ficha_como_justificativa(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada(
            [['cnae' => '5611201', 'status_sugerido' => 'deferida', 'status_escolhido' => 'indeferida']],
            ['parecer' => 'Uso vedado na via local pelo Quadro 11-A.'],
        );

        $this->service()->decide($ficha, $this->analista());

        $this->assertSame('Uso vedado na via local pelo Quadro 11-A.', $ficha->viabilityRequest->fresh()->decision->reason);
    }

    public function test_parecer_informado_na_conclusao_prevalece_sobre_o_da_ficha(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada(
            [['cnae' => '5611201', 'status_sugerido' => 'deferida', 'status_escolhido' => 'indeferida']],
            ['parecer' => 'Parecer da ficha.'],
        );

        $this->service()->decide($ficha, $this->analista(), DecisionOutcome::Indeferida, 'Parecer da conclusão.');

        $this->assertSame('Parecer da conclusão.', $ficha->viabilityRequest->fresh()->decision->reason);
    }

    public function test_sem_parecer_usa_a_justificativa_das_atividades_indeferidas(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada(
            [
                ['cnae' => '4712100', 'cnae_formatado' => '4712-1/00', 'status_escolhido' => 'deferida', 'justificativa' => 'Permitido.'],
                ['cnae' => '5611201', 'cnae_formatado' => '5611-2/01', 'status_escolhido' => 'indeferida', 'justificativa' => 'Proibido na zona ZPR-1.'],
            ],
            ['parecer' => null],
        );

        $this->service()->decide($ficha, $this->analista());

        $reason = (string) $ficha->viabilityRequest->fresh()->decision->reason;
        $this->assertSame('CNAE 5611-2/01: Proibido na zona ZPR-1.', $reason);
    }

    public function test_indeferimento_sem_parecer_nem_justificativa_e_recusado(): void
    {
        $ficha = $this->fichaFinalizada(
            [['cnae' => '5611201', 'status_sugerido' => 'deferida', 'status_escolhido' => 'indeferida']],
            ['parecer' => null],
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Informe o parecer com o motivo do indeferimento');

        $this->service()->decide($ficha, $this->analista());
    }

    public function test_defere_sem_exigir_escolha_individual_quando_nenhuma_cnae_esta_indeferida(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada([
            ['cnae' => '4712100', 'cnae_formatado' => '4712-1/00', 'status_escolhido' => null],
            ['cnae' => '5611201', 'cnae_formatado' => '5611-2/01', 'status_escolhido' => 'analise'],
            ['cnae' => '8211300', 'cnae_formatado' => '8211-3/00', 'status_escolhido' => 'deferida'],
        ]);

        $result = $this->service()->decide($ficha, $this->analista(), DecisionOutcome::Deferida);

        $this->assertSame(DecisionOutcome::Deferida, $result->outcome);
        $this->assertSame(ViabilityRequestStatus::Deferida, $ficha->viabilityRequest->fresh()->status);
    }

    public function test_deferimento_grava_o_parecer_do_analista_como_motivo(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada([
            ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
        ]);

        $result = $this->service()->decide($ficha, $this->analista(), DecisionOutcome::Deferida, "  Atividade compatível com a zona e com o porte do imóvel.  \n");

        $this->assertSame('Atividade compatível com a zona e com o porte do imóvel.', $result->decision->reason);
    }

    public function test_deferimento_sem_parecer_segue_sem_motivo(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada([
            ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
        ]);

        $result = $this->service()->decide($ficha, $this->analista(), DecisionOutcome::Deferida, '   ');

        $this->assertNull($result->decision->reason);
    }

    public function test_indefere_com_uma_cnae_indeferida_sem_exigir_escolha_nas_demais(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada([
            ['cnae' => '4712100', 'status_escolhido' => null],
            ['cnae' => '5611201', 'cnae_formatado' => '5611-2/01', 'status_escolhido' => 'indeferida', 'justificativa' => 'Proibido na zona.'],
        ]);

        $result = $this->service()->decide($ficha, $this->analista(), DecisionOutcome::Indeferida);

        $this->assertSame(DecisionOutcome::Indeferida, $result->outcome);
        $this->assertSame(ViabilityRequestStatus::Indeferida, $ficha->viabilityRequest->fresh()->status);
        $this->assertNotNull($ficha->viabilityRequest->fresh()->decision->reason);
    }

    public function test_recusa_deferir_quando_ha_cnae_indeferida(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada([
            ['cnae' => '4712100', 'status_escolhido' => null],
            ['cnae' => '5611201', 'cnae_formatado' => '5611-2/01', 'status_escolhido' => 'indeferida'],
        ]);

        try {
            $this->service()->decide($ficha, $this->analista(), DecisionOutcome::Deferida);
            $this->fail('Deveria recusar o deferimento.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('5611-2/01', $e->getMessage());
        }

        $this->assertDatabaseCount('viability_decisions', 0);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $ficha->viabilityRequest->fresh()->status);
    }

    public function test_recusa_indeferir_quando_nenhuma_cnae_esta_indeferida(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada([
            ['cnae' => '4712100', 'status_escolhido' => null],
            ['cnae' => '5611201', 'status_escolhido' => 'deferida'],
        ]);

        try {
            $this->service()->decide($ficha, $this->analista(), DecisionOutcome::Indeferida, 'Parecer qualquer.');
            $this->fail('Deveria recusar o indeferimento.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('nenhuma atividade', $e->getMessage());
        }

        $this->assertDatabaseCount('viability_decisions', 0);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $ficha->viabilityRequest->fresh()->status);
    }

    public function test_defere_o_caso_pendente_sem_zona_com_fundamentacao_da_ficha(): void
    {
        // Anti-fachada inversa: o motor consolidou PENDENTE (zona oficial ausente)
        // e NÃO decidiria; mas o analista é quem decide o caso pendente — escolheu
        // deferida na ficha, com fundamentação própria. O serviço DEFERE (≠ do
        // expresso, que recusa o pendente).
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada(
            [[
                'cnae' => '4712100',
                'status_sugerido' => 'analise',
                'status_escolhido' => 'deferida',
                'fundamentacao' => ['Decisão técnica do analista — uso compatível com a vizinhança (LOUOS art. 1º).'],
            ]],
            [
                'engine_available' => false,
                'engine_snapshot' => ['consolidado' => 'pendente'],
                'engine_rules_versions' => null,
            ],
        );

        $result = $this->service()->decide($ficha, $this->analista());

        $this->assertSame(DecisionOutcome::Deferida, $result->outcome);
        $decision = $ficha->viabilityRequest->fresh()->decision;
        $this->assertSame('analise_tecnica', $decision->flow);
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertNotNull($decision->tvl_product_number);
        $this->assertContains(
            'Decisão técnica do analista — uso compatível com a vizinhança (LOUOS art. 1º).',
            $decision->fundamentacao,
        );
        $this->assertSame(ViabilityRequestStatus::Deferida, $ficha->viabilityRequest->fresh()->status);

        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_recusa_decidir_quando_a_ficha_ainda_esta_em_rascunho(): void
    {
        // HU-086 CA-03: ficha em rascunho não decide — bloqueio por inconsistência.
        Event::fake([ResultadoEmitido::class]);

        $ficha = $this->fichaFinalizada(
            [['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida']],
            ['status' => AnalysisRecordStatus::Rascunho, 'finalized_at' => null],
        );

        try {
            $this->service()->decide($ficha, $this->analista());
            $this->fail('Esperava DomainException ao decidir uma ficha em rascunho.');
        } catch (DomainException) {
            // esperado
        }

        $this->assertDatabaseCount('viability_decisions', 0);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $ficha->viabilityRequest->fresh()->status);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }
}
