<?php

namespace App\Support\Louos;

/**
 * Grafia oficial das 21 zonas do Quadro 10 (Lei nº 9.148/2016).
 * Alias de GIS (ZPR-1, ZPR_1) vira a chave do PDF (ZPR 1). Código
 * inventado (ZCN-1) não é promovido a zona da lei.
 */
final class Quadro10Zona
{
    /** @var list<string> */
    private const OFICIAIS = [
        'ZPR 1',
        'ZPR 2',
        'ZPR 3',
        'ZEIS 1',
        'ZEIS 2',
        'ZEIS 3',
        'ZEIS 4',
        'ZEIS 5',
        'ZCMe 1/01',
        'ZCMe 1/02',
        'ZCMe 1/03',
        'ZCMe 2',
        'ZCMe - CA',
        'ZCMu 1 - IPITANGA',
        'ZCMu 2',
        'ZCLMe',
        'ZCLMu',
        'ZDE 1',
        'ZDE 2',
        'ZUSI',
        'ZIT',
    ];

    /**
     * @return list<string>
     */
    public static function oficiais(): array
    {
        return self::OFICIAIS;
    }

    public static function oficializar(?string $bruta): ?string
    {
        if ($bruta === null) {
            return null;
        }

        $texto = trim(preg_replace('/\s+/', ' ', $bruta) ?? '');

        if ($texto === '') {
            return null;
        }

        $chave = self::chave($texto);

        foreach (self::OFICIAIS as $oficial) {
            if (self::chave($oficial) === $chave) {
                return $oficial;
            }
        }

        return $texto;
    }

    private static function chave(string $valor): string
    {
        return strtolower(preg_replace('/[\s_-]+/', '', $valor) ?? $valor);
    }
}
