<?php

namespace App\Services\Solicitacao;

use App\Enums\Fluxo;

/**
 * Insumo imutável da resolução de viabilidade por solicitação (09-04): o
 * resultado por CNAE (com o ConsultaViabilidadeResult bruto para a decisão ler
 * fresco), a tendência consolidada (pior caso) e as versões representativas das
 * regras. NÃO carrega decisão nem é persistido por si — é consumido pela
 * simulação orientativa (snapshot, Fase 8) e pela decisão autoritativa (09-05),
 * que reexecuta o resolver e nunca confia no snapshot pré-protocolo (RN-001).
 *
 * Cada item de `por_cnae` traz: `cnae`, `cnae_formatado`, `is_primary`,
 * `tendencia` (veredito locacional propagado), `tendencia_label`, `fluxo`
 * (encaminhamento do motor de risco: 'expresso'|'analise'), `consulta` (o
 * ConsultaViabilidadeResult — objeto) e `consulta_array` (toArray para o
 * snapshot).
 */
final readonly class ResolvedViability
{
    /**
     * @param  list<array<string, mixed>>  $por_cnae  Resultado por CNAE (com ConsultaViabilidadeResult e seu toArray).
     * @param  string  $consolidado  Pior caso (ResultadoViabilidade) entre os CNAEs.
     * @param  array<string, array<string, ?string>>  $rules_versions  Versões representativas (território/louos/risco).
     * @param  array{lat: float, lng: float}|null  $ponto  Centroide do polígono (null sem polígono).
     * @param  float|null  $area_m2  Área útil declarada.
     */
    public function __construct(
        public array $por_cnae,
        public string $consolidado,
        public array $rules_versions,
        public ?array $ponto,
        public ?float $area_m2,
    ) {}

    /**
     * Elegibilidade ao fluxo expresso (HU-073): elegível somente se TODOS os
     * CNAEs forem encaminhados ao expresso pelo motor de risco — qualquer
     * 'analise' (alto/gatilho/não classificado) torna o conjunto inelegível
     * (semi-expresso). Sem CNAEs não há nada a decidir → inelegível.
     */
    public function elegivelExpresso(): bool
    {
        if ($this->por_cnae === []) {
            return false;
        }

        foreach ($this->por_cnae as $item) {
            if (($item['fluxo'] ?? null) !== Fluxo::Expresso->value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Snapshot orientativo no shape EXATO que a simulação (Fase 8) persiste:
     * `ponto`, `area_m2` e `por_cnae` com a `consulta` serializada (toArray). O
     * objeto ConsultaViabilidadeResult fica fora do registro — ele só serve à
     * decisão fresca em memória.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'ponto' => $this->ponto,
            'area_m2' => $this->area_m2,
            'por_cnae' => array_map(
                static fn (array $item): array => [
                    'cnae' => $item['cnae'],
                    'cnae_formatado' => $item['cnae_formatado'],
                    'is_primary' => $item['is_primary'],
                    'tendencia' => $item['tendencia'],
                    'tendencia_label' => $item['tendencia_label'],
                    'consulta' => $item['consulta_array'],
                ],
                $this->por_cnae,
            ),
        ];
    }
}
