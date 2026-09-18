<?php

namespace App\Services\Louos;

use RuntimeException;
use SplFileObject;

/**
 * Converte a planilha operacional CNAE→LOUOS (`cnae-enquadramentos.csv`,
 * 20.08.26) no CSV do importador do Quadro 7. O PDF do Quadro 7 classifica
 * usos, não CNAE: esta ponte escolhe UM uso por CNAE (o motor não admite
 * faixas sobrepostas).
 *
 * Escolha: descarta o escritório genérico 07.12.13 quando há uso específico;
 * entre os restantes prefere prefixo 07, depois 08A, 08 e 09. 9900-8/00 fica
 * de fora (divergência do catálogo CNAE-Subclasses 2.3).
 */
class LouosQuadro7EnquadramentosConverter
{
    /** @var list<string> */
    private const PREFIXO_PREFERENCIA = ['07', '08A', '08', '09'];

    public const ESCRITORIO_GENERICO = '07.12.13';

    public const CNAE_FORA_DO_CATALOGO = '9900800';

    /**
     * @return list<array{cnae: string, grupo: string, subgrupo: string, area_min: string, area_max: string, observacao: string}>
     */
    public function converter(string $csvPath): array
    {
        $porCnae = $this->lerPlanilha($csvPath);
        $faixas = [];

        foreach ($porCnae as $linhas) {
            $escolhida = $this->escolherUso($linhas);

            if ($escolhida === null) {
                continue;
            }

            foreach ($this->faixasDaLinha($escolhida) as $faixa) {
                $faixas[] = $faixa;
            }
        }

        return $faixas;
    }

    /**
     * @param  list<array{cnae: string, grupo: string, subgrupo: string, area_min: string, area_max: string, observacao: string}>  $faixas
     */
    public function escreverCsv(array $faixas, string $destino): void
    {
        $handle = fopen($destino, 'w');

        if ($handle === false) {
            throw new RuntimeException("Não foi possível gravar {$destino}.");
        }

        fputcsv($handle, ['cnae', 'grupo', 'subgrupo', 'area_min', 'area_max', 'observacao'], ',', '"', '\\');

        foreach ($faixas as $faixa) {
            fputcsv($handle, [
                $faixa['cnae'],
                $faixa['grupo'],
                $faixa['subgrupo'],
                $faixa['area_min'],
                $faixa['area_max'],
                $faixa['observacao'],
            ], ',', '"', '\\');
        }

        fclose($handle);
    }

    /**
     * @return array<string, list<array<string, string>>>
     */
    private function lerPlanilha(string $csvPath): array
    {
        $file = new SplFileObject($csvPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        $header = null;
        $porCnae = [];

        foreach ($file as $line) {
            if ($line === false || $line === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) $value), $line);

                continue;
            }

            if (count($line) < count($header)) {
                continue;
            }

            $data = array_combine($header, array_map(fn ($value) => trim((string) $value), $line));

            if ($data === false) {
                continue;
            }

            $code = preg_replace('/\D/', '', $data['cnae'] ?? '');

            if ($code === self::CNAE_FORA_DO_CATALOGO) {
                continue;
            }

            if (preg_match('/^\d{7}$/', $code) !== 1) {
                continue;
            }

            $porCnae[$code][] = $data;
        }

        return $porCnae;
    }

    /**
     * @param  list<array<string, string>>  $linhas
     * @return array<string, string>|null
     */
    private function escolherUso(array $linhas): ?array
    {
        $especificos = array_values(array_filter(
            $linhas,
            fn (array $linha): bool => ($linha['codigo_louos'] ?? '') !== self::ESCRITORIO_GENERICO,
        ));

        $pool = $especificos !== [] ? $especificos : $linhas;

        $porPrefixo = [];

        foreach ($pool as $linha) {
            $porPrefixo[$this->prefixo($linha['codigo_louos'] ?? '')][] = $linha;
        }

        foreach (self::PREFIXO_PREFERENCIA as $prefixo) {
            if (isset($porPrefixo[$prefixo])) {
                return $porPrefixo[$prefixo][0];
            }
        }

        return $pool[0] ?? null;
    }

    private function prefixo(string $codigoLouos): string
    {
        if (str_starts_with($codigoLouos, '08A')) {
            return '08A';
        }

        $partes = explode('.', $codigoLouos);

        return $partes[0];
    }

    /**
     * @param  array<string, string>  $linha
     * @return list<array{cnae: string, grupo: string, subgrupo: string, area_min: string, area_max: string, observacao: string}>
     */
    private function faixasDaLinha(array $linha): array
    {
        $bandas = [];

        foreach ([
            ['enquadramento1', 'ate_m2_1'],
            ['enquadramento2', 'ate_m2_2'],
            ['enquadramento3', 'acima_m2'],
        ] as [$codigoCol, $areaCol]) {
            $uso = $this->parseUso($linha[$codigoCol] ?? '');

            if ($uso === null) {
                continue;
            }

            $bandas[] = [
                'uso' => $uso,
                'marca' => $this->parseArea($linha[$areaCol] ?? ''),
            ];
        }

        if ($bandas === []) {
            return [];
        }

        $limites = $this->limites($bandas);
        $faixas = [];

        foreach ($bandas as $i => $banda) {
            $faixas[] = [
                'cnae' => $linha['cnae'],
                'grupo' => $banda['uso']['grupo'],
                'subgrupo' => $banda['uso']['subgrupo'],
                'area_min' => $this->formatarArea($limites[$i]['min']),
                'area_max' => $limites[$i]['max'] === null ? '' : $this->formatarArea($limites[$i]['max']),
                'observacao' => $linha['codigo_louos'],
            ];
        }

        return $faixas;
    }

    /**
     * @param  list<array{uso: array{grupo: string, subgrupo: string}, marca: ?float}>  $bandas
     * @return list<array{min: float, max: ?float}>
     */
    private function limites(array $bandas): array
    {
        $total = count($bandas);

        if ($total === 1) {
            return [['min' => 0.0, 'max' => null]];
        }

        $max1 = $bandas[0]['marca'] ?? 0.0;

        if ($total === 2) {
            $marca2 = $bandas[1]['marca'];
            $min2 = ($marca2 !== null && $marca2 > $max1 && $marca2 <= $max1 + 0.011)
                ? $marca2
                : round($max1 + 0.01, 2);

            return [
                ['min' => 0.0, 'max' => $max1],
                ['min' => $min2, 'max' => null],
            ];
        }

        $max2 = $bandas[1]['marca'] ?? round($max1 + 0.01, 2);
        $min3 = $bandas[2]['marca'] ?? round($max2 + 0.01, 2);

        return [
            ['min' => 0.0, 'max' => $max1],
            ['min' => round($max1 + 0.01, 2), 'max' => $max2],
            ['min' => $min3, 'max' => null],
        ];
    }

    /**
     * @return array{grupo: string, subgrupo: string}|null
     */
    private function parseUso(string $raw): ?array
    {
        $raw = trim($raw);

        if ($raw === '' || $raw === '-' || strcasecmp($raw, 'Q') === 0) {
            return null;
        }

        if (preg_match('/^(nR[0-9A-Za-z]+|ID[0-9]+)\s*-\s*([0-9]+)/', $raw, $match) !== 1) {
            return null;
        }

        $grupo = $match[1];

        if (str_starts_with(strtolower($grupo), 'nr')) {
            $grupo = 'nR'.substr($grupo, 2);
        }

        $numero = $match[2];

        if (strlen($numero) === 1) {
            $numero = '0'.$numero;
        } elseif (strlen($numero) === 3 && str_starts_with($numero, '0')) {
            $numero = substr($numero, 1);
        }

        return [
            'grupo' => $grupo,
            'subgrupo' => $grupo.'-'.$numero,
        ];
    }

    private function parseArea(string $raw): ?float
    {
        $raw = trim($raw);

        if ($raw === '' || $raw === '-' || strcasecmp($raw, 'Q') === 0) {
            return null;
        }

        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }

        if (! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    private function formatarArea(float $valor): string
    {
        $formatado = number_format($valor, 2, '.', '');

        if (str_ends_with($formatado, '.00')) {
            return (string) (int) $valor;
        }

        return rtrim(rtrim($formatado, '0'), '.');
    }
}
