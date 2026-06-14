<?php

namespace App\Services\Louos;

use App\Enums\ResultadoViabilidade;

/**
 * Resultado imutável do enquadramento da LOUOS (HU-038 a HU-046), espelhando
 * RiscoResult/TerritoryResult: cada Quadro é uma dimensão de shape estável
 * (contrato do motor 05-03+, da UI e do EP07+) e o consolidado carrega o
 * veredito fundamentado.
 *
 * - quadro7:  {status, grupo, subgrupo, motivo, versao_regra}
 * - quadro10: {status, permissao, condicionante_ref, motivo, versao_regra}
 * - quadro11/quadro11a: {status, condicoes, motivo, versao_regra}
 * - consolidado: {resultado, fundamentacao[], condicionantes[], motivo}
 * - versoes: versão de regra consultada por quadro (RN-002 — reprodução por época)
 *
 * `status` por dimensão ∈ {identificado, nao_encontrado, indisponivel}.
 * `indisponivel` carrega o `motivo` (ex.: zona pendente SEDUR) — degradação
 * honesta: sem o dado real o consolidado é `pendente`, NUNCA permitido/não
 * permitido inventado (anti-fachada).
 */
final readonly class EnquadramentoResult
{
    public const STATUS_IDENTIFICADO = 'identificado';

    public const STATUS_NAO_ENCONTRADO = 'nao_encontrado';

    public const STATUS_INDISPONIVEL = 'indisponivel';

    /**
     * @param  array<string, mixed>  $quadro7
     * @param  array<string, mixed>  $quadro10
     * @param  array<string, mixed>  $quadro11
     * @param  array<string, mixed>  $quadro11a
     * @param  array<string, mixed>  $consolidado
     * @param  array<string, ?string>  $versoes
     */
    public function __construct(
        public array $quadro7,
        public array $quadro10,
        public array $quadro11,
        public array $quadro11a,
        public array $consolidado,
        private array $versoes,
    ) {}

    /**
     * Versão de regra consultada em cada Quadro (RN-002) — insumo da auditoria e
     * da reprodução por época, paralelo a RiscoResult::versoes().
     *
     * @return array<string, ?string>
     */
    public function versoes(): array
    {
        return $this->versoes;
    }

    /**
     * Veredito consolidado (value de ResultadoViabilidade) — atalho ao
     * consolidado['resultado'].
     */
    public function resultado(): string
    {
        return $this->consolidado['resultado'];
    }

    /**
     * Verdadeiro quando o parecer ficou pendente de análise técnica — degradação
     * segura quando uma dimensão necessária está indisponível (ex.: zona).
     */
    public function pendente(): bool
    {
        return $this->resultado() === ResultadoViabilidade::Pendente->value;
    }

    /**
     * Contrato snake_case do resultado, consumido pelo motor (05-03+), pela UI e
     * pelo EP07+. Espelha RiscoResult::toArray/TerritoryResult::toArray.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'quadro7' => $this->quadro7,
            'quadro10' => $this->quadro10,
            'quadro11' => $this->quadro11,
            'quadro11a' => $this->quadro11a,
            'consolidado' => $this->consolidado,
            'versoes' => $this->versoes,
        ];
    }
}
