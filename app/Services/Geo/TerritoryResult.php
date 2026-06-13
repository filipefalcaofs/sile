<?php

namespace App\Services\Geo;

/**
 * Resultado imutável da identificação territorial por ponto (HU-031 a HU-035).
 * Cada dimensão é um array de shape estável (contrato do endpoint JSON 04-06 e
 * da UI 04-07):
 *
 * - bairro / zona / lote: {status, nome, propriedades, motivo, versao_camada}
 * - via: idem + {distancia_m}
 * - restricoes: {status, itens[], motivo, versao_camada} (itens: {nome, propriedades})
 *
 * `status` ∈ {identificado, nao_encontrado, indisponivel}. `indisponivel`
 * carrega o `motivo` (ex.: base de zoneamento/lotes pendente SEDUR) — nunca um
 * valor falso. `versao_camada` registra a versão consultada (RN-004 —
 * reprodução por época).
 */
final readonly class TerritoryResult
{
    /**
     * @param  array<string, mixed>  $bairro
     * @param  array<string, mixed>  $via
     * @param  array<string, mixed>  $zona
     * @param  array<string, mixed>  $lote
     * @param  array<string, mixed>  $restricoes
     */
    public function __construct(
        public array $bairro,
        public array $via,
        public array $zona,
        public array $lote,
        public array $restricoes,
    ) {}

    /**
     * Versão da camada consultada em cada dimensão (RN-004) — insumo da
     * auditoria e da reprodução.
     *
     * @return array<string, ?string>
     */
    public function versoes(): array
    {
        return [
            'bairro' => $this->bairro['versao_camada'] ?? null,
            'via' => $this->via['versao_camada'] ?? null,
            'zona' => $this->zona['versao_camada'] ?? null,
            'lote' => $this->lote['versao_camada'] ?? null,
            'restricoes' => $this->restricoes['versao_camada'] ?? null,
        ];
    }

    /**
     * Status resumido por dimensão — insumo da auditoria e da UI.
     *
     * @return array<string, string>
     */
    public function resumoStatus(): array
    {
        return [
            'bairro' => (string) $this->bairro['status'],
            'via' => (string) $this->via['status'],
            'zona' => (string) $this->zona['status'],
            'lote' => (string) $this->lote['status'],
            'restricoes' => (string) $this->restricoes['status'],
        ];
    }

    /**
     * Contrato snake_case do JSON consumido por 04-06 (endpoint) e 04-07 (mapa).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bairro' => $this->bairro,
            'via' => $this->via,
            'zona' => $this->zona,
            'lote' => $this->lote,
            'restricoes' => $this->restricoes,
        ];
    }
}
