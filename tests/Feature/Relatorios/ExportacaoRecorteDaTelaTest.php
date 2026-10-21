<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\Sources\GeoBairroReportSource;
use App\Services\Relatorios\Export\Sources\IndicadoresReportSource;
use App\Services\Relatorios\Export\Sources\SaturacaoReportSource;
use App\Services\Relatorios\GeoBairroIndicadorService;
use App\Services\Relatorios\IndicadoresViabilidadeService;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SaturacaoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A exportação de Indicadores, Geo/bairro e Saturação traz as mesmas
 * solicitações que a tela conta (RN-005): só protocoladas, CNAE pelo código
 * sem pontuação e bairro oficial com o digitado de reserva.
 */
class ExportacaoRecorteDaTelaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['viabiliza.relatorios.saturacao.capacidades' => ['4712100' => 2]]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function solicitacao(array $attrs = [], ?Cnae $cnae = null, ?DecisionOutcome $decisao = null): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now(),
        ], $attrs));

        if ($cnae !== null) {
            $request->cnaes()->attach($cnae->id, ['is_primary' => true]);
        }

        if ($decisao !== null) {
            $factory = ViabilityDecision::factory();

            if ($decisao === DecisionOutcome::Indeferida) {
                $factory = $factory->indeferida();
            }

            $factory->create([
                'viability_request_id' => $request->id,
                'outcome' => $decisao,
                'decided_at' => now(),
                'tvl_product_number' => null,
            ]);
        }

        return $request;
    }

    /**
     * @return list<int>
     */
    private function idsExportados(object $source, ReportFilters $filtros): array
    {
        return $source->definition($filtros)->builder()->pluck('viability_requests.id')->sort()->values()->all();
    }

    public function test_indicadores_exporta_so_protocoladas_mesmo_sem_periodo(): void
    {
        $this->solicitacao();
        $this->solicitacao(['status' => ViabilityRequestStatus::Cancelada]);
        $this->solicitacao(['status' => ViabilityRequestStatus::Rascunho, 'protocol_number' => null, 'protocoled_at' => null]);

        $filtros = ReportFilters::fromArray([]);
        $volume = array_sum(array_column(app(IndicadoresViabilidadeService::class)->porPeriodo($filtros), 'total'));

        $this->assertCount($volume, $this->idsExportados(app(IndicadoresReportSource::class), $filtros));
        $this->assertCount(2, $this->idsExportados(app(IndicadoresReportSource::class), $filtros));
    }

    public function test_indicadores_exporta_pelo_mesmo_cnae_da_tela(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);
        $prefixoParecido = Cnae::factory()->create(['code' => '4712200']);

        $alvo = $this->solicitacao([], $comercio);
        $this->solicitacao([], $prefixoParecido);

        $filtros = ReportFilters::fromArray(['cnae' => '4712-1/00']);

        $this->assertSame([$alvo->id], $this->idsExportados(app(IndicadoresReportSource::class), $filtros));
    }

    public function test_geo_exporta_o_que_o_resumo_conta_pelo_bairro_oficial(): void
    {
        $oficial = $this->solicitacao(['bairro_oficial' => ' Pituba', 'address_neighborhood' => 'Pitubinha']);
        $this->solicitacao(['bairro_oficial' => null, 'address_neighborhood' => 'Barra']);
        $this->solicitacao(['status' => ViabilityRequestStatus::Rascunho, 'protocol_number' => null, 'protocoled_at' => null, 'address_neighborhood' => 'Pituba']);

        $filtros = ReportFilters::fromArray(['bairro' => 'pituba']);
        $total = app(GeoBairroIndicadorService::class)->resumo($filtros)['total'];
        $source = app(GeoBairroReportSource::class);

        $this->assertSame([$oficial->id], $this->idsExportados($source, $filtros));
        $this->assertCount($total, $this->idsExportados($source, $filtros));

        $linha = ($source->definition($filtros)->mapRow)($source->definition($filtros)->builder()->first());
        $this->assertContains('Pituba', $linha);
    }

    public function test_saturacao_exporta_os_deferidos_que_formam_os_pares(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);
        $outro = Cnae::factory()->create(['code' => '5611201']);

        $deferido = $this->solicitacao(['address_neighborhood' => 'Pituba'], $comercio, DecisionOutcome::Deferida);
        $this->solicitacao(['address_neighborhood' => 'Pituba'], $comercio, DecisionOutcome::Indeferida);
        $this->solicitacao(['address_neighborhood' => 'Pituba'], $comercio);
        $this->solicitacao(['address_neighborhood' => 'Pituba'], $outro, DecisionOutcome::Deferida);
        $this->solicitacao(['address_neighborhood' => null], $comercio, DecisionOutcome::Deferida);

        $filtros = ReportFilters::fromArray(['cnae' => '4712100']);
        $ativos = array_sum(array_column(app(SaturacaoService::class)->porBairroCnae($filtros), 'ativos'));

        $this->assertSame([$deferido->id], $this->idsExportados(app(SaturacaoReportSource::class), $filtros));
        $this->assertSame(1, $ativos);
    }

    public function test_telas_exportam_pela_fonte_do_proprio_recorte(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();

        $this->solicitacao(['status' => ViabilityRequestStatus::Rascunho, 'protocol_number' => 'VIA-RASCUNHO-1', 'protocoled_at' => null, 'address_neighborhood' => 'Pituba']);

        foreach (['indicadores', 'geo-bairro', 'saturacao'] as $tela) {
            $conteudo = $this->actingAs($gestor, 'gestao')
                ->get("/gestao/relatorios/{$tela}?formato=csv")
                ->assertOk()
                ->streamedContent();

            $this->assertStringNotContainsString('VIA-RASCUNHO-1', $conteudo, $tela);
        }
    }
}
