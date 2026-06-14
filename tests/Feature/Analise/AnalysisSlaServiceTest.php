<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Models\Parameter;
use App\Services\Analise\AnalysisSlaService;
use App\Services\Analise\SlaStatus;
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

    public function test_semaforo_verde_quando_recem_iniciado(): void
    {
        $startedAt = Carbon::parse('2026-01-10 09:00:00');
        $dueAt = $startedAt->copy()->addDays(10);

        // 0% decorrido: dentro do prazo e bem abaixo do limiar amarelo.
        $status = $this->service()->statusFor($dueAt, $startedAt, $startedAt);

        $this->assertSame(SlaStatus::Verde, $status['status']);
    }

    public function test_semaforo_amarelo_ao_atingir_o_limiar(): void
    {
        $startedAt = Carbon::parse('2026-01-10 09:00:00');
        $dueAt = $startedAt->copy()->addHours(240); // 10 dias

        // 85% da janela decorrido (204h de 240h) >= 80% (default) → amarelo.
        $now = $startedAt->copy()->addHours(204);

        $status = $this->service()->statusFor($dueAt, $startedAt, $now);

        $this->assertSame(SlaStatus::Amarelo, $status['status']);
    }

    public function test_semaforo_vermelho_quando_estourado(): void
    {
        $startedAt = Carbon::parse('2026-01-10 09:00:00');
        $dueAt = $startedAt->copy()->addDays(10);

        // now após o dueAt: prazo estourado (isOverdue do calculator) → vermelho.
        $now = $dueAt->copy()->addHour();

        $status = $this->service()->statusFor($dueAt, $startedAt, $now);

        $this->assertSame(SlaStatus::Vermelho, $status['status']);
    }

    public function test_limiar_do_semaforo_e_parametrizavel_sem_deploy(): void
    {
        $startedAt = Carbon::parse('2026-01-10 09:00:00');
        $dueAt = $startedAt->copy()->addHours(240); // 10 dias
        $now = $startedAt->copy()->addHours(120); // 50% decorrido

        // Com o limiar default (80%), 50% decorrido ainda é verde.
        $antes = $this->service()->statusFor($dueAt, $startedAt, $now);
        $this->assertSame(SlaStatus::Verde, $antes['status']);

        // O admin baixa o limiar para 50% (HU-014): a gravação do Parameter
        // invalida o cache da chave (Parameter::saved) — efeito sem deploy.
        Parameter::query()->create([
            'key' => 'analise.sla.semaforo.amarelo_percentual',
            'group' => 'analise',
            'type' => 'integer',
            'value' => '50',
            'default_value' => '80',
            'validation_rules' => ['required', 'integer', 'min:1', 'max:99'],
            'description' => 'Percentual do prazo a partir do qual o semáforo fica amarelo.',
        ]);

        // Mesma fração (50%), novo limiar → amarelo.
        $depois = $this->service()->statusFor($dueAt, $startedAt, $now);
        $this->assertSame(SlaStatus::Amarelo, $depois['status']);
    }

    public function test_status_for_devolve_o_tempo_restante_legivel(): void
    {
        $startedAt = Carbon::parse('2026-01-10 09:00:00');
        $dueAt = $startedAt->copy()->addDays(10);
        $now = $startedAt->copy()->addDays(3);

        $status = $this->service()->statusFor($dueAt, $startedAt, $now);

        $this->assertArrayHasKey('restante', $status);
        $this->assertIsString($status['restante']);
        $this->assertNotSame('', $status['restante']);
    }
}
