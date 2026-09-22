<?php

namespace App\Services\Louos;

use App\Enums\ResultadoViabilidade;

/**
 * Resultado imutável do enquadramento da LOUOS (HU-038 a HU-046), espelhando
 * RiscoResult/TerritoryResult: o ramo da planilha e cada Quadro territorial
 * são dimensões de shape estável (contrato do motor, da UI e do EP07+) e o
 * consolidado carrega o veredito fundamentado.
 *
 * - enquadramento: {status, grupo, subgrupo, codigo_louos, motivo, versao_regra}
 * - quadro10: {status, permissao, condicionante_ref, motivo, versao_regra}
 * - quadro11a: {status, condicoes, motivo, versao_regra}
 * - consolidado: {resultado, fundamentacao[], condicionantes[], motivo}
 * - versoes: versão de regra consultada por domínio (RN-002)
 *
 * `status` por dimensão ∈ {identificado, nao_encontrado, indisponivel}.
 * `indisponivel` carrega o `motivo` — degradação honesta: sem o dado real o
 * consolidado é `pendente`, NUNCA permitido/não permitido inventado.
 */
final readonly class EnquadramentoResult
{
    public const STATUS_IDENTIFICADO = 'identificado';

    public const STATUS_NAO_ENCONTRADO = 'nao_encontrado';

    public const STATUS_INDISPONIVEL = 'indisponivel';

    /**
     * @param  array<string, mixed>  $enquadramento
     * @param  array<string, mixed>  $quadro10
     * @param  array<string, mixed>  $quadro11a
     * @param  array<string, mixed>  $consolidado
     * @param  array<string, ?string>  $versoes
     */
    public function __construct(
        public array $enquadramento,
        public array $quadro10,
        public array $quadro11a,
        public array $consolidado,
        private array $versoes,
    ) {}

    /**
     * @return array<string, ?string>
     */
    public function versoes(): array
    {
        return $this->versoes;
    }

    public function resultado(): string
    {
        return $this->consolidado['resultado'];
    }

    public function pendente(): bool
    {
        return $this->resultado() === ResultadoViabilidade::Pendente->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enquadramento' => $this->enquadramento,
            'quadro10' => $this->quadro10,
            'quadro11a' => $this->quadro11a,
            'consolidado' => $this->consolidado,
            'versoes' => $this->versoes,
        ];
    }
}
