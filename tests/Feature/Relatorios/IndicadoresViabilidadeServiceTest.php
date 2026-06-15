<?php

namespace Tests\Feature\Relatorios;

use App\Enums\AnalysisCategory;
use App\Enums\DecisionOutcome;
use App\Enums\RiscoMunicipal;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\IndicadoresViabilidadeService;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Indicadores de solicitações (HU-123 período, HU-124 zona→bairro, HU-125 CNAE,
 * HU-126 risco) calculados por AGREGAÇÃO SQL real sobre os dados das Fases 8–10.
 * Datasets factory com valores CRAVADOS provam contagens exatas; a degradação é
 * honesta (zona indisponível → bairro com rótulo; sem dados → lista vazia, nunca
 * número inventado — CA-03 anti-fachada).
 */
class IndicadoresViabilidadeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): IndicadoresViabilidadeService
    {
        return app(IndicadoresViabilidadeService::class);
    }

    /**
     * Solicitação protocolada com atributos cravados (a base dos indicadores é o
     * conjunto protocolado, nunca o rascunho).
     *
     * @param  array<string, mixed>  $attrs
     */
    private function protocolada(array $attrs = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocoled_at' => now(),
        ], $attrs));
    }

    /**
     * Decisão vinculante cravada (HU-127/128). Cada decisão nasce sobre a sua
     * própria solicitação protocolada para não colidir no protocolo/TVL únicos.
     */
    private function decisao(DecisionOutcome $outcome, string $decididaEm): void
    {
        $solicitacao = $this->protocolada(['protocoled_at' => Carbon::parse($decididaEm)->subDays(2)]);

        ViabilityDecision::factory()->create([
            'viability_request_id' => $solicitacao->id,
            'outcome' => $outcome,
            'tvl_product_number' => null,
            'decided_at' => Carbon::parse($decididaEm),
        ]);
    }

    #[Test]
    public function por_periodo_conta_protocoladas_por_dia_no_intervalo(): void
    {
        $this->protocolada(['protocoled_at' => Carbon::parse('2026-03-10 09:00:00')]);
        $this->protocolada(['protocoled_at' => Carbon::parse('2026-03-10 16:00:00')]);
        $this->protocolada(['protocoled_at' => Carbon::parse('2026-03-11 10:00:00')]);
        // Fora do intervalo (não deve contar).
        $this->protocolada(['protocoled_at' => Carbon::parse('2026-04-02 10:00:00')]);
        // Rascunho sem protocolo (não é solicitação protocolada).
        ViabilityRequest::factory()->create(['status' => ViabilityRequestStatus::Rascunho, 'protocoled_at' => null]);

        $serie = $this->service()->porPeriodo(ReportFilters::fromArray([
            'data_de' => '2026-03-01',
            'data_ate' => '2026-03-31',
        ]));

        $this->assertSame([
            ['dia' => '2026-03-10', 'total' => 2],
            ['dia' => '2026-03-11', 'total' => 1],
        ], $serie);
    }

    #[Test]
    public function por_periodo_sem_dados_no_intervalo_retorna_lista_vazia(): void
    {
        // CA-03 anti-fachada: dados existem, mas FORA do período consultado →
        // lista vazia honesta, nunca um número fabricado.
        $this->protocolada(['protocoled_at' => Carbon::parse('2026-03-10 09:00:00')]);

        $serie = $this->service()->porPeriodo(ReportFilters::fromArray([
            'data_de' => '2026-05-01',
            'data_ate' => '2026-05-31',
        ]));

        $this->assertSame([], $serie);
    }

    #[Test]
    public function por_periodo_respeita_o_filtro_de_categoria(): void
    {
        $this->protocolada(['analysis_category' => AnalysisCategory::Expresso, 'protocoled_at' => Carbon::parse('2026-03-10')]);
        $this->protocolada(['analysis_category' => AnalysisCategory::Expresso, 'protocoled_at' => Carbon::parse('2026-03-10')]);
        $this->protocolada(['analysis_category' => AnalysisCategory::SemiExpresso, 'protocoled_at' => Carbon::parse('2026-03-10')]);

        $serie = $this->service()->porPeriodo(ReportFilters::fromArray(['categoria' => 'expresso']));

        $this->assertSame([['dia' => '2026-03-10', 'total' => 2]], $serie);
    }

    #[Test]
    public function por_zona_degrada_para_bairro_com_rotulo_honesto(): void
    {
        // HU-124: a zona urbanística oficial está bloqueada (SEDUR/GIS pendente).
        // O indicador degrada para BAIRRO com rótulo explícito — nunca inventa zona.
        $this->protocolada(['address_neighborhood' => 'Pituba']);
        $this->protocolada(['address_neighborhood' => 'Pituba']);
        $this->protocolada(['address_neighborhood' => 'Itapuã']);

        $resultado = $this->service()->porZona(ReportFilters::fromArray([]));

        $this->assertSame('bairro', $resultado['degradacao']);
        $this->assertStringContainsString('Zona urbanística indisponível', $resultado['rotulo']);
        $this->assertSame([
            ['bairro' => 'Pituba', 'total' => 2],
            ['bairro' => 'Itapuã', 'total' => 1],
        ], $resultado['itens']);
    }

    #[Test]
    public function por_cnae_conta_processos_distintos_por_cnae(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);
        $restaurante = Cnae::factory()->create(['code' => '5611201']);

        $this->protocolada()->cnaes()->attach($comercio->id, ['is_primary' => true]);
        $this->protocolada()->cnaes()->attach($comercio->id, ['is_primary' => true]);
        $this->protocolada()->cnaes()->attach($restaurante->id, ['is_primary' => true]);

        $resultado = $this->service()->porCnae(ReportFilters::fromArray([]));

        $this->assertSame([
            ['cnae' => '4712100', 'total' => 2],
            ['cnae' => '5611201', 'total' => 1],
        ], $resultado);
    }

    #[Test]
    public function por_risco_usa_o_nivel_real_e_degrada_para_categoria_e_nao_classificado(): void
    {
        // HU-126: preferir o risco MUNICIPAL real (Decreto 32.636/2020) pelo CNAE
        // principal; sem classificação, cair na categoria derivada da Fase 10; sem
        // ela, rotular 'nao_classificado'. Cada linha declara a fonte: 'real' (nível
        // oficial do Decreto) vs 'derivada' (inferido da categoria ou ausente, jamais
        // da tabela oficial) — nunca um nível inventado.
        $versao = RuleVersion::factory()->create();

        $cnaeAlto = Cnae::factory()->create(['code' => '1111111']);
        $cnaeBaixo = Cnae::factory()->create(['code' => '2222222']);
        $cnaeSemClass = Cnae::factory()->create(['code' => '3333333']);

        RiskClassification::factory()->create(['rule_version_id' => $versao->id, 'cnae_code' => '1111111', 'risco_municipal' => RiscoMunicipal::Alto]);
        RiskClassification::factory()->create(['rule_version_id' => $versao->id, 'cnae_code' => '2222222', 'risco_municipal' => RiscoMunicipal::BaixoA]);

        $this->protocolada()->cnaes()->attach($cnaeAlto->id, ['is_primary' => true]);
        $this->protocolada()->cnaes()->attach($cnaeBaixo->id, ['is_primary' => true]);
        $this->protocolada(['analysis_category' => AnalysisCategory::Expresso])->cnaes()->attach($cnaeSemClass->id, ['is_primary' => true]);
        $this->protocolada(['analysis_category' => null])->cnaes()->attach($cnaeSemClass->id, ['is_primary' => true]);

        $resultado = $this->service()->porRisco(ReportFilters::fromArray([]));

        $porNivel = collect($resultado)->keyBy('nivel');

        $this->assertSame(1, $porNivel['alto']['total']);
        $this->assertSame('real', $porNivel['alto']['fonte']);
        $this->assertSame(1, $porNivel['baixo_a']['total']);
        $this->assertSame('real', $porNivel['baixo_a']['fonte']);
        $this->assertSame(1, $porNivel['expresso']['total']);
        $this->assertSame('derivada', $porNivel['expresso']['fonte']);
        $this->assertSame(1, $porNivel['nao_classificado']['total']);
        $this->assertSame('derivada', $porNivel['nao_classificado']['fonte']);
    }

    #[Test]
    public function taxa_de_deferimento_e_indeferimento_sao_razoes_reais_no_periodo(): void
    {
        // HU-127/128: 3 deferidas + 1 indeferida decididas no período → 75.0% e
        // 25.0%. Cada uma é sua própria razão sobre as decididas (não somam 100
        // artificialmente — em_analise não vira decisão).
        $this->decisao(DecisionOutcome::Deferida, '2026-03-05');
        $this->decisao(DecisionOutcome::Deferida, '2026-03-06');
        $this->decisao(DecisionOutcome::Deferida, '2026-03-07');
        $this->decisao(DecisionOutcome::Indeferida, '2026-03-08');
        // Decidida FORA do período (não conta).
        $this->decisao(DecisionOutcome::Deferida, '2026-04-10');

        $filtros = ReportFilters::fromArray(['data_de' => '2026-03-01', 'data_ate' => '2026-03-31']);

        $deferimento = $this->service()->taxaDeferimento($filtros);
        $this->assertSame(3, $deferimento['deferidas']);
        $this->assertSame(4, $deferimento['total']);
        $this->assertSame(75.0, $deferimento['taxa']);

        $indeferimento = $this->service()->taxaIndeferimento($filtros);
        $this->assertSame(1, $indeferimento['indeferidas']);
        $this->assertSame(4, $indeferimento['total']);
        $this->assertSame(25.0, $indeferimento['taxa']);
    }

    #[Test]
    public function taxa_sem_decisoes_no_periodo_e_null_nunca_zero_fabricado(): void
    {
        // CA-03 anti-fachada: período sem nenhuma decisão → taxa null ("sem
        // decisões no período"), jamais um 0% que finge ter medido algo.
        $this->decisao(DecisionOutcome::Deferida, '2026-03-05');

        $filtros = ReportFilters::fromArray(['data_de' => '2026-06-01', 'data_ate' => '2026-06-30']);

        $deferimento = $this->service()->taxaDeferimento($filtros);
        $this->assertSame(0, $deferimento['total']);
        $this->assertNull($deferimento['taxa']);

        $this->assertNull($this->service()->taxaIndeferimento($filtros)['taxa']);
    }
}
