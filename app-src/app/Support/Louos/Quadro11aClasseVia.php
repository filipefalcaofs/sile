<?php

namespace App\Support\Louos;

/**
 * Grafia oficial das 7 classes de via do Quadro 11A (Lei nº 9.148/2016).
 * O GeoServer publica com hífen e espaçamento irregular (VC-I, VC - II); a
 * lei e o cadastro usam espaço simples (VC I, VC II). Classe fora da lista
 * não é promovida a classe da lei — devolve null e o quadro degrada.
 */
final class Quadro11aClasseVia
{
    /** @var list<string> */
    private const OFICIAIS = ['VA I', 'VA II', 'VC I', 'VC II', 'VE', 'VL', 'VP'];

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

        $chave = self::chave($bruta);

        if ($chave === '') {
            return null;
        }

        foreach (self::OFICIAIS as $oficial) {
            if (self::chave($oficial) === $chave) {
                return $oficial;
            }
        }

        return null;
    }

    /**
     * Compara sem hífen, sem espaço e sem caixa: "VC - II", "VC-II" e
     * "vc ii" resolvem para a mesma classe.
     */
    private static function chave(string $valor): string
    {
        return strtoupper((string) preg_replace('/[\s\-_]+/', '', trim($valor)));
    }
}
