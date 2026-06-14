<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Models\Parameter;
use App\Services\Analise\AnalysisSlaService;
use App\Services\Expresso\BusinessDeadlineCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fundação do SLA da fila de análise (HU-144): o AnalysisSlaService materializa
 * o prazo-limite ABSOLUTO por etapa (analysis_due_at) REUSANDO o
 * BusinessDeadlineCalculator (Fase 9 — o seam de HU-137/feriados troca SÓ lá) e
 * os parâmetros administráveis analise.sla.<etapa>_dias, com DEFAULT INLINE
 * (sem depender do seeder 10-01). O semáforo (verde/amarelo/vermelho) e o tempo
 * restante são calculados ON-THE-FLY — nada de cor persistida.
 */
class AnalysisSlaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AnalysisSlaService
    {
        return new AnalysisSlaService(new BusinessDeadlineCalculator);
    }

    public function test_due_at_da_distribuicao_e_de_dois_dias_por_default(): void
    {
        $from = Carbon::parse('2026-01-10 09:00:00');

        $this->assertSame(
            $from->copy()->addDays(2)->toDateTimeString(),
            $this->service()->dueAtFor(AnalysisStage::Distribuicao, $from)->toDateTimeString(),
        );
    }

    public function test_due_at_da_analise_e_de_dez_dias_por_default(): void
    {
        $from = Carbon::parse('2026-01-10 09:00:00');

        $this->assertSame(
            $from->copy()->addDays(10)->toDateTimeString(),
            $this->service()->dueAtFor(AnalysisStage::Analise, $from)->toDateTimeString(),
        );
    }

    public function test_due_at_respeita_o_parametro_administravel_sem_deploy(): void
    {
        $from = Carbon::parse('2026-01-10 09:00:00');

        // O admin encurta o prazo da análise para 5 dias (HU-014): a gravação do
        // Parameter invalida o cache da chave (Parameter::saved) — efeito sem deploy.
        Parameter::query()->create([
            'key' => 'analise.sla.analise_dias',
            'group' => 'analise',
            'type' => 'integer',
            'value' => '5',
            'default_value' => '10',
            'validation_rules' => ['required', 'integer', 'min:1', 'max:180'],
            'description' => 'Prazo (dias) da etapa de análise para o SLA.',
        ]);

        $this->assertSame(
            $from->copy()->addDays(5)->toDateTimeString(),
            $this->service()->dueAtFor(AnalysisStage::Analise, $from)->toDateTimeString(),
        );
    }

    public function test_due_at_usa_agora_quando_a_origem_e_omitida(): void
    {
        Carbon::setTestNow('2026-01-10 09:00:00');

        $this->assertSame(
            Carbon::parse('2026-01-12 09:00:00')->toDateTimeString(),
            $this->service()->dueAtFor(AnalysisStage::Distribuicao)->toDateTimeString(),
        );

        Carbon::setTestNow();
    }
}
