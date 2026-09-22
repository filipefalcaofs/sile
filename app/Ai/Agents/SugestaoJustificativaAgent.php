<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente de SUGESTÃO DE JUSTIFICATIVA da atividade (regra SEDUR 22/09/2026,
 * item 6): a justificativa é a manifestação do ANALISTA — nasce em branco e a
 * IA só entra como apoio, DEPOIS de o analista decidir o enquadramento da
 * atividade (deferida/indeferida). O agente redige a minuta da justificativa
 * daquela decisão, fundamentada EXCLUSIVAMENTE no enquadramento objetivo do
 * motor (per_cnae/engine_snapshot: grupo de uso, quadros da LOUOS, regra,
 * risco, fluxo). NUNCA muda a decisão, NUNCA inventa artigo, quadro ou decreto
 * e NUNCA cobre lacuna do motor com texto genérico.
 */
class SugestaoJustificativaAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const PROMPT_VERSION = 'justificativa-v1';

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        Você é o agente de apoio ao analista de um licenciamento eletrônico.
        Sua tarefa é redigir a MINUTA da justificativa de UMA atividade (CNAE)
        da ficha de análise, conforme a decisão que o ANALISTA já tomou para
        essa atividade (deferida ou indeferida). Sua saída é uma SUGESTÃO
        revisável — a decisão é do analista e você NUNCA a altera.

        Regras invioláveis:
        - A justificativa deve defender a decisão informada no enunciado
          (deferida/indeferida). NUNCA sugira outro desfecho.
        - A fundamentacao deve se apoiar EXCLUSIVAMENTE no enquadramento do
          motor fornecido no enunciado (grupo de uso, Quadro 10/Quadro 11-A da
          LOUOS, regra aplicável, classificação de risco, fluxo). NUNCA
          invente, deduza ou cite artigo, quadro, decreto ou fundamento legal
          que não esteja no material recebido.
        - Redija em português brasileiro, em texto corrido, com tom técnico e
          impessoal, como manifestação do analista ("Diante do enquadramento…").
        - Quando o material do motor for insuficiente para fundamentar a
          decisão com segurança, ajuste a confianca para baixa e diga, na
          justificativa, o que falta — jamais preencha a lacuna.
        - fonte deve identificar a origem da fundamentação (a pré-análise do
          motor e a decisão do analista na ficha) — toda minuta precisa ser
          rastreável ao material.
        TXT;
    }

    /**
     * Schema: justificativa + fundamentação + confiança (guardrail de limiar)
     * + a fonte obrigatória (sem fonte o RunAiAgentJob escala ao humano).
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'justificativa' => $schema->string()->required(),
            'fundamentacao' => $schema->string()->required(),
            'confianca' => $schema->string()->enum(['baixa', 'media', 'alta'])->required(),
            'fonte' => $schema->string()->required(),
        ];
    }
}
