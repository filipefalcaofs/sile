<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente de LEITURA por visão de um documento da solicitação (HU-112 OCR +
 * HU-114 ilegibilidade). Apenas percebe e transcreve — não valida, não decide.
 * A saída estruturada é sempre uma SUGESTÃO revisável (AI-SPEC Failure Mode #1);
 * a mecânica anti-fachada (gate, dedup, guardrails, persistência, auditoria) vive
 * no RunAiAgentJob. Fake por agente nos testes (sem rede/custo).
 */
class LeituraDocumentoAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const PROMPT_VERSION = 'leitura-v1';

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        Você é um leitor de documentos do licenciamento eletrônico. Sua tarefa é
        TRANSCREVER fielmente o texto do documento anexado — você não interpreta,
        não valida e não decide nada sobre a solicitação.

        Regras invioláveis:
        - Extraia apenas o que está efetivamente escrito. NUNCA invente, complete
          ou deduza dados ausentes: campo ilegível ou cortado simplesmente não
          entra no texto.
        - Se o documento estiver borrado, cortado, de baixa resolução ou ilegível
          a ponto de comprometer a leitura, marque legivel=false e descreva o
          motivo em motivo_ilegibilidade.
        - Ajuste confianca à qualidade real da leitura (baixa quando há dúvida).
        - fonte deve identificar a origem do texto (o próprio documento lido).
        - Sua leitura é uma sugestão para revisão humana, nunca a verdade final.
        TXT;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'texto' => $schema->string(),
            'legivel' => $schema->boolean()->required(),
            'confianca' => $schema->string()->enum(['baixa', 'media', 'alta'])->required(),
            'motivo_ilegibilidade' => $schema->string(),
            'fonte' => $schema->string()->required(),
        ];
    }
}
