<?php

namespace Tests\Fixtures\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente estruturado mínimo de teste para exercitar a MECÂNICA do RunAiAgentJob
 * (gate, dedup, guardrails, persistência, auditoria) sem acoplar a uma função
 * real. Saída { valor, confianca, fonte } — o suficiente para os guardrails de
 * confiança e fonte. Fake por agente nos testes (sem rede).
 */
class ProbeStructuredAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const PROMPT_VERSION = 'probe-v1';

    public function instructions(): Stringable|string
    {
        return 'Agente de teste da mecânica de execução de IA.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'valor' => $schema->string()->required(),
            'confianca' => $schema->string()->enum(['baixa', 'media', 'alta'])->required(),
            'fonte' => $schema->string()->required(),
        ];
    }
}
