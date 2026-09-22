<?php

namespace App\Services\Geo;

/**
 * Resultado imutável da validação de localização por sobreposição (HU-037
 * RN-004). Contrato snake_case do JSON consumido pela UI (04-07).
 *
 * - `status` ∈ {validado, alerta_sobreposicao, indisponivel}.
 * - `indisponivel` carrega o `motivo` (base de lotes pendente SEDUR) e nunca um
 *   percentual fabricado — degradação comunicada, sem fachada.
 * - `limiar` é o percentual mínimo administrável (geo.validacao.sobreposicao_minima).
 * - `alerta` é verdadeiro quando a sobreposição fica abaixo do limiar (insumo do
 *   alerta registrado para o analista).
 */
final readonly class LocationValidationResult
{
    public function __construct(
        public string $status,
        public ?float $sobreposicaoPercentual,
        public int $limiar,
        public ?string $motivo,
        public bool $alerta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'sobreposicao_percentual' => $this->sobreposicaoPercentual,
            'limiar' => $this->limiar,
            'motivo' => $this->motivo,
            'alerta' => $this->alerta,
        ];
    }
}
