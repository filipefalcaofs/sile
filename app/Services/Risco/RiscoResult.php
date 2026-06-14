<?php

namespace App\Services\Risco;

use App\Enums\Fluxo;

/**
 * Resultado imutável da classificação de risco (HU-047 a HU-051), espelhando
 * TerritoryResult: cada dimensão é um array de shape estável (contrato do
 * motor 06-05, da UI 06-08 e do EP07+). As dimensões municipal e sanitária são
 * SEPARADAS (RN-009) e o encaminhamento é a decisão de roteamento auditada.
 *
 * - municipal: {status, nivel, nivel_label, condicionantes, versao_regras}
 * - sanitario: {status, nivel_original, nivel_final, reclassificado(bool),
 *   condicionantes_perguntas[], versao_regras}
 * - encaminhamento: {fluxo, dimensao_decisiva, motivo, gatilhos_acionados[]}
 * - fundamentacao: referências legais da decisão
 * - versoes: versão de regra consultada por dimensão (RN-002/RN-004)
 *
 * `status` por dimensão ∈ {classificado, nao_classificado}. `nao_classificado`
 * ocorre quando o CNAE não está na tabela vigente (FA-02): o motor encaminha
 * para análise com o motivo — NUNCA inventa um nível inexistente.
 */
final readonly class RiscoResult
{
    public const STATUS_CLASSIFICADO = 'classificado';

    public const STATUS_NAO_CLASSIFICADO = 'nao_classificado';

    /**
     * @param  array<string, mixed>  $municipal
     * @param  array<string, mixed>  $sanitario
     * @param  array<string, mixed>  $encaminhamento
     * @param  list<string>  $fundamentacao
     * @param  array<string, ?string>  $versoes
     */
    public function __construct(
        public array $municipal,
        public array $sanitario,
        public array $encaminhamento,
        public array $fundamentacao,
        public array $versoes,
    ) {}

    /**
     * Versão de regra consultada em cada dimensão (RN-002/RN-004) — insumo da
     * auditoria e da reprodução por época, paralelo a TerritoryResult::versoes().
     *
     * @return array<string, ?string>
     */
    public function versoes(): array
    {
        return $this->versoes;
    }

    /**
     * Verdadeiro quando o encaminhamento foi para análise técnica (alto risco,
     * gatilho acionado, CNAE não classificado ou nível ausente no mapa) —
     * degradação segura: o que não é expresso, é análise.
     */
    public function encaminhadoParaAnalise(): bool
    {
        return ($this->encaminhamento['fluxo'] ?? null) === Fluxo::Analise->value;
    }

    /**
     * Contrato snake_case do resultado, consumido pelo motor (06-05), pela UI
     * (06-08) e pelo EP07+. Espelha TerritoryResult::toArray.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'municipal' => $this->municipal,
            'sanitario' => $this->sanitario,
            'encaminhamento' => $this->encaminhamento,
            'fundamentacao' => $this->fundamentacao,
            'versoes' => $this->versoes,
        ];
    }
}
