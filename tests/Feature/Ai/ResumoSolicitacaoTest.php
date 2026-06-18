<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\ResumoSolicitacaoAgent;
use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Enums\ViabilityRequestStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AiConfiguration;
use App\Models\AiSuggestion;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Ai\ResumoSolicitacaoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * HU-116 (resumo da solicitação para o cidadão, pré-protocolo): a IA sintetiza os
 * dados DECLARADOS (empresa/atividades/imóvel) num resumo de CONFERÊNCIA — sempre
 * SUGESTÃO revisável, jamais decisão e NUNCA afirmando o desfecho ("vai ser
 * deferido"). Prova: resumo com fonte vira sugestão sugerida; resumo sem fonte
 * escala para humano; toggle features.ia_resumo OFF ⇒ NÃO chama o provedor e NÃO
 * cria sugestão (degradação honesta). Toggle COMPARTILHADO com o resumo do
 * processo (HU-117), function/auditoria específicas por HU. A função GERA TEXTO
 * (capability text, sem anexos). Fake por agente — sem rede, sem custo.
 */
#[Group('ia')]
class ResumoSolicitacaoTest extends TestCase
{
    use RefreshDatabase;

    private function provedorTextoAtivo(): void
    {
        AiConfiguration::factory()->create([
            'provider' => 'openai',
            'capability' => 'text',
            'model' => 'gpt-5.4-mini',
            'active' => true,
            'is_default' => true,
        ]);
    }

    /**
     * Rascunho de solicitação com os dados declarados (empresa/CNAE/imóvel) — a
     * entrada real do resumo de conferência. requester = $dono para a policy.
     */
    private function solicitacaoDeclarada(?User $dono = null): ViabilityRequest
    {
        $empresa = Company::factory()->create(['legal_name' => 'Comércio Exemplo LTDA']);

        $atributos = [
            'company_id' => $empresa->id,
            'used_area_m2' => 50,
            'address_street' => 'Rua das Flores',
            'address_number' => '100',
            'address_neighborhood' => 'Centro',
            'status' => ViabilityRequestStatus::Rascunho,
        ];

        if ($dono !== null) {
            $atributos['requester_user_id'] = $dono->id;
            $atributos['created_by_user_id'] = $dono->id;
        }

        return ViabilityRequest::factory()->withPrimaryCnae()->create($atributos);
    }

    private function service(): ResumoSolicitacaoService
    {
        return app(ResumoSolicitacaoService::class);
    }

    public function test_resumo_com_fonte_vira_sugestao_revisavel(): void
    {
        config(['sile.features.ia_resumo' => true]);
        $this->provedorTextoAtivo();
        $solicitacao = $this->solicitacaoDeclarada();

        ResumoSolicitacaoAgent::fake([[
            'resumo' => 'Solicitação de viabilidade para comércio varejista no Centro, em nome de Comércio Exemplo LTDA.',
            'fonte' => 'dados declarados na solicitação (empresa, atividades e imóvel)',
        ]]);

        $despachou = $this->service()->processar($solicitacao, userId: null);

        $this->assertTrue($despachou);
        // Os dados DECLARADOS (imóvel minimizado) vão ao provedor — conferência
        // fiel do que o cidadão informou, não fachada.
        ResumoSolicitacaoAgent::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, 'Centro'),
        );

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::ResumoSolicitacao, $sugestao->type);
        // Conferência é apoio revisável, jamais decisão (RN-001/Failure Mode #1).
        $this->assertSame(AiSuggestionStatus::Sugerida, $sugestao->status);
        $this->assertSame($solicitacao->id, $sugestao->viability_request_id);
        $this->assertNotEmpty($sugestao->output['resumo']);
        $this->assertSame('resumo-solicitacao-v1', $sugestao->prompt_version);

        // Toggle compartilhado ia_resumo (116/117), auditoria específica da HU.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'ia-resumo_solicitacao',
            'result' => 'sucesso',
        ]);
    }

    public function test_resumo_sem_fonte_escala_para_humano(): void
    {
        config(['sile.features.ia_resumo' => true]);
        $this->provedorTextoAtivo();
        $solicitacao = $this->solicitacaoDeclarada();

        // Sem fonte rastreável a conferência não pode ser aceita às cegas: nasce
        // escalada para revisão humana (guardrail do RunAiAgentJob).
        ResumoSolicitacaoAgent::fake([[
            'resumo' => 'Resumo sem indicação de origem.',
        ]]);

        $this->service()->processar($solicitacao);

        $this->assertSame(AiSuggestionStatus::EscaladaHumano, AiSuggestion::query()->sole()->status);
    }

    public function test_toggle_desligado_nao_chama_provedor_nem_cria_sugestao(): void
    {
        config(['sile.features.ia_resumo' => false]);
        $this->provedorTextoAtivo();
        $solicitacao = $this->solicitacaoDeclarada();

        ResumoSolicitacaoAgent::fake([[
            'resumo' => 'qualquer',
            'fonte' => 'dados declarados',
        ]]);

        $despachou = $this->service()->processar($solicitacao);

        $this->assertFalse($despachou);
        ResumoSolicitacaoAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_revisao_do_wizard_dispara_resumo_ao_carregar_sugestao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['sile.features.ia_resumo' => true]);
        config(['sile.features.solicitacao_viabilidade' => true]);
        $this->provedorTextoAtivo();

        $dono = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
        $solicitacao = $this->solicitacaoDeclarada($dono);

        ResumoSolicitacaoAgent::fake([[
            'resumo' => 'Solicitação de comércio varejista no Centro para conferência antes de protocolar.',
            'fonte' => 'dados declarados na solicitação (empresa, atividades e imóvel)',
        ]]);

        $url = route('portal.solicitacoes.edit', $solicitacao);
        $versao = (new HandleInertiaRequests)->version(request());

        // Partial reload da prop deferida sugestoesResumo: ao carregar o card de
        // conferência na etapa de revisão, o controller dispara o resumo (gated/
        // dedup) e a prop já o entrega.
        $resposta = $this->actingAs($dono)
            ->get($url, [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $versao,
                'X-Inertia-Partial-Component' => 'portal/solicitacoes/wizard',
                'X-Inertia-Partial-Data' => 'sugestoesResumo',
            ])
            ->assertOk();

        ResumoSolicitacaoAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Centro'));

        $sugestao = AiSuggestion::query()
            ->where('type', AiSuggestionType::ResumoSolicitacao)
            ->sole();
        $this->assertSame($solicitacao->id, $sugestao->viability_request_id);

        $resposta->assertJsonPath('props.sugestoesResumo.0.type', 'resumo_solicitacao');
        $resposta->assertJsonPath('props.sugestoesResumo.0.status', 'sugerida');
    }

    public function test_revisao_do_wizard_nao_dispara_resumo_com_toggle_desligado(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['sile.features.ia_resumo' => false]);
        config(['sile.features.solicitacao_viabilidade' => true]);
        $this->provedorTextoAtivo();

        $dono = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
        $solicitacao = $this->solicitacaoDeclarada($dono);

        ResumoSolicitacaoAgent::fake([[
            'resumo' => 'não deveria rodar',
            'fonte' => 'dados declarados',
        ]]);

        $url = route('portal.solicitacoes.edit', $solicitacao);
        $versao = (new HandleInertiaRequests)->version(request());

        $this->actingAs($dono)
            ->get($url, [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $versao,
                'X-Inertia-Partial-Component' => 'portal/solicitacoes/wizard',
                'X-Inertia-Partial-Data' => 'sugestoesResumo',
            ])
            ->assertOk();

        ResumoSolicitacaoAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->where('type', AiSuggestionType::ResumoSolicitacao)->count());
    }
}
