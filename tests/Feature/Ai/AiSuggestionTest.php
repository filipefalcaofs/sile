<?php

namespace Tests\Feature\Ai;

use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Models\AiSuggestion;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fundação de execução de IA (Onda 1): a sugestão de IA é SEMPRE revisável,
 * nunca uma decisão (AI-SPEC Failure Mode #1). O ledger ai_suggestions guarda a
 * saída estruturada, a proveniência (provider/modelo/versão do prompt/tokens) e
 * o status — que jamais é "decidida".
 */
class AiSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_status_nunca_admite_decidida(): void
    {
        $valores = array_map(fn (AiSuggestionStatus $s): string => $s->value, AiSuggestionStatus::cases());

        $this->assertNotContains('decidida', $valores, 'A IA não decide: nenhum status pode ser "decidida".');
        $this->assertNull(AiSuggestionStatus::tryFrom('decidida'));
        $this->assertEqualsCanonicalizing(
            ['sugerida', 'escalada_humano', 'descartada', 'aplicada'],
            $valores,
        );
    }

    public function test_faz_cast_de_tipo_status_e_colunas_json(): void
    {
        $suggestion = AiSuggestion::factory()->create([
            'type' => AiSuggestionType::Ocr,
            'status' => AiSuggestionStatus::Sugerida,
            'input_ref' => ['document_id' => 7],
            'output' => ['texto' => 'CONTRATO', 'confianca' => 'alta', 'fonte' => 'doc'],
            'prompt_tokens' => 120,
            'completion_tokens' => 30,
            'cost_estimated' => null,
        ]);

        $fresh = $suggestion->fresh();

        $this->assertInstanceOf(AiSuggestionType::class, $fresh->type);
        $this->assertSame(AiSuggestionType::Ocr, $fresh->type);
        $this->assertInstanceOf(AiSuggestionStatus::class, $fresh->status);
        $this->assertSame(AiSuggestionStatus::Sugerida, $fresh->status);
        $this->assertSame(['document_id' => 7], $fresh->input_ref);
        $this->assertSame('CONTRATO', $fresh->output['texto']);
        $this->assertSame(120, $fresh->prompt_tokens);
        $this->assertSame(30, $fresh->completion_tokens);
        $this->assertNull($fresh->cost_estimated);
    }

    public function test_a_factory_cria_sugestao_revisavel_jamais_decidida(): void
    {
        $suggestion = AiSuggestion::factory()->create();

        $this->assertContains($suggestion->status, AiSuggestionStatus::cases());
        $this->assertNotSame('decidida', $suggestion->status->value);
    }

    public function test_relaciona_processo_e_autor(): void
    {
        $request = ViabilityRequest::factory()->create();
        $user = User::factory()->create();

        $suggestion = AiSuggestion::factory()->create([
            'viability_request_id' => $request->id,
            'created_by_user_id' => $user->id,
        ]);

        $this->assertTrue($suggestion->viabilityRequest->is($request));
        $this->assertTrue($suggestion->createdBy->is($user));
    }

    public function test_a_criacao_da_sugestao_e_auditada(): void
    {
        $suggestion = AiSuggestion::factory()->create();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => AiSuggestion::class,
            'subject_id' => $suggestion->id,
            'event' => 'created',
        ]);
    }
}
