<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\Export\Sources\SolicitacoesReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fonte das solicitações para o export transversal (HU-131/RN-005): o
 * SolicitacoesReportSource transforma o conjunto FILTRADO da consulta de
 * processos no ReportDefinition, reusando ProcessoQueryService::filtered via
 * ReportFilters::toProcessoFiltros — sem reimplementar o filtro. O bag carrega o
 * vocabulário COMPLETO das telas (não só os 7 campos do indicador), então um
 * filtro de processo (status/protocolo) recorta o export exatamente como a tela.
 */
class SolicitacoesReportSourceTest extends TestCase
{
    use RefreshDatabase;

    private function source(): SolicitacoesReportSource
    {
        return app(SolicitacoesReportSource::class);
    }

    #[Test]
    public function e_um_report_source_do_contrato(): void
    {
        $this->assertInstanceOf(ReportSource::class, $this->source());
    }

    #[Test]
    public function builder_traz_somente_as_linhas_do_filtro_de_processo_rn005(): void
    {
        $deferida = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocoled_at' => now(),
        ]);
        ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocoled_at' => now(),
        ]);

        // `status` está no vocabulário do ProcessoQueryService mas FORA dos 7
        // campos do indicador — prova que o bag carrega o vocabulário completo.
        $definicao = $this->source()->definition(ReportFilters::fromArray(['status' => 'deferida']));

        $linhas = $definicao->builder()->get();

        $this->assertCount(1, $linhas);
        $this->assertSame($deferida->id, $linhas->first()->id);
    }

    #[Test]
    public function map_row_devolve_as_colunas_na_ordem_da_definicao(): void
    {
        $solicitacao = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocoled_at' => now(),
            'address_neighborhood' => 'Pituba',
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $solicitacao->id,
            'outcome' => DecisionOutcome::Deferida,
            'tvl_product_number' => null,
            'decided_at' => now(),
        ]);

        $definicao = $this->source()->definition(ReportFilters::fromArray([]));

        // Reconstrói o modelo a partir do MESMO builder (com os eager-loads da
        // consulta de processos) para o mapRow.
        $modelo = $definicao->builder()->firstOrFail();
        $linha = $definicao->mapRow($modelo);

        // colunas: protocolo, empresa, cnpj, status, categoria, bairro, analista,
        // protocolado_em, decidido_em, resultado.
        $this->assertCount(10, $linha);
        $this->assertCount(10, $definicao->colunas);
        $this->assertSame('Deferida', $linha[3]);   // status_label
        $this->assertSame('Pituba', $linha[5]);     // bairro
        $this->assertSame('Deferida', $linha[9]);   // resultado da decisão
    }

    #[Test]
    public function definicao_marca_dado_pessoal_e_evento_de_auditoria_proprio(): void
    {
        $definicao = $this->source()->definition(ReportFilters::fromArray([]));

        $this->assertTrue($definicao->personalData);
        $this->assertSame('exporta-solicitacoes', $definicao->event);
        $this->assertSame('solicitacoes', $definicao->arquivoBase);
    }
}
