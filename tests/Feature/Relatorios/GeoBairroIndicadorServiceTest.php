<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\GeoBairroIndicadorService;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Painel geoeconômico por bairro (Geo BI interno — Módulo 1): agregação SQL real
 * das solicitações PROTOCOLADAS por bairro, com deferidas/indeferidas e taxa de
 * deferimento. Degradação HONESTA: a zona urbanística oficial (Quadro LOUOS/GIS)
 * está pendente na SEDUR, então o recorte é por `address_neighborhood`; sem
 * bairro informado a solicitação NÃO vira bucket fantasma (entra só no resumo).
 */
class GeoBairroIndicadorServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function service(): GeoBairroIndicadorService
    {
        return app(GeoBairroIndicadorService::class);
    }

    private function filtros(array $bag = []): ReportFilters
    {
        return ReportFilters::fromArray($bag);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function protocolada(array $attrs = []): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ], $attrs));
    }

    private function decisao(ViabilityRequest $request, DecisionOutcome $outcome): void
    {
        $factory = ViabilityDecision::factory();

        if ($outcome === DecisionOutcome::Indeferida) {
            $factory = $factory->indeferida();
        }

        $factory->create([
            'viability_request_id' => $request->id,
            'outcome' => $outcome,
            'decided_at' => now(),
            'tvl_product_number' => null,
        ]);
    }

    public function test_por_bairro_agrega_total_e_decisoes_com_taxa(): void
    {
        $pituba1 = $this->protocolada(['address_neighborhood' => 'Pituba']);
        $pituba2 = $this->protocolada(['address_neighborhood' => 'Pituba']);
        $barra = $this->protocolada(['address_neighborhood' => 'Barra']);

        $this->decisao($pituba1, DecisionOutcome::Deferida);
        $this->decisao($pituba2, DecisionOutcome::Indeferida);
        $this->decisao($barra, DecisionOutcome::Deferida);

        $resultado = $this->service()->porBairro($this->filtros());

        $this->assertSame('bairro', $resultado['degradacao']);

        $pituba = collect($resultado['itens'])->firstWhere('bairro', 'Pituba');
        $this->assertSame(2, $pituba['total']);
        $this->assertSame(1, $pituba['deferidas']);
        $this->assertSame(1, $pituba['indeferidas']);
        $this->assertSame(50.0, $pituba['taxa_deferimento']);

        $barraRow = collect($resultado['itens'])->firstWhere('bairro', 'Barra');
        $this->assertSame(1, $barraRow['total']);
        $this->assertSame(100.0, $barraRow['taxa_deferimento']);
    }

    public function test_por_bairro_taxa_null_sem_decisoes(): void
    {
        $this->protocolada(['address_neighborhood' => 'Itapuã']);

        $resultado = $this->service()->porBairro($this->filtros());
        $itapua = collect($resultado['itens'])->firstWhere('bairro', 'Itapuã');

        $this->assertSame(1, $itapua['total']);
        $this->assertSame(0, $itapua['deferidas']);
        $this->assertNull($itapua['taxa_deferimento']);
    }

    public function test_resumo_conta_bairros_distintos_e_sem_bairro(): void
    {
        $this->protocolada(['address_neighborhood' => 'Pituba']);
        $this->protocolada(['address_neighborhood' => 'Pituba']);
        $this->protocolada(['address_neighborhood' => 'Barra']);
        $this->protocolada(['address_neighborhood' => null]);

        $resumo = $this->service()->resumo($this->filtros());

        $this->assertSame(4, $resumo['total']);
        $this->assertSame(2, $resumo['bairros_distintos']);
        $this->assertSame(1, $resumo['sem_bairro']);
    }

    public function test_solicitacao_sem_bairro_nao_vira_linha_fantasma(): void
    {
        $this->protocolada(['address_neighborhood' => null]);

        $resultado = $this->service()->porBairro($this->filtros());

        $this->assertSame([], $resultado['itens']);
    }

    public function test_rascunho_nao_entra_na_agregacao(): void
    {
        // Rascunho (sem protocoled_at) não é submissão: fica fora das métricas.
        ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Rascunho,
            'protocol_number' => null,
            'protocoled_at' => null,
            'address_neighborhood' => 'Pituba',
        ]);

        $resumo = $this->service()->resumo($this->filtros());
        $this->assertSame(0, $resumo['total']);
    }
}
