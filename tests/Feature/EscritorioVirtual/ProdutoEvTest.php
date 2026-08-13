<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Aba/Card Produto EV (T03): o detalhe do processo expõe, no recurso do
 * processo, o bloco `escritorio_virtual` com Tipo (sede|abrigado), o TVL da
 * sede, a inscrição, a validade da sede (não modelada → null/"—") e o status
 * do produto. Para o ABRIGADO, o "End. Virtual" é o nº TVL da sede
 * (virtual_office_hq_tvl_number — RN-EV-05, CA-P-01).
 *
 * CA-P-02: o requerente EXTERNO (portal, guard `web`) NÃO alcança o PDF do
 * produto (TVL) pelo SILE — as rotas do produto vivem sob o guard `gestao`
 * (+ emitir-tvl + signed); o cidadão é redirecionado ao login da gestão.
 */
class ProdutoEvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    public function test_abrigado_exibe_end_virtual_igual_ao_tvl_da_sede(): void
    {
        $sede = ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => 'INSC-P-1',
            'protocol_number' => 'VIA-P-SEDE',
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $sede->id,
            'is_virtual_office_hq' => true,
            'tvl_product_number' => 'TVL-P-SEDE',
        ]);

        $abrigadoCompany = Company::factory()->create(['legal_name' => 'Abrigado P ME']);
        $abrigado = ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => 'INSC-P-1',
            'protocol_number' => 'VIA-P-AB',
            'company_id' => $abrigadoCompany->id,
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $abrigado->id,
            'is_virtual_office_tenant' => true,
            'virtual_office_hq_tvl_number' => 'TVL-P-SEDE',
            'tvl_product_number' => 'TVL-P-AB',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$abrigado->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/processos/show')
                ->where('processo.escritorio_virtual.tipo', 'abrigado')
                ->where('processo.escritorio_virtual.tvl_sede', 'TVL-P-SEDE')
                ->where('processo.escritorio_virtual.inscricao', 'INSC-P-1')
                ->where('processo.escritorio_virtual.validade_sede', null)
                ->etc());
    }

    public function test_sede_exibe_tipo_sede_com_o_proprio_tvl(): void
    {
        $sede = ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => 'INSC-P-2',
            'protocol_number' => 'VIA-P-SEDE2',
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $sede->id,
            'is_virtual_office_hq' => true,
            'tvl_product_number' => 'TVL-P-SEDE2',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$sede->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('processo.escritorio_virtual.tipo', 'sede')
                ->where('processo.escritorio_virtual.tvl_sede', 'TVL-P-SEDE2')
                ->where('processo.escritorio_virtual.validade_sede', null)
                ->etc());
    }

    public function test_processo_sem_ev_nao_expoe_bloco(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'tvl_product_number' => 'TVL-P-COMUM',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$request->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('processo.escritorio_virtual', null)
                ->etc());
    }

    public function test_requerente_externo_nao_alcanca_o_pdf_do_produto(): void
    {
        // Requerente externo: cidadão autenticado no PORTAL (guard `web`).
        $externo = User::factory()->withAcceptedLgpdTerm()->create();
        $externo->assignRole('cidadao');

        $request = ViabilityRequest::factory()->protocoled()->create();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'tvl_product_number' => 'TVL-EXT',
        ]);

        // Emitir o produto (TVL): rota de gestão — o cidadão do portal cai no login da gestão.
        $this->actingAs($externo, 'web')
            ->post("/gestao/processos/{$request->id}/tvl")
            ->assertRedirect(route('gestao.login'));

        // Baixar o PDF do produto: idem — inacessível ao requerente externo.
        $this->actingAs($externo, 'web')
            ->get('/gestao/processos/tvl/1/download')
            ->assertRedirect(route('gestao.login'));
    }
}
