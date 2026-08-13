<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\TipoGatilho;
use App\Enums\ViabilityRequestStatus;
use App\Models\ExpressoQueda;
use App\Models\Parameter;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\Sources\ExpressoQuedaReportSource;
use App\Services\Relatorios\ExpressoQuedaService;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Relatório de quedas do expresso (HU-145): taxa de resposta expressa
 * (respondidas pelo expresso ÷ elegíveis), meta parametrizável (RN-004), série
 * temporal e ranking de motivos — tudo por AGREGAÇÃO SQL real sobre os dados das
 * Fases 9/10 e a captura do 15-07. Datasets cravados provam contagens exatas; a
 * degradação é honesta: taxa null sem base no período, meta null quando não
 * definida, motivo null rotulado 'não classificado' (CA-03 anti-fachada).
 */
class ExpressoQuedaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ExpressoQuedaService
    {
        return app(ExpressoQuedaService::class);
    }

    /**
     * Protocolada sem protocol_number fixo (nullable) — evita o único do
     * protocolo ao criar várias no mesmo teste.
     */
    private function protocolada(): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocoled_at' => now(),
        ]);
    }

    /**
     * Processo RESPONDIDO pelo expresso: transição protocolada→deferida (entrou
     * no expresso) + ViabilityDecision flow=expresso, decided_by_user_id null
     * (auto-decisão sem analista). created_at/decided_at no dia informado.
     */
    private function respondidaExpressa(string $em = 'now'): void
    {
        $req = $this->protocolada();
        $dia = Carbon::parse($em);

        $req->transitions()->create([
            'from_status' => ViabilityRequestStatus::Protocolada,
            'to_status' => ViabilityRequestStatus::Deferida,
        ])->forceFill(['created_at' => $dia])->save();

        ViabilityDecision::factory()->create([
            'viability_request_id' => $req->id,
            'flow' => 'expresso',
            'outcome' => DecisionOutcome::Deferida,
            'tvl_product_number' => null,
            'decided_by_user_id' => null,
            'decided_at' => $dia,
        ]);
    }

    /**
     * Processo que CAIU à análise: transição protocolada→em_analise (entrou no
     * expresso, elegível) SEM ViabilityDecision do expresso (não respondido).
     */
    private function quedaParaAnalise(string $em = 'now'): void
    {
        $req = $this->protocolada();

        $req->transitions()->create([
            'from_status' => ViabilityRequestStatus::Protocolada,
            'to_status' => ViabilityRequestStatus::EmAnalise,
        ])->forceFill(['created_at' => Carbon::parse($em)])->save();
    }

    #[Test]
    public function taxa_resposta_expressa_e_a_razao_real_respondidas_sobre_elegiveis(): void
    {
        // HU-145 RN-002: 6 respondidas pelo expresso ÷ 8 elegíveis (6 deferidas +
        // 2 caídas à análise) = 75.0%. A meta nasce null (não definida, RN-004).
        for ($i = 0; $i < 6; $i++) {
            $this->respondidaExpressa();
        }
        $this->quedaParaAnalise();
        $this->quedaParaAnalise();

        $resultado = $this->service()->taxaRespostaExpressa(ReportFilters::fromArray([]));

        $this->assertSame(6, $resultado['respondidas']);
        $this->assertSame(8, $resultado['elegiveis']);
        $this->assertSame(75.0, $resultado['taxa']);
        $this->assertNull($resultado['meta']);
    }

    #[Test]
    public function taxa_sem_base_no_periodo_e_null_nunca_zero_fabricado(): void
    {
        // CA-03 anti-fachada: há dados, mas FORA do período consultado → taxa null
        // ("sem base no período"), jamais um 0% que finge ter medido algo.
        $this->respondidaExpressa('2026-03-10');

        $resultado = $this->service()->taxaRespostaExpressa(ReportFilters::fromArray([
            'data_de' => '2026-06-01',
            'data_ate' => '2026-06-30',
        ]));

        $this->assertSame(0, $resultado['elegiveis']);
        $this->assertNull($resultado['taxa']);
    }

    #[Test]
    public function meta_reflete_o_parametro_e_e_null_quando_nao_definida(): void
    {
        // RN-004: a meta é parametrizável (HU-014) e NUNCA inventada. Sem o
        // parâmetro → null; definida via Settings (banco) → reflete sem deploy.
        $this->assertNull(
            $this->service()->taxaRespostaExpressa(ReportFilters::fromArray([]))['meta'],
        );

        Parameter::factory()->create([
            'key' => 'relatorios.expresso.meta_taxa',
            'group' => 'relatorios',
            'type' => 'string',
            'value' => '80',
        ]);

        $this->assertSame(
            80.0,
            $this->service()->taxaRespostaExpressa(ReportFilters::fromArray([]))['meta'],
        );
    }

    #[Test]
    public function ranking_motivos_agrupa_por_tipo_gatilho_e_rotula_null_como_nao_classificado(): void
    {
        // HU-145: ranking dos motivos da queda por gatilho (desc). tipo_gatilho
        // null (motor degradado / sem gatilho) é rotulado 'não classificado' —
        // honesto, nunca somado a um gatilho real.
        $req = $this->protocolada();

        ExpressoQueda::factory()->count(3)->create([
            'viability_request_id' => $req->id,
            'tipo_gatilho' => TipoGatilho::ZeisEspecial->value,
        ]);
        ExpressoQueda::factory()->create([
            'viability_request_id' => $req->id,
            'tipo_gatilho' => TipoGatilho::EnquadramentoAusente->value,
        ]);
        ExpressoQueda::factory()->count(2)->create([
            'viability_request_id' => $req->id,
            'tipo_gatilho' => null,
        ]);

        $ranking = $this->service()->rankingMotivos(ReportFilters::fromArray([]));

        $this->assertCount(3, $ranking);
        $this->assertSame(TipoGatilho::ZeisEspecial->value, $ranking[0]['tipo_gatilho']);
        $this->assertSame(3, $ranking[0]['total']);
        $this->assertSame('ZEIS especial', $ranking[0]['rotulo']);

        $naoClassificado = collect($ranking)->firstWhere('tipo_gatilho', null);
        $this->assertNotNull($naoClassificado);
        $this->assertSame(2, $naoClassificado['total']);
        $this->assertSame('não classificado', $naoClassificado['rotulo']);
    }

    #[Test]
    public function serie_temporal_da_taxa_por_dia_dentro_da_janela(): void
    {
        // HU-145: taxa de resposta expressa por dia (respondidas ÷ elegíveis do
        // dia), dentro da janela parametrizável, ordenada por dia.
        Carbon::setTestNow('2026-06-10 12:00:00');

        // Dia 09: 1 respondida (1 elegível) → 100.0
        $this->respondidaExpressa('2026-06-09 10:00:00');
        // Dia 10: 2 respondidas + 1 queda (3 elegíveis) → 66.7
        $this->respondidaExpressa('2026-06-10 09:00:00');
        $this->respondidaExpressa('2026-06-10 11:00:00');
        $this->quedaParaAnalise('2026-06-10 08:00:00');

        $serie = $this->service()->serieTemporal(ReportFilters::fromArray([]));

        $this->assertSame([
            ['dia' => '2026-06-09', 'respondidas' => 1, 'elegiveis' => 1, 'taxa' => 100.0],
            ['dia' => '2026-06-10', 'respondidas' => 2, 'elegiveis' => 3, 'taxa' => 66.7],
        ], $serie);

        Carbon::setTestNow();
    }

    #[Test]
    public function drill_down_recorta_processos_que_cairam_reusando_o_filtered(): void
    {
        // HU-145: o drill-down parte do conjunto de processos que CAÍRAM (têm
        // queda registrada), reusando o filtro completo do SAPS (ProcessoQueryService).
        $caido = $this->protocolada();
        ExpressoQueda::factory()->create(['viability_request_id' => $caido->id]);
        $naoCaido = $this->protocolada();

        $ids = $this->service()->drillDown(ReportFilters::fromArray([]))->pluck('id');

        $this->assertTrue($ids->contains($caido->id));
        $this->assertFalse($ids->contains($naoCaido->id));
    }

    #[Test]
    public function report_source_exporta_as_quedas_filtradas_com_colunas_corretas(): void
    {
        // HU-131 RN-005: o ExpressoQuedaReportSource exporta exatamente as quedas
        // do conjunto filtrado, sem PII direta (personalData=false).
        $req = $this->protocolada();
        ExpressoQueda::factory()->create([
            'viability_request_id' => $req->id,
            'cnae' => '4721102',
            'tipo_gatilho' => TipoGatilho::ZeisEspecial->value,
            'dimensao' => 'municipal',
            'motivo' => 'CNAE em ZEIS especial encaminhado à análise técnica',
        ]);

        $definition = (new ExpressoQuedaReportSource)->definition(ReportFilters::fromArray([]));

        $this->assertSame('exporta-quedas-expresso', $definition->event);
        $this->assertFalse($definition->personalData);

        $linhas = $definition->builder()->get();
        $this->assertCount(1, $linhas);

        $row = $definition->mapRow($linhas->first());
        $this->assertContains('4721102', $row);
        $this->assertContains('ZEIS especial', $row);
        $this->assertContains('municipal', $row);
    }
}
