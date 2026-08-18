<?php

namespace App\Services\Louos;

use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use RuntimeException;
use SplFileObject;

/**
 * Import real do Quadro 7 da LOUOS (enquadramento por área — HU-015/HU-038) a
 * partir do CSV versionado em database/data/louos/, espelhando
 * RiscoMunicipalImportService. As faixas ficam ligadas a uma versão de regra
 * (rule_version_id, domínio louos_quadro7) — dado versionado, nunca código.
 *
 * PROVENIÊNCIA: seed DERIVADO da Lei nº 9.148/2016 (Quadro 7) combinada ao
 * modelo "Enquadramento TVL" do SAPS legado (atividade → faixa de área →
 * grupo/subgrupo de uso: ex. minimercado até 350 m² = nR1, acima = nR2;
 * escritório até 1.250 m² = nR1, acima = nR2). É SUBSTITUÍVEL pela planilha
 * oficial do Quadro 7 da SEDUR quando entregue — muda a carga, não a lógica.
 *
 * Idempotente: upsert por (rule_version_id, cnae_code, area_min); o upsert NÃO
 * toca 'observacao' — anotações dos mantenedores (05-06) sobrevivem a
 * re-imports. As faixas de um mesmo CNAE são validadas como NÃO sobrepostas: um
 * CNAE com sobreposição é rejeitado por inteiro (relatório), nunca inserido
 * parcialmente.
 */
class LouosQuadro7ImportService
{
    private const EXPECTED_HEADER = [
        'cnae',
        'grupo',
        'subgrupo',
        'area_min',
        'area_max',
        'observacao',
    ];

    /**
     * @return array{lidos: int, importados: int, atualizados: int, rejeitados: array<int, string>, cnaes_distintos: int, total_faixas: int}
     */
    public function import(RuleVersion $version, string $csvPath): array
    {
        $file = new SplFileObject($csvPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        $header = null;
        $rejected = [];
        $read = 0;

        // Faixas válidas agrupadas por CNAE — a não-sobreposição só pode ser
        // avaliada com todas as faixas do CNAE em mãos.
        $porCnae = [];

        foreach ($file as $line) {
            if ($line === false || $line === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) $value), $line);

                if ($header !== self::EXPECTED_HEADER) {
                    throw new RuntimeException(
                        "Cabeçalho inesperado em {$csvPath}: esperado cnae,grupo,subgrupo,area_min,area_max,observacao (Quadro 7 da Lei 9.148/2016).",
                    );
                }

                continue;
            }

            $read++;

            if (count($line) !== count(self::EXPECTED_HEADER)) {
                $rejected[] = sprintf('linha %d: número de colunas inválido', $read);

                continue;
            }

            $data = array_combine(
                self::EXPECTED_HEADER,
                array_map(fn ($value) => trim((string) $value), $line),
            );

            $originalCode = $data['cnae'];
            $code = preg_replace('/\D/', '', $originalCode);

            if (preg_match('/^\d{7}$/', $code) !== 1) {
                $rejected[] = sprintf("código '%s' inválido (esperado o padrão DDDD-D/SS)", $originalCode);

                continue;
            }

            if ($data['grupo'] === '') {
                $rejected[] = sprintf("código '%s': grupo de uso é obrigatório", $originalCode);

                continue;
            }

            if (! is_numeric($data['area_min'])) {
                $rejected[] = sprintf("código '%s': area_min inválida ('%s')", $originalCode, $data['area_min']);

                continue;
            }

            $areaMin = (float) $data['area_min'];

            if ($data['area_max'] === '') {
                $areaMax = null;
            } elseif (! is_numeric($data['area_max'])) {
                $rejected[] = sprintf("código '%s': area_max inválida ('%s')", $originalCode, $data['area_max']);

                continue;
            } else {
                $areaMax = (float) $data['area_max'];
            }

            if ($areaMax !== null && $areaMin > $areaMax) {
                $rejected[] = sprintf(
                    "código '%s': area_min (%s) maior que area_max (%s)",
                    $originalCode,
                    $data['area_min'],
                    $data['area_max'],
                );

                continue;
            }

            $porCnae[$code][] = [
                'rule_version_id' => $version->getKey(),
                'cnae_code' => $code,
                'grupo' => $data['grupo'],
                'subgrupo' => $data['subgrupo'] === '' ? null : $data['subgrupo'],
                'area_min' => $areaMin,
                'area_max' => $areaMax,
                'observacao' => $data['observacao'] === '' ? null : $data['observacao'],
                'cnae_original' => $originalCode,
            ];
        }

        $rows = [];

        foreach ($porCnae as $faixas) {
            $conflito = $this->faixaSobreposta($faixas);

            if ($conflito !== null) {
                $rejected[] = sprintf(
                    "código '%s': faixas de área sobrepostas (%s)",
                    $faixas[0]['cnae_original'],
                    $conflito,
                );

                continue;
            }

            foreach ($faixas as $faixa) {
                unset($faixa['cnae_original']);
                $rows[] = $faixa;
            }
        }

        $existing = LouosQuadro7Faixa::query()
            ->where('rule_version_id', $version->getKey())
            ->get(['cnae_code', 'area_min'])
            ->map(fn (LouosQuadro7Faixa $faixa): string => $this->chave($faixa->cnae_code, (float) $faixa->area_min))
            ->flip();

        $imported = 0;
        $updated = 0;

        foreach ($rows as $row) {
            isset($existing[$this->chave($row['cnae_code'], $row['area_min'])]) ? $updated++ : $imported++;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            LouosQuadro7Faixa::query()->upsert(
                $chunk,
                ['rule_version_id', 'cnae_code', 'area_min'],
                ['grupo', 'subgrupo', 'area_max'],
            );
        }

        return [
            'lidos' => $read,
            'importados' => $imported,
            'atualizados' => $updated,
            'rejeitados' => $rejected,
            'cnaes_distintos' => count($rows) === 0 ? 0 : count(array_unique(array_column($rows, 'cnae_code'))),
            'total_faixas' => count($rows),
        ];
    }

    /**
     * Detecta sobreposição entre as faixas de um mesmo CNAE: ordena por area_min
     * e acusa quando uma faixa começa antes do fim da anterior. area_max nula =
     * sem limite superior, logo só pode ser a última faixa. Retorna a descrição
     * do conflito ou null quando as faixas são contíguas/disjuntas.
     *
     * @param  array<int, array<string, mixed>>  $faixas
     */
    private function faixaSobreposta(array $faixas): ?string
    {
        usort($faixas, fn (array $a, array $b): int => $a['area_min'] <=> $b['area_min']);

        $total = count($faixas);

        for ($i = 1; $i < $total; $i++) {
            $anterior = $faixas[$i - 1];
            $atual = $faixas[$i];

            if ($anterior['area_max'] === null) {
                return sprintf('faixa sem limite superior precede outra a partir de %s m²', $atual['area_min']);
            }

            if ($atual['area_min'] < $anterior['area_max']) {
                return sprintf('faixa a partir de %s m² invade o limite %s m² da anterior', $atual['area_min'], $anterior['area_max']);
            }
        }

        return null;
    }

    private function chave(string $cnaeCode, float $areaMin): string
    {
        return $cnaeCode.'|'.$areaMin;
    }
}
