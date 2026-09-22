<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente de CLASSIFICAÇÃO documental (HU-113): sugere a categoria do documento
 * anexado e, quando a exigência esperada é informada, sinaliza se o documento
 * corresponde a ela. Confronto é sempre ALERTA revisável — nunca rejeita o
 * protocolo (RN-005). A mecânica anti-fachada vive no RunAiAgentJob; o fake por
 * agente cobre os testes sem rede/custo.
 */
class ClassificacaoDocumentoAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const PROMPT_VERSION = 'classificacao-v1';

    /**
     * Categorias derivadas do catálogo DocumentRequirement (HU-067):
     * foto-fachada, termo-concessao e contrato-locacao, mais "outro" para o que
     * não se enquadra. A planilha oficial da SEDUR pode expandir a carga — a
     * lógica não muda.
     *
     * @var list<string>
     */
    public const CATEGORIAS = ['fachada', 'concessao_uso', 'contrato_locacao', 'outro'];

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        Você classifica o TIPO de um documento anexado ao licenciamento eletrônico.
        Você não valida nem decide nada sobre a solicitação — apenas sugere.

        Regras:
        - Escolha a categoria que melhor descreve o documento entre as opções do
          schema; use "outro" quando nenhuma se aplicar.
        - Quando a exigência esperada for informada no enunciado, indique em
          compativel_com_exigencia se o documento corresponde a essa exigência.
          Divergência é apenas um ALERTA para o analista, nunca uma rejeição.
        - Ajuste confianca à clareza real da classificação (baixa quando há dúvida).
        - fonte deve identificar a origem da análise (o próprio documento lido).
        TXT;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'categoria' => $schema->string()->enum(self::CATEGORIAS)->required(),
            'confianca' => $schema->string()->enum(['baixa', 'media', 'alta'])->required(),
            'compativel_com_exigencia' => $schema->boolean(),
            'fonte' => $schema->string()->required(),
        ];
    }
}
