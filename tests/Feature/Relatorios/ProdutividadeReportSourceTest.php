<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\Sources\ProdutividadeReportSource;
use App\Services\Relatorios\Export\SyncOnlyReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Exportação da produtividade por analista (HU-130/HU-131) com a MESMA
 * minimização do serviço: anônima por default (rótulo ordinal, sem identidade),
 * nominal só sob a flag/permissão (RN-007), escopo do próprio analista. O source
 * implementa {@see SyncOnlyReportSource} porque carrega ESTADO de construtor
 * (nominal/escopo) — o ReportExporter força o caminho síncrono e nunca despacha
 * o Job (que reconstruiria via `app($sourceClass)` e perderia o estado).
 */
class ProdutividadeReportSourceTest extends TestCase
{
    use RefreshDatabase;

    private function decisaoTecnica(User $analista, DecisionOutcome $outcome): void
    {
        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => null,
            'protocoled_at' => now(),
        ]);

        $factory = ViabilityDecision::factory();

        if ($outcome === DecisionOutcome::Indeferida) {
            $factory = $factory->indeferida();
        }

        $factory->create([
            'viability_request_id' => $request->id,
            'flow' => 'analise_tecnica',
            'outcome' => $outcome,
            'decided_by_user_id' => $analista->id,
            'decided_at' => now(),
            'tvl_product_number' => null,
        ]);
    }

    #[Test]
    public function o_source_e_sync_only_para_preservar_o_estado_de_construtor(): void
    {
        // Marcador SyncOnly garante o caminho síncrono no ReportExporter
        // (preserva nominal/escopo, que o Job perderia ao reconstruir pelo bag).
        $this->assertInstanceOf(SyncOnlyReportSource::class, new ProdutividadeReportSource);
    }

    #[Test]
    public function export_anonimo_por_default_nao_expoe_o_nome(): void
    {
        $definition = (new ProdutividadeReportSource)->definition(ReportFilters::fromArray([]));

        $chaves = array_column($definition->colunas, 'key');

        $this->assertContains('analista_rotulo', $chaves);
        $this->assertNotContains('analista_nome', $chaves);
        $this->assertFalse($definition->personalData);
        $this->assertSame('exporta-produtividade', $definition->event);
    }

    #[Test]
    public function export_anonimo_rotula_por_ordinal_de_volume_sobre_dado_real(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Analista']);
        $bruno = User::factory()->create(['name' => 'Bruno Analista']);

        // Ana: 3 (2 deferidas + 1 indeferida). Bruno: 1 indeferida.
        $this->decisaoTecnica($ana, DecisionOutcome::Deferida);
        $this->decisaoTecnica($ana, DecisionOutcome::Deferida);
        $this->decisaoTecnica($ana, DecisionOutcome::Indeferida);
        $this->decisaoTecnica($bruno, DecisionOutcome::Indeferida);

        $definition = (new ProdutividadeReportSource)->definition(ReportFilters::fromArray([]));

        // count() do exporter conta ANALISTAS (fromSub), não a contagem do 1º grupo.
        $this->assertSame(2, $definition->builder()->count());

        $linhas = $definition->builder()->get();
        $this->assertCount(2, $linhas);

        // Ordinal por volume desc: Ana (3) é "#1", Bruno (1) é "#2"; sem nome/id.
        $primeira = $definition->mapRow($linhas->first());
        $this->assertSame(['Analista #1', 3, 2, 1], $primeira);

        $segunda = $definition->mapRow($linhas->get(1));
        $this->assertSame(['Analista #2', 1, 0, 1], $segunda);
    }

    #[Test]
    public function export_nominal_expoe_o_nome_sob_a_flag(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Analista']);
        $bruno = User::factory()->create(['name' => 'Bruno Analista']);

        $this->decisaoTecnica($ana, DecisionOutcome::Deferida);
        $this->decisaoTecnica($ana, DecisionOutcome::Deferida);
        $this->decisaoTecnica($bruno, DecisionOutcome::Indeferida);

        $definition = (new ProdutividadeReportSource(nominal: true))->definition(ReportFilters::fromArray([]));

        $chaves = array_column($definition->colunas, 'key');
        $this->assertContains('analista_nome', $chaves);
        $this->assertNotContains('analista_rotulo', $chaves);
        // Nominal expõe identidade → herda a meta-auditoria de dado pessoal (RN-007).
        $this->assertTrue($definition->personalData);

        $primeira = $definition->mapRow($definition->builder()->get()->first());
        $this->assertSame(['Ana Analista', 2, 2, 0], $primeira);
    }

    #[Test]
    public function escopo_do_proprio_analista_traz_so_o_seu_recorte(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Analista']);
        $bruno = User::factory()->create(['name' => 'Bruno Analista']);

        $this->decisaoTecnica($ana, DecisionOutcome::Deferida);
        $this->decisaoTecnica($ana, DecisionOutcome::Deferida);
        $this->decisaoTecnica($bruno, DecisionOutcome::Indeferida);

        // nominal=true mas escopado → ignora nominal (é o próprio), anonimizado.
        $definition = (new ProdutividadeReportSource(nominal: true, scopeUserId: $bruno->id))
            ->definition(ReportFilters::fromArray([]));

        $this->assertSame(1, $definition->builder()->count());

        $primeira = $definition->mapRow($definition->builder()->get()->first());
        $this->assertSame(['Analista #1', 1, 0, 1], $primeira);

        $chaves = array_column($definition->colunas, 'key');
        $this->assertContains('analista_rotulo', $chaves);
        $this->assertFalse($definition->personalData);
    }
}
