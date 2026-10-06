<?php

namespace App\Services\Tratamento;

/**
 * Lista da aba "Análise CNLU": cada código entra no nível de subgrupo que a
 * planilha escreve. Item com ressalva "(subgrupo N)" não entra na lista do
 * motor até a SEDUR dizer o que a ressalva restringe.
 */
class PlanilhaCnluParser
{
    private const CODIGO_SUBGRUPO = '/\b[nN][A-Za-z]*\d*-\d+\b/u';

    private const CODIGO_ID = '/\bID\d+-\d+\b/u';

    /**
     * @param  list<array<int, string>>  $linhas
     * @return array{itens: list<array{codigo: string, texto: string}>, aguardando: list<string>}
     */
    public function interpretar(array $linhas): array
    {
        $itens = [];
        $aguardando = [];

        foreach ($linhas as $linha) {
            $texto = trim(implode(' ', array_filter($linha, fn (string $v): bool => trim($v) !== '')));

            if ($texto === '') {
                continue;
            }

            if (preg_match(self::CODIGO_ID, $texto) === 1) {
                $this->bloco($texto, $itens, $aguardando);

                continue;
            }

            foreach ($this->codigos($texto) as $codigo) {
                $itens[] = ['codigo' => $codigo, 'texto' => $texto];
            }
        }

        return ['itens' => $itens, 'aguardando' => $aguardando];
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
     * @param  list<string>  $aguardando
     */
    private function bloco(string $texto, array &$itens, array &$aguardando): void
    {
        foreach (preg_split('/\R/u', $texto) ?: [] as $linha) {
            if (preg_match(self::CODIGO_ID, $linha, $codigo) !== 1) {
                continue;
            }

            if (preg_match('/\(\s*subgrupo\s+\d+\s*\)/iu', $linha) === 1) {
                $aguardando[] = $codigo[0];

                continue;
            }

            $itens[] = ['codigo' => $codigo[0], 'texto' => $texto];
        }
    }
}
