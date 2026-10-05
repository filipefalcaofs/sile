<?php

namespace Tests\Feature\Relatorios;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\GargalosService;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Onde o tempo é gasto. As faixas saem dos intervalos entre transições de
 * status — nada é digitado pelo analista — e por construção somam o tempo
 * total do processo.
 */
class GargalosServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): GargalosService
    {
        return app(GargalosService::class);
    }

    /**
     * @param  list<array{0: ViabilityRequestStatus, 1: string}>  $etapas
     */
    private function processoCom(array $etapas, string $decididoEm): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse($etapas[0][1]),
        ]);

        $anterior = null;

        foreach ($etapas as [$status, $em]) {
            $transicao = $request->transitions()->create([
                'from_status' => $anterior,
                'to_status' => $status,
            ]);

            $transicao->forceFill(['created_at' => Carbon::parse($em)])->save();

            $anterior = $status;
        }

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'tvl_product_number' => fake()->unique()->numerify('TVL-2026-######'),
            'decided_at' => Carbon::parse($decididoEm),
        ]);

        return $request->fresh();
    }

    private function filtros(): ReportFilters
    {
        return ReportFilters::fromArray(['data_de' => '2026-03-01', 'data_ate' => '2026-03-31']);
    }

    public function test_as_faixas_somam_o_tempo_total_do_processo(): void
    {
        // Segunda 00h fila, 08h análise, 12h pendência, decisão quarta 00h.
        $this->processoCom([
            [ViabilityRequestStatus::Protocolada, '2026-03-02 00:00:00'],
            [ViabilityRequestStatus::EmAnalise, '2026-03-02 08:00:00'],
            [ViabilityRequestStatus::EmPendencia, '2026-03-02 12:00:00'],
        ], '2026-03-04 00:00:00');

        $faixas = $this->service()->porPeriodo($this->filtros());

        $total = array_sum(array_column($faixas, 'horas'));

        $this->assertEqualsWithDelta(48.0, $total, 0.017);
    }

    public function test_atribui_as_horas_a_faixa_do_status_em_que_o_processo_esteve(): void
    {
        $this->processoCom([
            [ViabilityRequestStatus::Protocolada, '2026-03-02 00:00:00'],
            [ViabilityRequestStatus::EmAnalise, '2026-03-02 08:00:00'],
            [ViabilityRequestStatus::EmPendencia, '2026-03-02 12:00:00'],
        ], '2026-03-04 00:00:00');

        $faixas = collect($this->service()->porPeriodo($this->filtros()))->keyBy('faixa');

        $this->assertEqualsWithDelta(8.0, $faixas['fila']['horas'], 0.01);
        $this->assertEqualsWithDelta(4.0, $faixas['analise_manual']['horas'], 0.01);
        $this->assertEqualsWithDelta(36.0, $faixas['pendencia_documental']['horas'], 0.01);
    }

    public function test_processo_que_nunca_entrou_em_analise_nao_contribui_para_a_faixa(): void
    {
        $this->processoCom([
            [ViabilityRequestStatus::Protocolada, '2026-03-02 00:00:00'],
        ], '2026-03-03 00:00:00');

        $faixas = collect($this->service()->porPeriodo($this->filtros()))->keyBy('faixa');

        $this->assertArrayNotHasKey('analise_manual', $faixas->all());
        $this->assertEqualsWithDelta(24.0, $faixas['fila']['horas'], 0.01);
    }

    public function test_faixas_vem_ordenadas_por_participacao_decrescente_com_percentual(): void
    {
        $this->processoCom([
            [ViabilityRequestStatus::Protocolada, '2026-03-02 00:00:00'],
            [ViabilityRequestStatus::EmAnalise, '2026-03-02 06:00:00'],
        ], '2026-03-03 00:00:00');

        $faixas = $this->service()->porPeriodo($this->filtros());

        $this->assertSame('analise_manual', $faixas[0]['faixa']);
        $this->assertSame('fila', $faixas[1]['faixa']);
        $this->assertEqualsWithDelta(75.0, $faixas[0]['percentual'], 0.01);
        $this->assertEqualsWithDelta(25.0, $faixas[1]['percentual'], 0.01);
    }

    public function test_periodo_sem_processos_devolve_lista_vazia(): void
    {
        $this->assertSame([], $this->service()->porPeriodo($this->filtros()));
    }

    public function test_cada_faixa_traz_rotulo_legivel(): void
    {
        $this->processoCom([
            [ViabilityRequestStatus::Protocolada, '2026-03-02 00:00:00'],
            [ViabilityRequestStatus::EmAnalise, '2026-03-02 06:00:00'],
        ], '2026-03-03 00:00:00');

        $faixas = collect($this->service()->porPeriodo($this->filtros()))->keyBy('faixa');

        $this->assertSame('Análise manual', $faixas['analise_manual']['rotulo']);
        $this->assertSame('Fila', $faixas['fila']['rotulo']);
    }
}
