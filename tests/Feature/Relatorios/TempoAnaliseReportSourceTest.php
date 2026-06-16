<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\Export\Sources\EscritorioVirtualReportSource;
use App\Services\Relatorios\Export\Sources\TempoAnaliseReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Exportação dos relatórios de tempo (HU-129) e do SAPS Sedes de Escritório
 * Virtual (RN-006) pelo contrato único de 15-02 (RN-005): cada source transforma
 * o conjunto FILTRADO em ReportDefinition. O TempoAnaliseReportSource detalha os
 * minutos ÚTEIS por etapa de cada processo (sem PII — só protocolo e tempos); o
 * EscritorioVirtualReportSource recorta is_virtual_office=true.
 */
class TempoAnaliseReportSourceTest extends TestCase
{
    use RefreshDatabase;

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
     * Processo com o ciclo completo cravado (pendência cruza fim de semana).
     */
    private function processoCompleto(string $protocolo, string $protocoladoEm): ViabilityRequest
    {
        $r = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocol_number' => $protocolo,
            'created_at' => '2026-06-10 09:00',
            'protocoled_at' => Carbon::parse($protocoladoEm),
        ]);

        $this->transicao($r, ViabilityRequestStatus::Rascunho, ViabilityRequestStatus::Protocolada, '2026-06-10 10:00');   // preenchimento 60
        $this->transicao($r, ViabilityRequestStatus::Protocolada, ViabilityRequestStatus::EmAnalise, '2026-06-10 14:00');   // espera 240
        $this->transicao($r, ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::EmPendencia, '2026-06-10 17:00');   // análise 180
        $this->transicao($r, ViabilityRequestStatus::EmPendencia, ViabilityRequestStatus::EmAnalise, '2026-06-15 09:00');   // pendência 3840
        $this->transicao($r, ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::Deferida, '2026-06-15 11:00');      // análise 120

        return $r;
    }

    #[Test]
    public function ambos_os_sources_implementam_o_contrato(): void
    {
        $this->assertInstanceOf(ReportSource::class, app(TempoAnaliseReportSource::class));
        $this->assertInstanceOf(ReportSource::class, app(EscritorioVirtualReportSource::class));
    }

    #[Test]
    public function tempo_source_traz_so_o_periodo_e_detalha_minutos_uteis_por_etapa(): void
    {
        $dentro = $this->processoCompleto('VIA-2026-000777', '2026-06-10 10:00');

        // Fora do período consultado (protocolado em 2024) — RN-005 exclui.
        $fora = ViabilityRequest::factory()->create([
            'protocol_number' => null,
            'created_at' => '2024-03-01 09:00',
            'protocoled_at' => '2024-03-01 10:00',
        ]);
        $this->transicao($fora, ViabilityRequestStatus::Rascunho, ViabilityRequestStatus::Protocolada, '2024-03-01 10:00');

        $definicao = app(TempoAnaliseReportSource::class)
            ->definition(ReportFilters::fromArray(['data_de' => '2026-06-01', 'data_ate' => '2026-06-30']));

        $linhas = $definicao->builder()->get();
        $this->assertCount(1, $linhas);
        $this->assertSame($dentro->id, $linhas->first()->id);

        // colunas: protocolo, preenchimento, espera, analise, pendencia, total.
        $this->assertCount(6, $definicao->colunas);
        $linha = $definicao->mapRow($linhas->first());

        // Minutos ÚTEIS por etapa (a pendência cruza sáb/dom: 3840, não calendário).
        $this->assertSame(['VIA-2026-000777', 60, 240, 300, 3840, 4440], $linha);
    }

    #[Test]
    public function tempo_source_nao_marca_dado_pessoal_e_tem_evento_proprio(): void
    {
        $definicao = app(TempoAnaliseReportSource::class)->definition(ReportFilters::fromArray([]));

        // Só protocolo e tempos — sem PII.
        $this->assertFalse($definicao->personalData);
        $this->assertSame('exporta-tempo-analise', $definicao->event);
        $this->assertSame('tempo-por-etapa', $definicao->arquivoBase);
    }

    #[Test]
    public function escritorio_virtual_source_traz_so_is_virtual_office(): void
    {
        $virtual = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocol_number' => 'VIA-2026-000900',
            'protocoled_at' => Carbon::parse('2026-06-10 10:00'),
            'address_neighborhood' => 'Comércio',
            'is_virtual_office' => true,
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $virtual->id,
            'outcome' => DecisionOutcome::Deferida,
            'tvl_product_number' => 'TVL-2026-000900',
            'decided_at' => Carbon::parse('2026-06-10 12:00'),
        ]);

        // Não é sede de escritório virtual — fica de fora do recorte.
        ViabilityRequest::factory()->create([
            'protocol_number' => 'VIA-2026-000901',
            'protocoled_at' => Carbon::parse('2026-06-10 10:00'),
            'is_virtual_office' => false,
        ]);

        $definicao = app(EscritorioVirtualReportSource::class)->definition(ReportFilters::fromArray([]));

        $linhas = $definicao->builder()->get();
        $this->assertCount(1, $linhas);
        $this->assertSame($virtual->id, $linhas->first()->id);

        // colunas: protocolo, empresa, cnpj, bairro, protocolado_em, decidido_em, resultado.
        $this->assertCount(7, $definicao->colunas);
        $linha = $definicao->mapRow($linhas->first());
        $this->assertCount(7, $linha);
        $this->assertSame('VIA-2026-000900', $linha[0]);
        $this->assertSame('Comércio', $linha[3]);
        $this->assertSame('Deferida', $linha[6]);

        $this->assertTrue($definicao->personalData);
        $this->assertSame('exporta-escritorio-virtual', $definicao->event);
        $this->assertSame('sedes-escritorio-virtual', $definicao->arquivoBase);
    }
}
