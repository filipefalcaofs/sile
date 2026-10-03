<?php

namespace Tests\Feature\Relatorios;

use App\Enums\AnalysisPendencyStatus;
use App\Models\AnalysisPendency;
use App\Models\Holiday;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\TempoViabilidadeCalculator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Régua federal do tempo de viabilidade: horas úteis entre o protocolo e a
 * decisão, desconsiderando fins de semana, feriados ativos e todo intervalo em
 * que o processo esteve com o cidadão (pendência aberta).
 *
 * Um dia útil vale 24 horas — a régua desconta fim de semana e feriado, não
 * expediente comercial, exatamente como a definição publicada pela REDESIM
 * para o campo "QTDE. HH VIABILIDADE END".
 */
class TempoViabilidadeCalculatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function calculator(): TempoViabilidadeCalculator
    {
        return app(TempoViabilidadeCalculator::class);
    }

    private function processoDecidido(string $protocoladoEm, string $decididoEm): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocoled_at' => Carbon::parse($protocoladoEm),
        ]);

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'decided_at' => Carbon::parse($decididoEm),
        ]);

        return $request->fresh();
    }

    private function pendencia(ViabilityRequest $request, string $abertaEm, ?string $respondidaEm): void
    {
        $pendencia = AnalysisPendency::factory()->for($request, 'viabilityRequest')->create([
            'status' => $respondidaEm === null
                ? AnalysisPendencyStatus::Aberta
                : AnalysisPendencyStatus::Respondida,
            'responded_at' => $respondidaEm === null ? null : Carbon::parse($respondidaEm),
        ]);

        $pendencia->forceFill(['created_at' => Carbon::parse($abertaEm)])->save();
    }

    public function test_desconta_o_fim_de_semana_entre_o_protocolo_e_a_decisao(): void
    {
        // Sexta 17h a segunda 10h: 65 horas de relógio, 17 horas úteis.
        $request = $this->processoDecidido('2026-03-06 17:00:00', '2026-03-09 10:00:00');

        $this->assertEqualsWithDelta(17.0, $this->calculator()->horasUteis($request), 0.01);
    }

    public function test_desconta_feriado_ativo_no_meio_do_intervalo(): void
    {
        Holiday::factory()->create([
            'date' => '2026-03-04',
            'active' => true,
            'recurring_annually' => false,
        ]);

        // Terça 00h a quinta 00h: 48 horas de relógio, 24 úteis com a quarta feriado.
        $request = $this->processoDecidido('2026-03-03 00:00:00', '2026-03-05 00:00:00');

        $this->assertEqualsWithDelta(24.0, $this->calculator()->horasUteis($request), 0.01);
    }

    public function test_desconta_o_periodo_em_que_o_processo_ficou_com_o_cidadao(): void
    {
        // Segunda 00h a quarta 00h: 48 horas úteis, menos 6 horas de pendência.
        $request = $this->processoDecidido('2026-03-02 00:00:00', '2026-03-04 00:00:00');
        $this->pendencia($request, '2026-03-02 10:00:00', '2026-03-02 16:00:00');

        $this->assertEqualsWithDelta(42.0, $this->calculator()->horasUteis($request->fresh()), 0.01);
    }

    public function test_pendencia_ainda_aberta_na_decisao_desconta_ate_a_decisao(): void
    {
        // Pendência aberta na segunda 20h e nunca respondida: desconta 28h até a decisão.
        $request = $this->processoDecidido('2026-03-02 00:00:00', '2026-03-04 00:00:00');
        $this->pendencia($request, '2026-03-02 20:00:00', null);

        $this->assertEqualsWithDelta(20.0, $this->calculator()->horasUteis($request->fresh()), 0.01);
    }

    public function test_pendencias_sobrepostas_descontam_o_intervalo_uma_unica_vez(): void
    {
        // Duas pendências cobrindo 10h-16h e 14h-18h: intervalo unificado de 8 horas.
        $request = $this->processoDecidido('2026-03-02 00:00:00', '2026-03-04 00:00:00');
        $this->pendencia($request, '2026-03-02 10:00:00', '2026-03-02 16:00:00');
        $this->pendencia($request, '2026-03-02 14:00:00', '2026-03-02 18:00:00');

        $this->assertEqualsWithDelta(40.0, $this->calculator()->horasUteis($request->fresh()), 0.01);
    }

    public function test_processo_sem_decisao_nao_tem_tempo_de_viabilidade(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocoled_at' => Carbon::parse('2026-03-02 00:00:00'),
        ]);

        $this->assertNull($this->calculator()->horasUteis($request));
    }

    public function test_processo_sem_protocolo_nao_tem_tempo_de_viabilidade(): void
    {
        $request = ViabilityRequest::factory()->draft()->create(['protocoled_at' => null]);

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'decided_at' => Carbon::parse('2026-03-04 00:00:00'),
        ]);

        $this->assertNull($this->calculator()->horasUteis($request->fresh()));
    }

    public function test_pendencia_maior_que_o_intervalo_nao_produz_tempo_negativo(): void
    {
        $request = $this->processoDecidido('2026-03-02 08:00:00', '2026-03-02 12:00:00');
        $this->pendencia($request, '2026-03-02 06:00:00', '2026-03-02 20:00:00');

        $this->assertSame(0.0, $this->calculator()->horasUteis($request->fresh()));
    }
}
