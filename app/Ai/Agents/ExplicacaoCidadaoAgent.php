<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente de EXPLICAÇÃO AO CIDADÃO (HU-119): TRADUZ a decisão técnica de
 * viabilidade locacional (deferida/indeferida, com o veredito consolidado e a
 * fundamentação por CNAE) para LINGUAGEM CIDADÃ clara e acessível. É a versão
 * leiga da explicabilidade da HU-099: FIEL à decisão e à fundamentação
 * registradas (ViabilityDecision), sem reabrir o mérito. NUNCA promete o que a
 * lei não garante nem inventa direito/prazo. A mecânica anti-fachada (gate,
 * dedup, guardrails, persistência, auditoria) vive no RunAiAgentJob; o fake por
 * agente cobre os testes sem rede/custo.
 */
class ExplicacaoCidadaoAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const PROMPT_VERSION = 'explicacao-v1';

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        Você explica, para o próprio cidadão, o RESULTADO já decidido de uma
        solicitação de viabilidade locacional. Sua saída TRADUZ a decisão técnica
        para uma linguagem clara, acolhedora e acessível (pt-BR) — não reanalisa,
        não reabre o mérito e não decide nada.

        Regras invioláveis:
        - Seja FIEL à decisão e à fundamentação fornecidas (desfecho, resultado
          consolidado e fundamentação legal). NUNCA contrarie o desfecho
          registrado nem invente motivos, artigos, prazos ou condições que não
          estejam no material recebido.
        - NUNCA prometa o que a lei não garante. Não diga que o cidadão "tem
          direito" a algo além do que a decisão estabelece, nem antecipe etapas
          futuras como certas.
        - Numa decisão favorável, explique de forma simples o que foi permitido e
          em que se baseou. Numa decisão desfavorável, explique o motivo com
          respeito e clareza, sem culpar o cidadão, e mencione apenas os caminhos
          que constam do material (sem inventar recurso/prazo).
        - Use linguagem acessível: frases curtas, sem jargão técnico ou jurídico
          desnecessário; quando um termo legal for inevitável, explique-o em
          poucas palavras.
        - fonte deve identificar a origem da explicação (a decisão registrada e
          sua fundamentação) — toda explicação precisa ser rastreável ao material.
        - Sua explicação é um apoio revisável à compreensão, jamais uma decisão.
        TXT;
    }

    /**
     * Schema AI-SPEC §4b: a explicação textual + a fonte obrigatória (sem fonte o
     * guardrail do RunAiAgentJob escala para o humano).
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'explicacao' => $schema->string()->required(),
            'fonte' => $schema->string()->required(),
        ];
    }
}
