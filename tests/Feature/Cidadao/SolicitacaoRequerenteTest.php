<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\ServiceDeclaration;
use App\Models\ServiceDeclarationAcceptance;
use App\Models\User;
use App\Models\ViabilityQuery;
use App\Models\ViabilityRequest;
use App\Policies\ViabilityRequestPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

    public function test_escopo_da_consulta_nao_confunde_id_de_servidor_com_id_de_cidadao(): void
    {
        $cidadao = Cidadao::factory()->create(['id' => 9002]);
        $outro = Cidadao::factory()->create();
        User::factory()->create(['id' => 9002]);

        ViabilityQuery::factory()->create(['cidadao_id' => $cidadao->id]);
        ViabilityQuery::factory()->create(['cidadao_id' => $outro->id]);

        $this->assertSame(1, ViabilityQuery::forCidadao($cidadao)->count());
        $this->assertTrue(ViabilityQuery::forCidadao($cidadao)->first()->cidadao->is($cidadao));
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

    public function test_solicitacao_do_cidadao_a_nao_aparece_na_listagem_do_cidadao_b(): void
    {
        $a = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $b = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['requester_cidadao_id' => $a->id]);

        $this->actingAs($b)->get(route('portal.solicitacoes.show', $solicitacao))->assertForbidden();
        $this->actingAs($a)->get(route('portal.solicitacoes.show', $solicitacao))->assertOk();
    }
}
