<?php

namespace Tests\Feature\Cidadao;

use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\Cidadao;
use App\Models\ServiceDeclaration;
use App\Models\ServiceDeclarationAcceptance;
use App\Models\User;
use App\Models\ViabilityQuery;
use App\Models\ViabilityRequest;
use App\Policies\ViabilityRequestPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SolicitacaoRequerenteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_requerente_e_cidadao_e_criador_pode_ser_servidor(): void
    {
        $cidadao = Cidadao::factory()->create();
        $servidor = User::factory()->create();

        $solicitacao = ViabilityRequest::factory()->create([
            'requester_cidadao_id' => $cidadao->id,
            'created_by_type' => User::class,
            'created_by_id' => $servidor->id,
        ]);

        $this->assertTrue($solicitacao->requester->is($cidadao));
        $this->assertInstanceOf(Cidadao::class, $solicitacao->requester);
        $this->assertTrue($solicitacao->createdBy->is($servidor));
    }

    public function test_servidor_com_mesmo_id_do_cidadao_nao_opera_a_solicitacao(): void
    {
        $cidadao = Cidadao::factory()->create(['id' => 9001]);
        $servidor = User::factory()->create(['id' => 9001]);
        $solicitacao = ViabilityRequest::factory()->draft()->create(['requester_cidadao_id' => $cidadao->id]);

        $policy = new ViabilityRequestPolicy;

        $this->assertTrue($policy->view($cidadao, $solicitacao));
        $this->assertFalse($policy->view($servidor, $solicitacao));
        $this->assertFalse($policy->update($servidor, $solicitacao));
        $this->assertFalse($policy->protocol($servidor, $solicitacao));
        $this->assertFalse($policy->cancel($servidor, $solicitacao));
    }

    public function test_cidadao_nao_ve_solicitacao_de_outro_cidadao(): void
    {
        $dono = Cidadao::factory()->create();
        $outro = Cidadao::factory()->create();
        $solicitacao = ViabilityRequest::factory()->draft()->create(['requester_cidadao_id' => $dono->id]);

        $policy = new ViabilityRequestPolicy;

        $this->assertFalse($policy->view($outro, $solicitacao));
        $this->assertFalse($policy->update($outro, $solicitacao));
    }

    public function test_historico_de_consultas_nao_confunde_id_de_servidor_com_id_de_cidadao(): void
    {
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create(['id' => 9002]);
        $outro = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $servidor = User::factory()->withAcceptedLgpdTerm()->create(['id' => 9002]);

        $consultaDoCidadao = ViabilityQuery::factory()->create(['cidadao_id' => $cidadao->id]);
        ViabilityQuery::factory()->create(['cidadao_id' => $outro->id]);

        // Servidor de mesmo id, logado na gestão, não abre o histórico do cidadão.
        $this->actingAs($servidor, 'gestao')
            ->get(route('portal.viabilidade.historico'))
            ->assertRedirect('/portal/login');

        $this->actingAs($cidadao, 'web')
            ->get(route('portal.viabilidade.historico'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('consultas.data', 1)
                ->where('consultas.data.0.id', $consultaDoCidadao->id));
    }

    public function test_listagem_de_solicitacoes_nao_confunde_id_de_servidor_com_id_de_cidadao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create(['id' => 9004]);
        $servidor = User::factory()->withAcceptedLgpdTerm()->create(['id' => 9004]);
        $outro = Cidadao::factory()->withAcceptedLgpdTerm()->create();

        $propria = ViabilityRequest::factory()->protocoled()->create(['requester_cidadao_id' => $cidadao->id]);
        // Do outro cidadão, mas criada e atribuída ao servidor cujo id coincide.
        ViabilityRequest::factory()->create([
            'requester_cidadao_id' => $outro->id,
            'created_by_type' => User::class,
            'created_by_id' => $servidor->id,
            'assigned_user_id' => $servidor->id,
            'status' => ViabilityRequestStatus::EmAnalise,
        ]);

        // Servidor de mesmo id, logado na gestão, não abre a listagem do cidadão.
        $this->actingAs($servidor, 'gestao')
            ->get(route('portal.solicitacoes.index'))
            ->assertRedirect('/portal/login');

        $this->actingAs($cidadao, 'web')
            ->get(route('portal.solicitacoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('solicitacoes.data', 1)
                ->where('solicitacoes.data.0.id', $propria->id));
    }

    public function test_painel_do_cidadao_nao_confunde_id_de_servidor_com_id_de_cidadao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create(['id' => 9005]);
        $servidor = User::factory()->create(['id' => 9005]);
        $outro = Cidadao::factory()->withAcceptedLgpdTerm()->create();

        $propria = ViabilityRequest::factory()->draft()->create(['requester_cidadao_id' => $cidadao->id]);
        $alheia = ViabilityRequest::factory()->create([
            'requester_cidadao_id' => $outro->id,
            'created_by_type' => User::class,
            'created_by_id' => $servidor->id,
            'assigned_user_id' => $servidor->id,
            'status' => ViabilityRequestStatus::EmPendencia,
        ]);
        AnalysisPendency::factory()->create([
            'viability_request_id' => $alheia->id,
            'requested_by_user_id' => $servidor->id,
        ]);

        $this->actingAs($cidadao)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('indicadores.processos', 1)
                ->where('indicadores.em_analise', 0)
                ->where('indicadores.em_convite', 0)
                ->has('atencao.pendencias', 0)
                ->has('atencao.rascunhos', 1)
                ->where('atencao.rascunhos.0.id', $propria->id)
                ->has('solicitacoesRecentes', 1)
                ->where('solicitacoesRecentes.0.id', $propria->id));
    }

    public function test_aceite_de_declaracao_pertence_ao_cidadao(): void
    {
        $cidadao = Cidadao::factory()->create();
        $declaracao = ServiceDeclaration::factory()->create();
        $solicitacao = ViabilityRequest::factory()->create();

        $aceite = ServiceDeclarationAcceptance::create([
            'service_declaration_id' => $declaracao->id,
            'viability_request_id' => $solicitacao->id,
            'cidadao_id' => $cidadao->id,
            'version' => $declaracao->version,
            'accepted_at' => now(),
        ]);

        $this->assertTrue($aceite->cidadao->is($cidadao));
    }

    public function test_solicitacao_do_cidadao_a_nao_abre_para_o_cidadao_b(): void
    {
        $a = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $b = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['requester_cidadao_id' => $a->id]);

        $this->actingAs($b)->get(route('portal.solicitacoes.show', $solicitacao))->assertForbidden();
        $this->actingAs($a)->get(route('portal.solicitacoes.show', $solicitacao))->assertOk();
    }
}
