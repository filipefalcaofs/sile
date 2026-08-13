<?php

namespace Tests\Feature\Ai;

use App\Models\AiSuggestion;
use App\Services\Ai\AiCallAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Helper de auditoria de CHAMADA de IA (RN-002). Padroniza logName 'ia' e o
 * evento ia-{função}, fixa a versão do prompt como rules_version e marca o
 * acesso a dado pessoal (LGPD). NUNCA registra api_key nem saída com PII — só
 * transporta as propriedades seguras que a camada de serviço/job decidiu logar.
 */
class AiCallAuditorTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): AiCallAuditor
    {
        return app(AiCallAuditor::class);
    }

    public function test_registra_chamada_com_log_name_ia_e_evento_por_funcao(): void
    {
        $activity = $this->auditor()->record(
            function: 'ocr',
            promptVersion: 'ocr-v1',
            properties: ['provider' => 'openai', 'model' => 'gpt-5.4-mini', 'prompt_tokens' => 120],
        );

        $this->assertDatabaseHas('activity_log', [
            'id' => $activity->id,
            'log_name' => 'ia',
            'event' => 'ia-ocr',
            'rules_version' => 'ocr-v1',
            'result' => 'sucesso',
            'personal_data' => false,
        ]);

        $this->assertSame('openai', $activity->fresh()->properties['provider']);
    }

    public function test_marca_dado_pessoal_e_vincula_o_assunto(): void
    {
        $suggestion = AiSuggestion::factory()->create();

        $activity = $this->auditor()->record(
            function: 'ocr',
            promptVersion: 'ocr-v1',
            properties: ['ai_suggestion_id' => $suggestion->id],
            subject: $suggestion,
            personalData: true,
        );

        $this->assertDatabaseHas('activity_log', [
            'id' => $activity->id,
            'subject_type' => AiSuggestion::class,
            'subject_id' => $suggestion->id,
            'personal_data' => true,
        ]);
    }

    public function test_registra_falha_sem_assunto(): void
    {
        $activity = $this->auditor()->record(
            function: 'classificacao',
            promptVersion: 'classificacao-v1',
            properties: ['erro' => 'tempo esgotado'],
            result: 'falha',
        );

        $this->assertDatabaseHas('activity_log', [
            'id' => $activity->id,
            'log_name' => 'ia',
            'event' => 'ia-classificacao',
            'result' => 'falha',
        ]);
        $this->assertNull($activity->fresh()->subject_id);
    }

    public function test_nunca_inclui_api_key_nas_propriedades(): void
    {
        $activity = $this->auditor()->record(
            function: 'ocr',
            promptVersion: 'ocr-v1',
            properties: ['provider' => 'openai', 'model' => 'gpt-5.4-mini'],
        );

        $this->assertArrayNotHasKey('api_key', $activity->fresh()->properties->toArray());
    }
}
