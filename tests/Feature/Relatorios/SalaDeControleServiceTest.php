<?php

namespace Tests\Feature\Relatorios;

use App\Enums\AnalysisCategory;
use App\Enums\ConclusionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\BenchmarkIngestion;
use App\Models\BenchmarkMunicipalityMetric;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SalaDeControleService;
use App\Support\Capitais;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\MontaFluxosDeConclusao;
use Tests\TestCase;

/**
 * A Sala de Controle não calcula nada: compõe o que os indicadores já
 * produzem. O que ela precisa garantir é que a indisponibilidade de um bloco
 * não derrube os outros — o gestor continua vendo o que existe.
 */
class SalaDeControleServiceTest extends TestCase
{
    use LazilyRefreshDatabase;
    use MontaFluxosDeConclusao;

    private function service(): SalaDeControleService
    {
        return app(SalaDeControleService::class);
    }

    private function filtros(): ReportFilters
    {
        return ReportFilters::fromArray(['data_de' => '2026-03-01', 'data_ate' => '2026-03-31']);
    }

    private function processoDecidido(float $horas, AnalysisCategory $categoria = AnalysisCategory::Expresso): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse('2026-03-02 00:00:00'),
            'analysis_category' => $categoria,
        ]);

        $transicao = $request->transitions()->create([
            'from_status' => null,
            'to_status' => ViabilityRequestStatus::EmAnalise,
        ]);
        $transicao->forceFill(['created_at' => Carbon::parse('2026-03-02 00:00:00')])->save();

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'tvl_product_number' => fake()->unique()->numerify('TVL-2026-######'),
            'decided_at' => Carbon::parse('2026-03-03 00:00:00'),
        ]);

        $request->forceFill(['viabilidade_horas_uteis' => $horas])->save();
    }

    private function referenciaCompleta(): void
    {
        foreach (Capitais::ufs() as $uf) {
            BenchmarkIngestion::factory()->create(['uf' => $uf, 'ano' => 2026, 'mes' => 8]);
        }

        BenchmarkMunicipalityMetric::factory()->salvador()->create([
            'ano' => 2026, 'mes' => 8, 'media' => 20.0,
        ]);
    }

    public function test_entrega_os_cinco_blocos(): void
    {
        $this->processoDecidido(2.0);
        $this->referenciaCompleta();

        $painel = $this->service()->montar($this->filtros());

        $this->assertArrayHasKey('tempo', $painel);
        $this->assertArrayHasKey('operacao', $painel);
        $this->assertArrayHasKey('distancia', $painel);
        $this->assertArrayHasKey('evolucao', $painel);
        $this->assertArrayHasKey('gargalos', $painel);
    }

    public function test_bloco_de_tempo_traz_a_distribuicao_do_periodo(): void
    {
        $this->processoDecidido(2.0);
        $this->processoDecidido(6.0);

        $painel = $this->service()->montar($this->filtros());

        $this->assertSame(2, $painel['tempo']['n']);
        $this->assertEqualsWithDelta(4.0, $painel['tempo']['media'], 0.01);
    }

    public function test_bloco_de_operacao_traz_volume_e_taxa_de_automacao(): void
    {
        // Conclusões pelos serviços reais: duas do motor (uma disparada pela
        // integração, com usuário) e uma decisão do motor concluída pelo analista.
        Carbon::setTestNow('2026-03-10 12:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->cenarioZonaPermitida();

        $this->concluidaPeloMotor();
        $this->concluidaPeloMotor(User::factory()->create());
        $this->concluirAnalise($this->encaminhadaComDecisaoDoMotor(), User::factory()->analistaServidor()->create(), ConclusionOutcome::Deferido);
        $this->encaminhadaComDecisaoDoMotor();

        $operacao = $this->service()->montar($this->filtros())['operacao'];

        $this->assertSame(3, $operacao['total']);
        $this->assertSame(2, $operacao['automatica']);
        $this->assertSame(1, $operacao['semi_expressa']);
        $this->assertEqualsWithDelta(66.67, $operacao['taxa_automacao'], 0.01);
        $this->assertSame(2, $operacao['decisoes_do_motor']);
        $this->assertSame(1, $operacao['analises_humanas']);
    }

    public function test_ausencia_de_benchmark_nao_derruba_os_blocos_internos(): void
    {
        $this->processoDecidido(2.0);

        $painel = $this->service()->montar($this->filtros());

        $this->assertSame(1, $painel['tempo']['n']);
        $this->assertNotEmpty($painel['gargalos']);
        $this->assertNull($painel['distancia']['referencia']);
        $this->assertNotNull($painel['distancia']['motivo']);
        $this->assertSame([], $painel['evolucao']);
    }

    public function test_periodo_sem_processos_devolve_blocos_vazios_com_motivo(): void
    {
        $painel = $this->service()->montar($this->filtros());

        $this->assertSame(0, $painel['tempo']['n']);
        $this->assertSame(0, $painel['operacao']['total']);
        $this->assertNull($painel['operacao']['taxa_automacao']);
        $this->assertSame([], $painel['gargalos']);
        $this->assertNotNull($painel['distancia']['motivo']);
    }

    public function test_distancia_sempre_acompanha_a_ressalva(): void
    {
        $painel = $this->service()->montar($this->filtros());

        $this->assertNotEmpty($painel['distancia']['ressalva']);
    }

    public function test_le_o_tempo_do_periodo_uma_vez_so(): void
    {
        $this->processoDecidido(2.0);
        $this->referenciaCompleta();

        DB::enableQueryLog();
        $painel = $this->service()->montar($this->filtros());
        $leituras = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], '"viabilidade_horas_uteis" from'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $leituras);
        $this->assertSame($painel['tempo']['media'], $painel['distancia']['interno']);
    }
}
