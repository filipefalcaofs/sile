<?php

namespace App\Services\Analise;

use App\Models\AnalysisRecord;

/**
 * Diff entre duas revisões da ficha de análise (HU-135 RN-007): compara campos
 * de topo (parecer, condicionantes, vagas) e o per_cnae por CNAE (status sugerido
 * × escolhido, condicionantes, justificativa) e devolve SÓ o que mudou, na forma
 * {campo: {de, para}} — campos iguais não aparecem. Função pura e testável: não
 * toca banco nem container; serve o painel de histórico da ficha (10-17).
 */
class AnalysisRecordDiff
{
    /** Campos de topo comparados entre as revisões. */
    private const CAMPOS_TOPO = ['parecer', 'conditions', 'parking', 'analysis_reasons', 'address_confirmed'];

    /** Campos comparados por CNAE (espelham a ficha SAPS). */
    private const CAMPOS_PER_CNAE = ['status_sugerido', 'status_escolhido', 'condicionantes', 'justificativa'];

    /**
     * @return array<string, mixed> só os campos que mudaram de $a para $b
     */
    public function between(AnalysisRecord $a, AnalysisRecord $b): array
    {
        $diff = [];

        foreach (self::CAMPOS_TOPO as $campo) {
            $de = $a->{$campo};
            $para = $b->{$campo};

            if ($de !== $para) {
                $diff[$campo] = ['de' => $de, 'para' => $para];
            }
        }

        $perCnae = $this->diffPerCnae($a->per_cnae, $b->per_cnae);

        if ($perCnae !== []) {
            $diff['per_cnae'] = $perCnae;
        }

        return $diff;
    }

    /**
     * Diferenças por CNAE: para cada CNAE presente numa das revisões, só os
     * sub-campos que mudaram. CNAEs sem mudança não entram.
     *
     * @param  array<int, mixed>|null  $a
     * @param  array<int, mixed>|null  $b
     * @return array<string, array<string, array{de: mixed, para: mixed}>>
     */
    private function diffPerCnae(?array $a, ?array $b): array
    {
        $mapA = $this->indexByCnae($a);
        $mapB = $this->indexByCnae($b);

        $cnaes = array_unique(array_merge(array_keys($mapA), array_keys($mapB)));

        $resultado = [];

        foreach ($cnaes as $cnae) {
            $itemA = $mapA[$cnae] ?? [];
            $itemB = $mapB[$cnae] ?? [];

            $campos = [];

            foreach (self::CAMPOS_PER_CNAE as $campo) {
                $de = $itemA[$campo] ?? null;
                $para = $itemB[$campo] ?? null;

                if ($de !== $para) {
                    $campos[$campo] = ['de' => $de, 'para' => $para];
                }
            }

            if ($campos !== []) {
                $resultado[(string) $cnae] = $campos;
            }
        }

        return $resultado;
    }

    /**
     * Indexa a lista per_cnae pelo código do CNAE para a comparação item a item.
     *
     * @param  array<int, mixed>|null  $perCnae
     * @return array<string, array<string, mixed>>
     */
    private function indexByCnae(?array $perCnae): array
    {
        $map = [];

        foreach ($perCnae ?? [] as $item) {
            if (is_array($item) && isset($item['cnae'])) {
                $map[(string) $item['cnae']] = $item;
            }
        }

        return $map;
    }
}
