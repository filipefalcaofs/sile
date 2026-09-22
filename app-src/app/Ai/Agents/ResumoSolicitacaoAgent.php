<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente de RESUMO DA SOLICITAÇÃO para o cidadão (HU-116), pré-protocolo:
 * sintetiza os dados DECLARADOS (empresa, atividades/CNAEs e imóvel) num resumo
 * de CONFERÊNCIA, para o requerente revisar o que informou antes de protocolar.
 * Apenas resume — não valida, não decide e JAMAIS afirma o desfecho ("vai ser
 * deferido/indeferido"), porque a viabilidade ainda nem foi analisada
 * (AI-SPEC Failure Mode #1). A mecânica anti-fachada (gate, dedup, guardrails,
 * persistência, auditoria) vive no RunAiAgentJob; o fake por agente cobre os
 * testes sem rede/custo.
 */
class ResumoSolicitacaoAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const PROMPT_VERSION = 'resumo-solicitacao-v1';

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        Você sintetiza, para o próprio cidadão, os dados que ele DECLAROU numa
        solicitação de viabilidade locacional. Sua saída é um RESUMO de
        conferência — ajuda o requerente a revisar o que informou antes de
        protocolar. Você não valida, não analisa e não decide.

        Regras invioláveis:
        - Resuma fielmente APENAS os dados declarados fornecidos (empresa,
          atividades/CNAEs e imóvel). NUNCA invente dados, números ou
          informações que não estejam no material recebido.
        - NUNCA afirme nem sugira o desfecho ("vai ser deferido", "será
          aprovado", "tende a ser indeferido"). A análise de viabilidade ainda
          não aconteceu — este é só um resumo dos dados informados.
        - Escreva em linguagem clara e acessível ao cidadão (pt-BR), sem jargão
          técnico ou jurídico desnecessário.
        - fonte deve identificar a origem do resumo (os dados declarados na
          solicitação) — toda síntese precisa ser rastreável ao material lido.
        - Seu resumo é um apoio de conferência revisável, jamais uma decisão.
        TXT;
    }

    /**
     * Schema AI-SPEC §4b: o resumo textual + a fonte obrigatória (sem fonte o
     * guardrail do RunAiAgentJob escala para o humano).
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resumo' => $schema->string()->required(),
            'fonte' => $schema->string()->required(),
        ];
    }
}
