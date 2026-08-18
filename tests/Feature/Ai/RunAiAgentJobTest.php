<?php

namespace Tests\Feature\Ai;

use App\Enums\AiSuggestionStatus;
use App\Models\AiConfiguration;
use App\Models\AiSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Fixtures\Ai\ProbeAiJob;
use Tests\Fixtures\Ai\ProbeStructuredAgent;
use Tests\TestCase;
use Throwable;

/**
 * Mecânica do Job base de IA (RunAiAgentJob): guard idempotente duplo
 * (re-check do gate + dedup por entrada/versão do prompt), chamada síncrona ao
 * agente, guardrails (confiança/fonte) e persistência da AiSuggestion SÓ no
 * sucesso, com auditoria RN-002. A fila é sync na suíte: dispatch roda inline.
 */
class RunAiAgentJobTest extends TestCase
{
    use RefreshDatabase;

    private function provedorAtivoDeTexto(): void
    {
        AiConfiguration::factory()->create([
            'provider' => 'openai',
            'capability' => 'text',
            'model' => 'gpt-5.4-mini',
            'active' => true,
            'is_default' => true,
        ]);
    }

    public function test_toggle_desligado_no_handle_nao_chama_o_provedor_nem_cria_sugestao(): void
    {
        config(['sile.features.ia_probe' => false]);
        $this->provedorAtivoDeTexto();

        ProbeStructuredAgent::fake([['valor' => 'x', 'confianca' => 'alta', 'fonte' => 'doc']]);

        ProbeAiJob::dispatch(['k' => 1]);

        ProbeStructuredAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'ia-probe',
            'result' => 'desativado',
        ]);
    }

    public function test_sucesso_cria_sugestao_sugerida_com_proveniencia_e_audita(): void
    {
        config(['sile.features.ia_probe' => true]);
        $this->provedorAtivoDeTexto();

        ProbeStructuredAgent::fake([['valor' => 'ok', 'confianca' => 'alta', 'fonte' => 'documento']]);

        ProbeAiJob::dispatch(['k' => 1], requestId: null, userId: null);

        ProbeStructuredAgent::assertPrompted(fn ($prompt) => $prompt->prompt === 'probe prompt');

        $suggestion = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionStatus::Sugerida, $suggestion->status);
        $this->assertSame('ok', $suggestion->output['valor']);
        $this->assertSame('alta', $suggestion->confidence);
        $this->assertSame('openai', $suggestion->provider);
        $this->assertSame('gpt-5.4-mini', $suggestion->model);
        $this->assertSame('probe-v1', $suggestion->prompt_version);
        // Sem preço configurado para o modelo ⇒ custo nunca inventado.
        $this->assertNull($suggestion->cost_estimated);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'ia-probe',
            'result' => 'sucesso',
            'subject_type' => AiSuggestion::class,
            'subject_id' => $suggestion->id,
        ]);
    }

    public function test_confianca_abaixo_do_limiar_escala_para_humano(): void
    {
        config(['sile.features.ia_probe' => true]);
        $this->provedorAtivoDeTexto();

        ProbeStructuredAgent::fake([['valor' => 'incerto', 'confianca' => 'baixa', 'fonte' => 'documento']]);

        ProbeAiJob::dispatch(['k' => 1]);

        $this->assertSame(AiSuggestionStatus::EscaladaHumano, AiSuggestion::query()->sole()->status);
    }

    public function test_fonte_ausente_escala_para_humano(): void
    {
        config(['sile.features.ia_probe' => true]);
        $this->provedorAtivoDeTexto();

        ProbeStructuredAgent::fake([['valor' => 'x', 'confianca' => 'alta', 'fonte' => '']]);

        ProbeAiJob::dispatch(['k' => 1]);

        $this->assertSame(AiSuggestionStatus::EscaladaHumano, AiSuggestion::query()->sole()->status);
    }

    public function test_dedup_nao_reprocessa_a_mesma_entrada(): void
    {
        config(['sile.features.ia_probe' => true]);
        $this->provedorAtivoDeTexto();

        ProbeStructuredAgent::fake(fn () => ['valor' => 'ok', 'confianca' => 'alta', 'fonte' => 'documento']);

        ProbeAiJob::dispatch(['k' => 1]);
        ProbeAiJob::dispatch(['k' => 1]);

        $this->assertSame(1, AiSuggestion::query()->count());
    }

    public function test_job_falho_audita_sem_criar_sugestao(): void
    {
        config(['sile.features.ia_probe' => true]);
        $this->provedorAtivoDeTexto();

        ProbeStructuredAgent::fake(fn () => throw new RuntimeException('falha do provedor'));

        try {
            ProbeAiJob::dispatch(['k' => 1]);
        } catch (Throwable) {
            // O driver sync re-lança após chamar failed(); a auditoria já ocorreu.
        }

        $this->assertSame(0, AiSuggestion::query()->count());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'ia-probe',
            'result' => 'falha',
        ]);
    }
}
