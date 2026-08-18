<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente de SUGESTÃO DE MINUTA DE PARECER (HU-118) — Failure Mode #1 do AI-SPEC.
 * Redige uma minuta de APOIO ao analista a partir da pré-análise REAL do motor
 * (engine_snapshot/per_cnae/versões da LOUOS), apresentada SEMPRE como sugestão
 * revisável. NÃO decide o desfecho, NÃO conclui pela viabilidade e NUNCA inventa
 * artigo, quadro ou fundamento legal: a fundamentação cita apenas o que o motor
 * forneceu. A mecânica anti-fachada (gate, dedup, guardrails, persistência,
 * auditoria) vive no RunAiAgentJob; o fake por agente cobre os testes sem rede.
 */
class SugestaoParecerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const PROMPT_VERSION = 'parecer-v1';

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        Você redige uma MINUTA de parecer técnico para apoiar o analista de um
        licenciamento eletrônico. Sua saída é uma sugestão de rascunho — você NÃO
        decide o desfecho, NÃO conclui pela viabilidade e NÃO substitui o analista.

        Regras invioláveis:
        - A fundamentacao deve se apoiar EXCLUSIVAMENTE na pré-análise do motor
          fornecida no enunciado (enquadramento por CNAE, resultado consolidado,
          versões dos quadros da LOUOS). NUNCA invente, deduza ou cite artigo,
          quadro, decreto ou fundamento legal que não esteja no material recebido.
        - NÃO afirme que o processo está deferido ou indeferido. Apresente a minuta
          como proposta de redação a ser revisada, ajustada e validada pelo humano.
        - Quando o material do motor for insuficiente para fundamentar com
          segurança, ajuste a confianca para baixa e diga, na minuta, o que falta —
          jamais preencha a lacuna com texto inventado.
        - Ajuste confianca à robustez real do enquadramento (baixa em caso de
          dúvida, alta apenas quando o motor sustenta a redação).
        - fonte deve identificar a origem da fundamentação (a pré-análise do motor
          e a ficha do processo) — toda minuta precisa ser rastreável ao material.
        - Sua minuta é um apoio revisável, jamais uma decisão.
        TXT;
    }

    /**
     * Schema AI-SPEC §4b: minuta + fundamentação + confiança (para o guardrail de
     * limiar) + a fonte obrigatória (sem fonte o RunAiAgentJob escala ao humano).
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'minuta' => $schema->string()->required(),
            'fundamentacao' => $schema->string()->required(),
            'confianca' => $schema->string()->enum(['baixa', 'media', 'alta'])->required(),
            'fonte' => $schema->string()->required(),
        ];
    }
}
