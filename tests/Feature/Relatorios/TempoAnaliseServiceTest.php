<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\TempoAnaliseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tempo POR ETAPA da timeline (HU-129) e os relatórios SAPS de tempo medidos em
 * tempo ÚTIL — descontando fins de semana/feriados via
 * BusinessDeadlineCalculator::businessDurationBetween (15-04). É a correção da
 * distorção do legado, que reporta tempo de CALENDÁRIO e mistura etapas: aqui
 * cada etapa (preenchimento, espera, análise, pendência) é isolada a partir de
 * viability_request_transitions e o tempo de espera que não é trabalho da SEDUR
 * fica visível. Sem dados no período → vazio HONESTO, NUNCA tempo inventado
 * (CA-03 anti-fachada).
 */
class TempoAnaliseServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TempoAnaliseService
    {
        return app(TempoAnaliseService::class);
    }

    private function junho(): ReportFilters
    {
        return ReportFilters::fromArray(['data_de' => '2026-06-01', 'data_ate' => '2026-06-30']);
    }

    /**
     * Solicitação protocolada com created_at/protocoled_at cravados.
     */
    private function processo(string $criadoEm, string $protocoladoEm): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocol_number' => null,
            'created_at' => Carbon::parse($criadoEm),
            'protocoled_at' => Carbon::parse($protocoladoEm),
        ]);
    }

    /**
     * Transição cravada no tempo (created_at fixo) — a fonte das etapas.
     */
    private function transicao(ViabilityRequest $r, ViabilityRequestStatus $de, ViabilityRequestStatus $para, string $quando): void
    {
        ViabilityRequestTransition::factory()->create([
            'viability_request_id' => $r->id,
            'from_status' => $de,
            'to_status' => $para,
            'created_at' => Carbon::parse($quando),
        ]);
    }

    /**
     * @return Collection<string, array{etapa: string, media_minutos: int|null, amostras: int}>
     */
    private function porEtapa(ReportFilters $f): Collection
    {
        return collect($this->service()->tempoPorEtapa($f)['etapas'])->keyBy('etapa');
    }

    #[Test]
    public function tempo_por_etapa_em_tempo_util_desconta_fim_de_semana(): void
    {
        // Processo A — ciclo completo; a PENDÊNCIA cruza sábado/domingo (prova o
        // desconto de tempo útil, anti-distorção do legado).
        $a = $this->processo('2026-06-10 09:00', '2026-06-10 10:00'); // Quarta
        $this->transicao($a, ViabilityRequestStatus::Rascunho, ViabilityRequestStatus::Protocolada, '2026-06-10 10:00');     // preenchimento 60
        $this->transicao($a, ViabilityRequestStatus::Protocolada, ViabilityRequestStatus::EmAnalise, '2026-06-10 14:00');     // espera 240
        $this->transicao($a, ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::EmPendencia, '2026-06-10 17:00');     // análise 180
        $this->transicao($a, ViabilityRequestStatus::EmPendencia, ViabilityRequestStatus::EmAnalise, '2026-06-15 09:00');     // pendência (cruza fds) 3840
        $this->transicao($a, ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::Deferida, '2026-06-15 11:00');        // análise 120 (total 300)

        // Processo B — mesmo dia, sem pendência.
        $b = $this->processo('2026-06-09 08:00', '2026-06-09 08:40'); // Terça
        $this->transicao($b, ViabilityRequestStatus::Rascunho, ViabilityRequestStatus::Protocolada, '2026-06-09 08:40');      // preenchimento 40
        $this->transicao($b, ViabilityRequestStatus::Protocolada, ViabilityRequestStatus::EmAnalise, '2026-06-09 10:40');      // espera 120
        $this->transicao($b, ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::Deferida, '2026-06-09 12:40');         // análise 120

        $etapas = $this->porEtapa($this->junho());

        $this->assertSame(50, $etapas['preenchimento']['media_minutos']);   // (60+40)/2
        $this->assertSame(2, $etapas['preenchimento']['amostras']);

        $this->assertSame(180, $etapas['espera']['media_minutos']);         // (240+120)/2
        $this->assertSame(2, $etapas['espera']['amostras']);

        $this->assertSame(210, $etapas['analise']['media_minutos']);        // (300+120)/2
        $this->assertSame(2, $etapas['analise']['amostras']);

        // Pendência [Qua 17:00 → Seg 09:00]: 3840 min ÚTEIS (desconta sáb+dom),
        // NÃO 6720 de calendário — a prova anti-distorção da HU-129.
        $this->assertSame(3840, $etapas['pendencia']['media_minutos']);
        $this->assertSame(1, $etapas['pendencia']['amostras']);
    }

    /**
     * Decisão com TVL cravada (protocoled_at→decided_at) — SAPS Tempo de Emissão.
     */
    private function decisaoTvl(string $protocoladoEm, string $decididoEm, ?string $tvl): void
    {
        $r = ViabilityRequest::factory()->create([
            'protocol_number' => null,
            'protocoled_at' => Carbon::parse($protocoladoEm),
        ]);

        $factory = ViabilityDecision::factory();

        if ($tvl === null) {
            $factory = $factory->indeferida();
        }

        $factory->create([
            'viability_request_id' => $r->id,
            'outcome' => $tvl === null ? DecisionOutcome::Indeferida : DecisionOutcome::Deferida,
            'tvl_product_number' => $tvl,
            'decided_at' => Carbon::parse($decididoEm),
        ]);
    }

    #[Test]
    public function tempo_de_emissao_de_tvl_em_tempo_util_so_conta_decisoes_com_tvl(): void
    {
        $this->decisaoTvl('2026-06-10 10:00', '2026-06-10 15:00', 'TVL-2026-000001'); // 300 (mesma quarta)
        $this->decisaoTvl('2026-06-09 09:00', '2026-06-09 10:00', 'TVL-2026-000002'); // 60 (mesma terça)

        // Ruído que NÃO conta (anti-fachada): indeferida sem TVL no período...
        $this->decisaoTvl('2026-06-10 09:00', '2026-06-10 16:00', null);
        // ...e um TVL DECIDIDO fora do período consultado.
        $this->decisaoTvl('2025-01-05 09:00', '2025-01-05 10:00', 'TVL-2025-000099');

        $resultado = $this->service()->tempoEmissaoTvl($this->junho());

        $this->assertSame(180, $resultado['media_minutos']); // (300+60)/2
        $this->assertSame(2, $resultado['amostras']);
    }

    #[Test]
    public function periodo_sem_dados_retorna_vazio_honesto_sem_numero_inventado(): void
    {
        // Dados de junho/2026 existem...
        $b = $this->processo('2026-06-09 08:00', '2026-06-09 08:40');
        $this->transicao($b, ViabilityRequestStatus::Rascunho, ViabilityRequestStatus::Protocolada, '2026-06-09 08:40');
        $this->transicao($b, ViabilityRequestStatus::Protocolada, ViabilityRequestStatus::EmAnalise, '2026-06-09 10:40');
        $this->decisaoTvl('2026-06-09 09:00', '2026-06-09 10:00', 'TVL-2026-000010');

        // ...mas a consulta é de 2024 (período vazio).
        $vazio = ReportFilters::fromArray(['data_de' => '2024-01-01', 'data_ate' => '2024-12-31']);

        $etapas = $this->porEtapa($vazio);

        foreach (['preenchimento', 'espera', 'analise', 'pendencia'] as $etapa) {
            // CA-03: sem amostras → média NULL (honesto), nunca 0 disfarçado de real.
            $this->assertNull($etapas[$etapa]['media_minutos']);
            $this->assertSame(0, $etapas[$etapa]['amostras']);
        }

        $tvl = $this->service()->tempoEmissaoTvl($vazio);
        $this->assertNull($tvl['media_minutos']);
        $this->assertSame(0, $tvl['amostras']);
    }
}
