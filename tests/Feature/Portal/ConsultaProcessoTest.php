<?php

namespace Tests\Feature\Portal;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Consulta de processo por número (do processo, da viabilidade ou do BAP),
 * aberta a qualquer cidadão logado, sem dados pessoais.
 */
class ConsultaProcessoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function cidadao(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    private function processo(?User $requerente = null, ViabilityRequestStatus $status = ViabilityRequestStatus::EmAnalise): ViabilityRequest
    {
        $empresa = Company::factory()->create(['legal_name' => 'AMP Consultoria e Acessoria Ltda', 'cnpj' => '57461624000123']);
        $processo = ViabilityRequest::factory()->create([
            'company_id' => $empresa->id,
            'requester_user_id' => $requerente?->id ?? User::factory()->cidadao()->create()->id,
        ]);
        $processo->forceFill([
            'status' => $status,
            'protocol_number' => '2026.000123',
            'external_reference' => 'BAP2600012345',
            'protocoled_at' => now()->subDays(10),
        ])->save();

        return $processo->refresh();
    }

    private function consultar(User $user, string $numero): TestResponse
    {
        return $this->actingAs($user)->get(route('portal.consulta', ['numero' => $numero]));
    }

    public function test_pagina_sem_numero_mostra_so_o_campo(): void
    {
        $this->actingAs($this->cidadao())->get(route('portal.consulta'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/consulta/index')
                ->where('numero', null)
                ->where('processo', null));
    }

    public function test_acha_por_protocolo_bap_e_viabilidade(): void
    {
        $processo = $this->processo();
        ViabilityDecision::factory()->create(['viability_request_id' => $processo->id, 'tvl_product_number' => '2104040']);
        $user = $this->cidadao();

        foreach ([' 2026.000123 ', 'bap2600012345', '2104040'] as $numero) {
            $this->consultar($user, $numero)->assertInertia(fn (Assert $page) => $page
                ->where('encontrado', true)
                ->where('processo.protocol_number', '2026.000123')
                ->where('processo.numero_viabilidade', '2104040')
                ->where('processo.empresa.razao_social', 'AMP Consultoria e Acessoria Ltda')
                ->where('processo.empresa.cnpj', '57.461.624/0001-23')
                ->has('timeline.etapas'));
        }
    }

    public function test_qualquer_cidadao_consulta_sem_dados_pessoais(): void
    {
        $processo = $this->processo();
        AnalysisPendency::factory()->create(['viability_request_id' => $processo->id, 'description' => 'Envie o alvará antigo.']);

        $resposta = $this->consultar($this->cidadao(), '2026.000123')->assertOk();
        $json = json_encode($resposta->viewData('page')['props']['processo'] + ['timeline' => $resposta->viewData('page')['props']['timeline']]);

        foreach (['cpf', 'email', 'phone', 'requester', 'address', 'Envie o alvará antigo.'] as $proibido) {
            $this->assertStringNotContainsString($proibido, (string) $json, "A consulta expôs {$proibido}.");
        }
    }

    public function test_nao_encontrado_ou_invalido_mostra_a_mesma_resposta(): void
    {
        $user = $this->cidadao();

        foreach (['9999.999999', '<script>'] as $numero) {
            $this->consultar($user, $numero)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('encontrado', false)
                ->where('processo', null));
        }
    }

    public function test_rascunho_nao_e_encontrado(): void
    {
        $this->processo(status: ViabilityRequestStatus::Rascunho);

        $this->consultar($this->cidadao(), '2026.000123')
            ->assertInertia(fn (Assert $page) => $page->where('encontrado', false));
    }

    public function test_pode_responder_so_para_o_requerente_com_convite_no_prazo(): void
    {
        $requerente = $this->cidadao();
        $processo = $this->processo($requerente, ViabilityRequestStatus::EmPendencia);
        $convite = AnalysisPendency::factory()->create([
            'viability_request_id' => $processo->id,
            'status' => AnalysisPendencyStatus::Aberta,
            'due_at' => now()->addDays(5),
        ]);

        $this->consultar($requerente, '2026.000123')->assertInertia(fn (Assert $page) => $page
            ->where('processo.convite.aberto', true)
            ->where('processo.pode_responder', true)
            ->where('processo.solicitacao_id', $processo->id));

        $this->consultar($this->cidadao(), '2026.000123')->assertInertia(fn (Assert $page) => $page
            ->where('processo.convite.aberto', true)
            ->where('processo.pode_responder', false)
            ->where('processo.solicitacao_id', null));

        $convite->forceFill(['due_at' => now()->subDay()])->save();

        $this->consultar($requerente, '2026.000123')->assertInertia(fn (Assert $page) => $page
            ->where('processo.convite.aberto', false)
            ->where('processo.pode_responder', false));
    }

    public function test_consulta_encontrada_e_auditada(): void
    {
        $processo = $this->processo();

        $this->consultar($this->cidadao(), '2026.000123')->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'consulta-processo',
            'subject_id' => $processo->id,
        ]);
    }
}
