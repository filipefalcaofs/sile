<?php

namespace App\Services\Tratamento;

/**
 * Lista da aba "Análise CNLU": cada código entra no nível de subgrupo que a
 * planilha escreve. Resposta da SEDUR de 07/10/2026: `nRa` vale só para o
 * subgrupo `nRa-02`, e os itens do art. 132 valem em qualquer subgrupo, com ou
 * sem a ressalva "(subgrupo N)".
 */
class PlanilhaCnluParser
{
    private const CODIGO_SUBGRUPO = '/\b[nN][A-Za-z]*\d*-\d+\b/u';

    private const CODIGO_ID = '/\bID\d+-\d+\b/u';

    /**
     * @var array<string, string>
     */
    private const AJUSTES_SEDUR = ['nRa' => 'nRa-02'];

    /**
     * @param  list<array<int, string>>  $linhas
     * @return array{itens: list<array{codigo: string, texto: string}>, aguardando: list<string>}
     */
    public function interpretar(array $linhas): array
    {
        $itens = [];
        foreach ($linhas as $linha) {
            $texto = trim(implode(' ', array_filter($linha, fn (string $v): bool => trim($v) !== '')));

            if ($texto === '') {
                continue;
            }

            if (preg_match(self::CODIGO_ID, $texto) === 1) {
                $this->bloco($texto, $itens);

                continue;
            }

            foreach ($this->codigos($texto) as $codigo) {
                $itens[] = ['codigo' => self::AJUSTES_SEDUR[$codigo] ?? $codigo, 'texto' => $texto];
            }
        }

        return ['itens' => $itens, 'aguardando' => []];
    }

    /**
     * @return list<string>
     */
    private function codigos(string $texto): array
    {
        if (preg_match('/\(([^)]*)\)/u', $texto, $dentro) === 1
            && preg_match_all(self::CODIGO_SUBGRUPO, $dentro[1], $subgrupos) > 0) {
            return array_values(array_unique($subgrupos[0]));
        }

        return [trim((string) preg_replace('/\s*\(.*$/u', '', $texto))];
    }

    /**
     * @param  list<array{codigo: string, texto: string}>  $itens
     */
    private function bloco(string $texto, array &$itens): void
    {
        foreach (preg_split('/\R/u', $texto) ?: [] as $linha) {
            if (preg_match(self::CODIGO_ID, $linha, $codigo) !== 1) {
                continue;
            }

            $itens[] = ['codigo' => $codigo[0], 'texto' => $texto];
        }
    }
}
