<?php

namespace App\Services\Tratamento;

/**
 * Casamento do subgrupo da linha da planilha com a lista da aba Análise CNLU.
 * As linhas trazem variações como `ID3- 07`, `ID3-02 (subgrupo 2)` e
 * `ID3-13 (subgrupo 1`; só o código do subgrupo conta.
 */
final class CnluLista
{
    /**
     * @param  iterable<string>  $codigos
     */
    public static function contem(string $subgrupo, iterable $codigos): bool
    {
        $subgrupo = self::normalizar($subgrupo);

        if ($subgrupo === '') {
            return false;
        }

        foreach ($codigos as $codigo) {
            $codigo = self::normalizar((string) $codigo);

            if ($codigo !== '' && ($subgrupo === $codigo || str_starts_with($subgrupo, $codigo.'-'))) {
                return true;
            }
        }

        return false;
    }

    public static function normalizar(string $codigo): string
    {
        if (preg_match('/^\s*([A-Za-z]+\d*)\s*-\s*(\d+)/u', $codigo, $m) === 1) {
            return mb_strtolower($m[1]).'-'.$m[2];
        }

        return mb_strtolower(trim((string) preg_replace('/\s*\(.*$/u', '', $codigo)));
    }
}
