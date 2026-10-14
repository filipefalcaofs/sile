<?php

namespace Tests\Feature\Expresso;

use App\Enums\AnalysisRecordStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Processo deferido ou indeferido no fluxo expresso abre uma ficha somente
 * leitura: o que o motor fez e o resultado de cada CNAE, sem criar ficha de
 * análise humana.
 */
class FichaExpressoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_deferido_no_expresso_abre_ficha_com_o_resultado_de_cada_cnae(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocol_number' => 'VIA-2026-000910',
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $processo->id,
            'per_cnae' => [
                [
                    'cnae' => '4712100',
                    'cnae_formatado' => '4712-1/00',
                    'is_primary' => true,
                    'tendencia' => 'permitido',
                    'tendencia_label' => 'Permitido',
                    'fluxo' => 'expresso',
                    'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — Quadro 10'],
                ],
                [
                    'cnae' => '5611201',
                    'cnae_formatado' => '5611-2/01',
                    'is_primary' => false,
                    'tendencia' => 'permitido_com_condicoes',
                    'tendencia_label' => 'Permitido com condições',
                    'fluxo' => 'expresso',
                    'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — Quadro 11-A'],
                ],
            ],
        ]);

        $this->actingAs($this->consulta(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-expresso/show')
                ->where('processo.status', 'deferida')
                ->where('decisao.outcome', 'deferida')
                ->has('decisao.per_cnae', 2)
                ->where('decisao.per_cnae.0.cnae', '4712100')
                ->where('decisao.per_cnae.0.tendencia', 'permitido')
                ->where('decisao.per_cnae.1.cnae', '5611201')
                ->where('decisao.per_cnae.1.tendencia', 'permitido_com_condicoes')
                ->has('explicacao'));

        $this->actingAs(User::factory()->analista()->withAcceptedLgpdTerm()->create(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('gestao/ficha-expresso/show'));

        $this->assertDatabaseCount('analysis_records', 0);
    }

    public function test_indeferido_no_expresso_abre_ficha_com_o_resultado_de_cada_cnae(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Indeferida,
            'protocol_number' => 'VIA-2026-000911',
        ]);
        ViabilityDecision::factory()->indeferida()->create([
            'viability_request_id' => $processo->id,
            'per_cnae' => [
                [
                    'cnae' => '4712100',
                    'cnae_formatado' => '4712-1/00',
                    'is_primary' => true,
                    'tendencia' => 'nao_permitido',
                    'tendencia_label' => 'Não permitido',
                    'fluxo' => 'expresso',
                    'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — Quadro 10'],
                ],
            ],
        ]);

        $this->actingAs($this->consulta(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-expresso/show')
                ->where('processo.status', 'indeferida')
                ->where('decisao.outcome', 'indeferida')
                ->where('decisao.per_cnae.0.tendencia', 'nao_permitido')
                ->where('decisao.per_cnae.0.tendencia_label', 'Não permitido')
                ->where('decisao.reason', 'Diante do enquadramento acima, manifesta-se pelo indeferimento desta atividade, por ser o uso proibido na zona ZPR-1 segundo o Quadro 10 da LOUOS.'));

        $this->assertDatabaseCount('analysis_records', 0);
    }

    public function test_quem_so_consulta_nao_abre_a_ficha_de_analise(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-2026-000912',
        ]);
        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        $this->actingAs($this->consulta(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertForbidden();

        $this->actingAs(User::factory()->analista()->withAcceptedLgpdTerm()->create(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('gestao/ficha-analise/show'));
    }

    private function consulta(): User
    {
        return User::factory()->apoio()->withAcceptedLgpdTerm()->create();
    }
}
