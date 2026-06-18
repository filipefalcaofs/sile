<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\ResumoProcessoAgent;
use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Enums\AnalysisRecordStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AiConfiguration;
use App\Models\AiSuggestion;
use App\Models\AnalysisRecord;
use App\Models\ViabilityRequest;
use App\Services\Ai\ResumoProcessoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * HU-117 (resumo do processo para o analista): a IA sintetiza a pré-análise do
 * motor (per_cnae/engine_snapshot), as inconsistências (Onda 1) e os dados
 * locacionais declarados num resumo FIEL — sempre SUGESTÃO revisável, jamais
 * decisão nem afirmação de desfecho (AI-SPEC Failure Mode #1). Prova: resumo com
 * fonte vira sugestão sugerida; resumo sem fonte escala para humano; toggle
 * features.ia_resumo OFF ⇒ NÃO chama o provedor e NÃO cria sugestão (degradação
 * honesta). A função GERA TEXTO (capability text, sem anexos). Fake por agente —
 * sem rede, sem custo.
 */
#[Group('ia')]
class ResumoProcessoTest extends TestCase
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
     * Processo em análise com a ficha (revisão vigente) pré-analisada pelo motor —
     * a entrada real do resumo (per_cnae/engine_snapshot + dados locacionais).
     */
    private function processoComFicha(): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'used_area_m2' => 50,
            'address_street' => 'Rua das Flores',
            'address_number' => '100',
            'address_neighborhood' => 'Centro',
            'status' => ViabilityRequestStatus::EmAnalise,
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        return $processo;
    }

    private function service(): ResumoProcessoService
    {
        return app(ResumoProcessoService::class);
    }

    public function test_resumo_com_fonte_vira_sugestao_revisavel(): void
    {
        config(['sile.features.ia_resumo' => true]);
        $this->provedorTextoAtivo();
        $processo = $this->processoComFicha();

        ResumoProcessoAgent::fake([[
            'resumo' => 'Processo de viabilidade para comércio varejista no Centro; o motor sugere deferimento da atividade principal.',
            'pontos_chave' => ['Atividade principal com tendência permitida pelo motor', 'Sem inconsistências de IA registradas'],
            'fonte' => 'pré-análise do motor (engine_snapshot) e ficha de análise',
        ]]);

        $despachou = $this->service()->processar($processo, userId: null);

        $this->assertTrue($despachou);
        // O input REAL da ficha (dados locacionais minimizados) vai ao provedor —
        // síntese fiel, não fachada.
        ResumoProcessoAgent::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, 'Centro'),
        );

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::ResumoProcesso, $sugestao->type);
        // Síntese é apoio revisável, jamais decisão (RN-001/Failure Mode #1).
        $this->assertSame(AiSuggestionStatus::Sugerida, $sugestao->status);
        $this->assertSame($processo->id, $sugestao->viability_request_id);
        $this->assertNotEmpty($sugestao->output['resumo']);
        $this->assertSame('resumo-processo-v1', $sugestao->prompt_version);

        // Toggle compartilhado ia_resumo (116/117), auditoria específica da HU.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'ia-resumo_processo',
            'result' => 'sucesso',
        ]);
    }

    public function test_resumo_sem_fonte_escala_para_humano(): void
    {
        config(['sile.features.ia_resumo' => true]);
        $this->provedorTextoAtivo();
        $processo = $this->processoComFicha();

        // Sem fonte rastreável a síntese não pode ser aceita às cegas: nasce
        // escalada para revisão humana (guardrail do RunAiAgentJob).
        ResumoProcessoAgent::fake([[
            'resumo' => 'Resumo sem indicação de origem.',
        ]]);

        $this->service()->processar($processo);

        $this->assertSame(AiSuggestionStatus::EscaladaHumano, AiSuggestion::query()->sole()->status);
    }

    public function test_toggle_desligado_nao_chama_provedor_nem_cria_sugestao(): void
    {
        config(['sile.features.ia_resumo' => false]);
        $this->provedorTextoAtivo();
        $processo = $this->processoComFicha();

        ResumoProcessoAgent::fake([[
            'resumo' => 'qualquer',
            'fonte' => 'motor',
        ]]);

        $despachou = $this->service()->processar($processo);

        $this->assertFalse($despachou);
        ResumoProcessoAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }
}
