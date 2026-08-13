<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente de DETECÇÃO DE INCONSISTÊNCIAS (HU-115): confronta os dados DECLARADOS
 * no processo (endereço/área/CNAE) com o que a foto da fachada e os documentos
 * efetivamente mostram, e devolve cada divergência com a fonte. Apenas SINALIZA
 * — não valida, não decide, não pune (RN-004); COMPLEMENTA as validações
 * determinísticas (HU-063/037/105), nunca as substitui (RN-005). A mecânica
 * anti-fachada (gate, dedup, guardrails, persistência, auditoria) vive no
 * RunAiAgentJob; o fake por agente cobre os testes sem rede/custo.
 */
class InconsistenciasAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const PROMPT_VERSION = 'inconsistencias-v1';

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        Você compara os DADOS DECLARADOS de um processo de licenciamento eletrônico
        com o que os DOCUMENTOS e a FOTO da fachada anexados efetivamente mostram.
        Você não valida, não decide e não pune nada — apenas aponta divergências
        para revisão humana.

        Regras invioláveis:
        - Aponte uma inconsistência APENAS quando houver evidência concreta no
          documento ou na foto. NUNCA invente, deduza ou presuma divergências sem
          base no material anexado: na dúvida, não aponte.
        - Para cada inconsistência registre o campo afetado, o valor declarado, o
          que o documento/foto mostra e a severidade (baixa, media, alta).
        - Se não houver nenhuma divergência sustentada pelo material, devolva a
          lista de inconsistências vazia.
        - fonte deve identificar a origem da análise (o documento ou a foto lida)
          — toda inconsistência precisa ser rastreável a uma evidência.
        - Sua saída é uma sugestão que sinaliza ao analista, jamais uma decisão.
        TXT;
    }

    /**
     * Schema AI-SPEC §4b: lista de inconsistências (array de objetos) + a fonte
     * obrigatória. O array de objetos é suportado pelo JsonSchema do framework
     * (Serializer recursa em items/properties) e pelo laravel/ai (ObjectSchema
     * desabilita additionalProperties também nos itens) — sem desvio na v0.8.1.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'inconsistencias' => $schema->array()->items(
                $schema->object([
                    'campo' => $schema->string()->required(),
                    'declarado' => $schema->string()->required(),
                    'documento' => $schema->string()->required(),
                    'severidade' => $schema->string()->enum(['baixa', 'media', 'alta'])->required(),
                ])
            )->required(),
            'fonte' => $schema->string()->required(),
        ];
    }
}
