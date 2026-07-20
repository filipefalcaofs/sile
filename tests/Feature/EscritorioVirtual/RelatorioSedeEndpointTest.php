<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Camada HTTP do relatório sede × abrigados de escritório virtual (Plano R1 —
 * Task 2). O endpoint serve a tela Inertia com o MESMO recorte da consulta
 * route-free (RN-005 — {@see RelatorioSedeEscritorioVirtualService}) e, com
 * ?formato=, exporta pelo contrato único (ReportExporter). Gated por
 * consultar-relatorios; sem a permissão, 403 no ponto único.
 */
class RelatorioSedeEndpointTest extends TestCase
{
    use RefreshDatabase;

    private ViabilityRequest $sede;

    /** @var array<int, ViabilityRequest> */
    private array $abrigados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

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

    /**
     * Consultor com consultar-relatorios: o analista NÃO herda a permissão no
     * seeder, então concedemos explicitamente (o gate da rota é a permissão, não
     * o papel).
     */
    private function consultor(): User
    {
        $user = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('consultar-relatorios');

        return $user;
    }

    public function test_lista_inertia_com_sede_e_abrigados_mapeados_por_linha(): void
    {
        $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/escritorio-virtual?sede=TVL-2026-SEDE1')
            ->assertOk()
            // A tela React (.tsx) chega numa fase de frontend posterior; aqui só
            // provamos o contrato do controller — o nome do componente, sem exigir
            // o arquivo em disco.
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/relatorios/escritorio-virtual', false)
                ->has('relatorio.data', 3)
                ->has('relatorio.data.0', fn (Assert $linha) => $linha
                    ->hasAll(['tipo', 'tvl', 'razao_social', 'data_emissao', 'inscricao', 'protocolo'])
                    ->where('tipo', 'sede')
                    ->where('inscricao', '111')
                    ->etc())
                ->where('filtros.sede', 'TVL-2026-SEDE1')
                ->has('perPageOptions'));

        // A consulta é auditada (RN-002).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-escritorio-virtual',
            'result' => 'sucesso',
        ]);
    }

    public function test_exporta_xlsx_pelo_contrato_unico(): void
    {
        $response = $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/escritorio-virtual?formato=xlsx&sede=TVL-2026-SEDE1')
            ->assertOk();

        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('content-disposition'),
        );

        // A exportação delega ao ReportExporter, que audita (RN-008).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'exporta-relatorio-sede-ev-xlsx',
            'result' => 'sucesso',
        ]);
    }

    public function test_sem_consultar_relatorios_recebe_403(): void
    {
        $semPermissao = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/relatorios/escritorio-virtual?sede=TVL-2026-SEDE1')
            ->assertForbidden();
    }
}
