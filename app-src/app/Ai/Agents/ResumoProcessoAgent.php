<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente de RESUMO DO PROCESSO para o analista (HU-117): sintetiza a pré-análise
 * do motor (engine_snapshot/per_cnae), as inconsistências sinalizadas (Onda 1),
 * as pendências e os dados locacionais num resumo FIEL do que já existe no
 * processo. Apenas resume — não valida, não decide e JAMAIS afirma o desfecho
 * (AI-SPEC Failure Mode #1). A mecânica anti-fachada (gate, dedup, guardrails,
 * persistência, auditoria) vive no RunAiAgentJob; o fake por agente cobre os
 * testes sem rede/custo.
 */
class ResumoProcessoAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const PROMPT_VERSION = 'resumo-processo-v1';

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        Você sintetiza, para o analista, o estado de um processo de licenciamento
        eletrônico. Sua saída é um RESUMO de apoio à leitura — você não valida,
        não decide e não antecipa o desfecho da análise.

        Regras invioláveis:
        - Resuma fielmente APENAS o que o material do processo fornece (pré-análise
          do motor, enquadramento por CNAE, inconsistências sinalizadas, pendências
          e dados locacionais). NUNCA invente fatos, números ou conclusões ausentes.
        - NÃO afirme que o processo será deferido ou indeferido, nem recomende a
          decisão. A tendência do motor pode ser mencionada como tendência, sempre
          atribuída ao motor — nunca como veredito.
        - Em pontos_chave, destaque o que merece atenção do analista (divergências,
          pendências, inconsistências), sem julgar.
        - fonte deve identificar a origem do resumo (pré-análise do motor e ficha do
          processo) — toda síntese precisa ser rastreável ao material lido.
        - Seu resumo é um apoio revisável, jamais uma decisão.
        TXT;
    }

    /**
     * Schema AI-SPEC §4b: o resumo textual + os pontos-chave opcionais + a fonte
     * obrigatória (sem fonte o guardrail do RunAiAgentJob escala para o humano).
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resumo' => $schema->string()->required(),
            'pontos_chave' => $schema->array()->items($schema->string()),
            'fonte' => $schema->string()->required(),
        ];
    }
}
