<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\GeoBairroIndicadorService;
use App\Services\Relatorios\ReportFilters;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Painel territorial com a base oficial materializada (Onda GIS): a agregação
 * por zona urbanística oficial (zona_codigo gravado na identificação do
 * imóvel) e o bairro CANÔNICO (bairro_oficial) preferido ao digitado
 * (coalesce honesto — o digitado é o fallback, nunca descartado). Zona nula
 * não vira bucket fantasma: entra só no resumo (sem_zona).
 */
class GeoZonaIndicadorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function protocolada(string $protocolo, ?string $zona, ?string $bairroOficial = null, ?string $bairroDigitado = null): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'protocol_number' => $protocolo,
            'protocoled_at' => Carbon::parse('2026-06-10 10:00'),
            'zona_codigo' => $zona,
            'bairro_oficial' => $bairroOficial,
            'address_neighborhood' => $bairroDigitado,
        ]);
    }

    private function decidida(ViabilityRequest $r, DecisionOutcome $outcome): void
    {
        ViabilityDecision::factory()->create([
            'viability_request_id' => $r->id,
            'outcome' => $outcome,
            // A factory crava um nº fixo — aqui cada decisão precisa do seu.
            'tvl_product_number' => 'TVL-2026-'.$r->id,
            'decided_at' => Carbon::parse('2026-06-12 10:00'),
        ]);
    }

    public function test_por_zona_agrega_pela_zona_oficial_com_taxa_de_deferimento(): void
    {
        $a = $this->protocolada('VIA-2026-000600', 'ZPR-1');
        $b = $this->protocolada('VIA-2026-000601', 'ZPR-1');
        $c = $this->protocolada('VIA-2026-000602', 'ZPR-2');
        $this->protocolada('VIA-2026-000603', null); // sem zona — só no resumo

        $this->decidida($a, DecisionOutcome::Deferida);
        $this->decidida($b, DecisionOutcome::Indeferida);
        $this->decidida($c, DecisionOutcome::Deferida);

        $resultado = app(GeoBairroIndicadorService::class)->porZona(ReportFilters::fromArray([]));

        // 3 processos com zona (2× ZPR-1 + 1× ZPR-2) e 1 sem (só no resumo).
        $this->assertSame(3, $resultado['resumo']['com_zona']);
        $this->assertSame(1, $resultado['resumo']['sem_zona']);

        $this->assertCount(2, $resultado['itens']);

        $zpr1 = collect($resultado['itens'])->firstWhere('zona', 'ZPR-1');
        $this->assertSame(2, $zpr1['total']);
        $this->assertSame(1, $zpr1['deferidas']);
        $this->assertSame(1, $zpr1['indeferidas']);
        $this->assertSame(50.0, $zpr1['taxa_deferimento']);

        $zpr2 = collect($resultado['itens'])->firstWhere('zona', 'ZPR-2');
        $this->assertSame(100.0, $zpr2['taxa_deferimento']);
    }

    public function test_por_bairro_prefere_o_bairro_oficial_materializado(): void
    {
        // Mesmo bairro oficial com grafias digitadas diferentes — agrega no
        // canônico (o digitado é só fallback quando não há oficial).
        $this->protocolada('VIA-2026-000610', null, 'Pituba', 'PITUBA');
        $this->protocolada('VIA-2026-000611', null, 'Pituba', 'pituba');
        $this->protocolada('VIA-2026-000612', null, null, 'Comércio');

        $itens = app(GeoBairroIndicadorService::class)->porBairro(ReportFilters::fromArray([]))['itens'];

        $pituba = collect($itens)->firstWhere('bairro', 'Pituba');
        $this->assertSame(2, $pituba['total']);

        $comercio = collect($itens)->firstWhere('bairro', 'Comércio');
        $this->assertSame(1, $comercio['total']);
    }
}
