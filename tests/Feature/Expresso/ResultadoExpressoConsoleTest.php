<?php

namespace Tests\Feature\Expresso;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Retaguarda do resultado do fluxo expresso (HU-076/HU-078): a SEDUR consulta as
 * decisões automáticas (deferida/indeferida) em lista filtrável e no detalhe
 * imutável da ViabilityDecision. Acesso por reuso de consultar-solicitacoes (a
 * decisão é parte da solicitação — sem permissão nova). SOMENTE LEITURA: não há
 * ação de re-decidir. Anti-fachada: exibe só o que existe na decisão real e o
 * status de transmissão Regin/SEFAZ lido da auditoria (pendente quando o canal
 * está bloqueado — nunca "enviado"). Espelha o RiscoController/RiscoConsultaTest.
 */
class ResultadoExpressoConsoleTest extends TestCase
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

    /**
     * Solicitação DEFERIDA com decisão real (número TVL no deferimento).
     */
    private function deferida(string $protocolo): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);

        ViabilityDecision::factory()->create(['viability_request_id' => $request->id]);

        return $request;
    }

    /**
     * Solicitação INDEFERIDA com decisão real (sem TVL — RN-009).
     */
    private function indeferida(string $protocolo): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Indeferida,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);

        ViabilityDecision::factory()->indeferida()->create(['viability_request_id' => $request->id]);

        return $request;
    }

    public function test_sem_permissao_consultar_solicitacoes_recebe_403_auditado(): void
    {
        // Acessa a gestão mas NÃO tem consultar-solicitacoes: o gate específico
        // barra e audita (CA-04), espelhando o risco/território.
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/resultados-expresso')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_com_permissao_lista_apenas_decididas(): void
    {
        $this->deferida('VIA-'.now()->year.'-000001');
        $this->indeferida('VIA-'.now()->year.'-000002');

        // Protocolada SEM decisão (em processamento / em análise técnica): NÃO
        // aparece — a lista é só de decisões reais, nunca inventa um desfecho.
        ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => 'VIA-'.now()->year.'-000003',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/resultados-expresso')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/resultados-expresso/index')
                ->has('decisoes.data', 2)
                ->has('perPageOptions')
                ->has('outcomeOptions')
                ->where('filtros.outcome', ''));
    }

    public function test_filtro_por_resultado_funciona(): void
    {
        $this->deferida('VIA-'.now()->year.'-000001');
        $this->indeferida('VIA-'.now()->year.'-000002');

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/resultados-expresso?outcome=indeferida')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('decisoes.data', 1)
                ->where('decisoes.data.0.outcome', 'indeferida'));
    }

    public function test_show_de_deferida_retorna_detalhe_com_tvl(): void
    {
        $request = $this->deferida('VIA-'.now()->year.'-000001');

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/resultados-expresso/{$request->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/resultados-expresso/show')
                ->where('decisao.outcome', 'deferida')
                ->where('decisao.tvl_product_number', 'TVL-'.now()->year.'-000001')
                ->has('decisao.per_cnae', 1)
                ->where('decisao.solicitacao.protocol_number', 'VIA-'.now()->year.'-000001')
                ->has('transmissao'));
    }

    public function test_show_sem_decisao_retorna_404(): void
    {
        // em_analise / protocolada sem decisão automática: detalhe honesto = 404
        // (nunca um deferimento/indeferimento fabricado).
        $semDecisao = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => 'VIA-'.now()->year.'-000009',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/resultados-expresso/{$semDecisao->id}")
            ->assertNotFound();
    }

    public function test_show_exibe_transmissao_regin_sefaz_como_pendente_da_auditoria(): void
    {
        $request = $this->deferida('VIA-'.now()->year.'-000001');

        // Espelha o que os listeners 09-08/09-09 gravam quando os contratos
        // Regin/SEFAZ estão bloqueados (indisponíveis até a Fase 13): a tela
        // mostra PENDENTE lido da trilha, nunca "enviado".
        $audit = app(AuditService::class);
        $audit->log(
            logName: 'integracoes',
            event: 'regin-parecer',
            description: 'Pendência de comunicação ao Regin',
            subject: $request,
            result: 'bloqueado',
        );
        $audit->log(
            logName: 'integracoes',
            event: 'sefaz-viabilidade',
            description: 'Pendência de envio à SEFAZ',
            subject: $request,
            result: 'bloqueado',
        );

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/resultados-expresso/{$request->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transmissao.regin.status', 'pendente')
                ->where('transmissao.sefaz.status', 'pendente'));
    }
}
