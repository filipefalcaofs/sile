<?php

namespace Tests\Feature\Relatorios;

use App\Enums\AnalysisCategory;
use App\Enums\RiscoMunicipal;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
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
        // ela, rotular 'nao_classificado'. Cada linha declara a fonte com honestidade
        // de três níveis: real (classificação do Decreto) | derivada (categoria da
        // análise) | indefinida (sem nenhuma base — nunca um nível inventado).
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
        $this->assertSame('indefinida', $porNivel['nao_classificado']['fonte']);
    }
}
