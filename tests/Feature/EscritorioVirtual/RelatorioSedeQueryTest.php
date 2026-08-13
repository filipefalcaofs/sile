<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\Company;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\Relatorios\RelatorioSedeEscritorioVirtualService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Relatório sede × abrigados de escritório virtual (Plano R1 — Task 1). A
 * consulta agrupa, por inscrição imobiliária travada, a SEDE (o alvo do lock
 * ativo) e os ABRIGADOS (decisões com is_virtual_office_tenant=true na mesma
 * inscrição). Filtra pelo nº do produto TVL da sede OU pela inscrição, e devolve
 * um paginator cujas linhas são mapeadas por `linha()`.
 */
class RelatorioSedeQueryTest extends TestCase
{
    use RefreshDatabase;

    private ViabilityRequest $sede;

    /** @var array<int, ViabilityRequest> */
    private array $abrigados = [];

    protected function setUp(): void
    {
        parent::setUp();

        // SEDE: deferida com TVL, marca hq, e trava a inscrição '111'.
        $sedeCompany = Company::factory()->create([
            'trade_name' => 'Sede EV Ltda',
            'legal_name' => 'Sede Escritório Virtual Ltda',
        ]);
        $this->sede = ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => '111',
            'protocol_number' => 'VIA-2026-SEDE1',
            'company_id' => $sedeCompany->id,
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $this->sede->id,
            'is_virtual_office_hq' => true,
            'tvl_product_number' => 'TVL-2026-SEDE1',
        ]);
        VirtualOfficeInscriptionLock::create([
            'property_registration' => '111',
            'sede_viability_request_id' => $this->sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);

        // 2 ABRIGADOS na mesma inscrição '111', cada um com empresa e TVL próprios.
        foreach (['AB1', 'AB2'] as $i => $sufixo) {
            $company = Company::factory()->create([
                'legal_name' => "Abrigado {$sufixo} ME",
            ]);
            $abrigado = ViabilityRequest::factory()->protocoled()->create([
                'property_registration' => '111',
                'protocol_number' => "VIA-2026-{$sufixo}",
                'company_id' => $company->id,
            ]);
            ViabilityDecision::factory()->create([
                'viability_request_id' => $abrigado->id,
                'is_virtual_office_tenant' => true,
                'virtual_office_hq_tvl_number' => 'TVL-2026-SEDE1',
                'tvl_product_number' => "TVL-2026-{$sufixo}",
            ]);
            $this->abrigados[$i] = $abrigado;
        }
    }

    public function test_consulta_por_numero_da_sede_traz_sede_e_abrigados(): void
    {
        $paginator = $this->service()->consultar(['sede' => 'TVL-2026-SEDE1']);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(3, $paginator->total());

        $ids = $paginator->getCollection()->pluck('id')->all();
        $this->assertContains($this->sede->id, $ids);
        $this->assertContains($this->abrigados[0]->id, $ids);
        $this->assertContains($this->abrigados[1]->id, $ids);
    }

    public function test_linha_mapeia_tipo_tvl_razao_e_demais_campos(): void
    {
        $paginator = $this->service()->consultar(['sede' => 'TVL-2026-SEDE1']);
        $service = $this->service();

        $linhas = $paginator->getCollection()
            ->map(fn (ViabilityRequest $r) => $service->linha($r))
            ->keyBy('protocolo');

        foreach ($linhas as $linha) {
            $this->assertSame(
                ['tipo', 'tvl', 'razao_social', 'data_emissao', 'inscricao', 'protocolo'],
                array_keys($linha),
            );
            $this->assertSame('111', $linha['inscricao']);
            $this->assertNotNull($linha['data_emissao']);
        }

        $sede = $linhas['VIA-2026-SEDE1'];
        $this->assertSame('sede', $sede['tipo']);
        $this->assertSame('TVL-2026-SEDE1', $sede['tvl']);
        $this->assertSame('Sede EV Ltda', $sede['razao_social']);

        $ab1 = $linhas['VIA-2026-AB1'];
        $this->assertSame('abrigado', $ab1['tipo']);
        $this->assertSame('TVL-2026-AB1', $ab1['tvl']);
        $this->assertSame('Abrigado AB1 ME', $ab1['razao_social']);

        $this->assertSame('abrigado', $linhas['VIA-2026-AB2']['tipo']);
    }

    public function test_consulta_por_inscricao_traz_os_mesmos_tres(): void
    {
        $paginator = $this->service()->consultar(['inscricao' => '111']);

        $this->assertSame(3, $paginator->total());

        $tipos = $paginator->getCollection()
            ->map(fn (ViabilityRequest $r) => $this->service()->linha($r)['tipo'])
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['abrigado', 'abrigado', 'sede'], $tipos);
    }

    public function test_numero_de_sede_inexistente_nao_traz_linhas(): void
    {
        $paginator = $this->service()->consultar(['sede' => 'nao-existe']);

        $this->assertSame(0, $paginator->total());
        $this->assertCount(0, $paginator->items());
    }

    private function service(): RelatorioSedeEscritorioVirtualService
    {
        return app(RelatorioSedeEscritorioVirtualService::class);
    }
}
