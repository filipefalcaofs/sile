<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\ProdutividadeAnalistaService;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Produtividade por analista (HU-130) sobre DADO REAL, em modo conservador por
 * sensibilidade RH/LGPD: a contagem é agregação SQL real das decisões de análise
 * técnica (viability_decisions.flow='analise_tecnica' por decided_by_user_id) e
 * dos processos atribuídos (viability_requests.assigned_user_id). O default é
 * AGREGADO/ANONIMIZADO (rótulo ordinal, sem identidade); a visão NOMINAL é um
 * parâmetro liberado só sob permissão (gate no HTTP de 15-09); o escopo do
 * próprio analista devolve só o recorte dele. Sem decisões no período → vazio
 * honesto, NUNCA produtividade inventada (CA-03 anti-fachada).
 */
class ProdutividadeAnalistaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProdutividadeAnalistaService
    {
        return new ProdutividadeAnalistaService;
    }

    /**
     * Decisão de análise técnica cravada para um analista (flow analise_tecnica,
     * decided_by = analista). protocol_number/tvl nulos para não colidir com os
     * uniques constantes das factories ao criar várias decisões.
     */
    private function decisaoTecnica(User $analista, DecisionOutcome $outcome, ?Carbon $quando = null): void
    {
        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => null,
            'protocoled_at' => $quando ?? now(),
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
            'decided_at' => $quando ?? now(),
            'tvl_product_number' => null,
        ]);
    }

    /**
     * Processos atribuídos ao analista (assigned_user_id) no período.
     */
    private function atribuir(User $analista, int $quantidade, ?Carbon $quando = null): void
    {
        ViabilityRequest::factory()->count($quantidade)->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => null,
            'protocoled_at' => $quando ?? now(),
            'assigned_user_id' => $analista->id,
        ]);
    }

    #[Test]
    public function conta_decisoes_exatas_por_analista_no_modo_agregado_e_anonimizado(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Analista']);
        $bruno = User::factory()->create(['name' => 'Bruno Analista']);

        // Ana: 3 decisões técnicas (2 deferidas + 1 indeferida).
        $this->decisaoTecnica($ana, DecisionOutcome::Deferida);
        $this->decisaoTecnica($ana, DecisionOutcome::Deferida);
        $this->decisaoTecnica($ana, DecisionOutcome::Indeferida);

        // Bruno: 2 decisões técnicas (1 deferida + 1 indeferida).
        $this->decisaoTecnica($bruno, DecisionOutcome::Deferida);
        $this->decisaoTecnica($bruno, DecisionOutcome::Indeferida);

        // Processos atribuídos (assigned_user_id) — métrica complementar real.
        $this->atribuir($ana, 4);
        $this->atribuir($bruno, 2);

        // RUÍDO que NÃO pode contar (prova a agregação real, anti-fachada):
        // decisão automática do expresso (decided_by null)...
        ViabilityDecision::factory()->create([
            'viability_request_id' => ViabilityRequest::factory()->create(['protocol_number' => null])->id,
            'flow' => 'expresso',
            'decided_by_user_id' => null,
            'tvl_product_number' => null,
        ]);
        // ...e uma decisão da Ana em OUTRO fluxo (expresso) — só análise técnica conta.
        ViabilityDecision::factory()->create([
            'viability_request_id' => ViabilityRequest::factory()->create(['protocol_number' => null])->id,
            'flow' => 'expresso',
            'decided_by_user_id' => $ana->id,
            'tvl_product_number' => null,
        ]);

        $linhas = $this->service()->porAnalista(ReportFilters::fromArray([]));

        $this->assertCount(2, $linhas);

        // Ordenado por volume desc → Ana (3) é "Analista #1", Bruno (2) é "#2".
        $this->assertSame('Analista #1', $linhas[0]['analista_rotulo']);
        $this->assertSame(3, $linhas[0]['decididas']);
        $this->assertSame(2, $linhas[0]['deferidas']);
        $this->assertSame(1, $linhas[0]['indeferidas']);
        $this->assertSame(4, $linhas[0]['atribuidas']);

        $this->assertSame('Analista #2', $linhas[1]['analista_rotulo']);
        $this->assertSame(2, $linhas[1]['decididas']);
        $this->assertSame(1, $linhas[1]['deferidas']);
        $this->assertSame(1, $linhas[1]['indeferidas']);
        $this->assertSame(2, $linhas[1]['atribuidas']);

        // Anonimização (RN-007): nenhuma linha expõe nome ou id do analista.
        $this->assertArrayNotHasKey('nome', $linhas[0]);
        $this->assertArrayNotHasKey('analista_id', $linhas[0]);
    }

    #[Test]
    public function modo_nominal_inclui_o_nome_do_analista(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Analista']);
        $bruno = User::factory()->create(['name' => 'Bruno Analista']);

        $this->decisaoTecnica($ana, DecisionOutcome::Deferida);
        $this->decisaoTecnica($ana, DecisionOutcome::Deferida);
        $this->decisaoTecnica($bruno, DecisionOutcome::Indeferida);

        $linhas = $this->service()->porAnalista(ReportFilters::fromArray([]), nominal: true);

        $this->assertCount(2, $linhas);
        $this->assertSame('Ana Analista', $linhas[0]['nome']);
        $this->assertSame($ana->id, $linhas[0]['analista_id']);
        $this->assertSame(2, $linhas[0]['decididas']);
        $this->assertSame('Bruno Analista', $linhas[1]['nome']);

        // Modo nominal não usa o rótulo anônimo.
        $this->assertArrayNotHasKey('analista_rotulo', $linhas[0]);
    }

    #[Test]
    public function escopo_do_proprio_analista_retorna_so_o_seu_recorte(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Analista']);
        $bruno = User::factory()->create(['name' => 'Bruno Analista']);

        $this->decisaoTecnica($ana, DecisionOutcome::Deferida);
        $this->decisaoTecnica($ana, DecisionOutcome::Deferida);
        $this->decisaoTecnica($bruno, DecisionOutcome::Deferida);
        $this->decisaoTecnica($bruno, DecisionOutcome::Indeferida);

        // O analista vê só o próprio recorte; o nominal é ignorado (é o próprio).
        $linhas = $this->service()->porAnalista(
            ReportFilters::fromArray([]),
            nominal: true,
            scopeUserId: $bruno->id,
        );

        $this->assertCount(1, $linhas);
        $this->assertSame(2, $linhas[0]['decididas']);
        $this->assertSame(1, $linhas[0]['deferidas']);
        $this->assertSame(1, $linhas[0]['indeferidas']);
        // Escopo do próprio ignora nominal: continua anonimizado (sem nome).
        $this->assertArrayNotHasKey('nome', $linhas[0]);
        $this->assertSame('Analista #1', $linhas[0]['analista_rotulo']);
    }

    #[Test]
    public function periodo_sem_decisoes_retorna_vazio_honesto(): void
    {
        $ana = User::factory()->create();

        // Decisão de HOJE — fora do período consultado (ano passado).
        $this->decisaoTecnica($ana, DecisionOutcome::Deferida, now());

        $linhas = $this->service()->porAnalista(ReportFilters::fromArray([
            'data_de' => now()->subYears(2)->format('Y-m-d'),
            'data_ate' => now()->subYear()->format('Y-m-d'),
        ]));

        // CA-03: sem decisões no período → vazio, NUNCA produtividade inventada.
        $this->assertSame([], $linhas);
    }
}
