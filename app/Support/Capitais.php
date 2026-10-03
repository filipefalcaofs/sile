<?php

namespace App\Support;

/**
 * As 27 capitais, por UF e nome normalizado.
 *
 * A base federal de microdados identifica o município por nome em maiúsculas
 * e sem acento, mais a sigla da UF — não há código IBGE no arquivo. Esta lista
 * é o que permite montar o ranking das capitais a partir dela.
 *
 * Fica no código, não em parâmetro: a divisão político-administrativa não muda
 * sem lei federal, e um gestor da SEDUR não a altera.
 */
final class Capitais
{
    /** @var array<string, string> */
    private const POR_UF = [
        'AC' => 'RIO BRANCO',
        'AL' => 'MACEIO',
        'AM' => 'MANAUS',
        'AP' => 'MACAPA',
        'BA' => 'SALVADOR',
        'CE' => 'FORTALEZA',
        'DF' => 'BRASILIA',
        'ES' => 'VITORIA',
        'GO' => 'GOIANIA',
        'MA' => 'SAO LUIS',
        'MG' => 'BELO HORIZONTE',
        'MS' => 'CAMPO GRANDE',
        'MT' => 'CUIABA',
        'PA' => 'BELEM',
        'PB' => 'JOAO PESSOA',
        'PE' => 'RECIFE',
        'PI' => 'TERESINA',
        'PR' => 'CURITIBA',
        'RJ' => 'RIO DE JANEIRO',
        'RN' => 'NATAL',
        'RO' => 'PORTO VELHO',
        'RR' => 'BOA VISTA',
        'RS' => 'PORTO ALEGRE',
        'SC' => 'FLORIANOPOLIS',
        'SE' => 'ARACAJU',
        'SP' => 'SAO PAULO',
        'TO' => 'PALMAS',
    ];

    public const UF_SALVADOR = 'BA';

    public const MUNICIPIO_SALVADOR = 'SALVADOR';

    /**
     * @return array<string, string> UF => nome normalizado da capital
     */
    public static function todas(): array
    {
        return self::POR_UF;
    }

    /**
     * @return list<string>
     */
    public static function ufs(): array
    {
        return array_keys(self::POR_UF);
    }

    public static function ehCapital(string $uf, string $municipio): bool
    {
        return (self::POR_UF[strtoupper(trim($uf))] ?? null) === self::nomeNormalizado($municipio);
    }

    /**
     * Maiúsculas, sem acento e com espaços colapsados — a forma em que a base
     * federal publica o nome do município.
     */
    public static function nomeNormalizado(string $valor): string
    {
        $semAcento = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);

        if ($semAcento === false) {
            $semAcento = $valor;
        }

        $semAcento = preg_replace('/[^A-Za-z0-9 ]/', '', $semAcento) ?? $semAcento;
        $colapsado = preg_replace('/\s+/', ' ', $semAcento) ?? $semAcento;

        return strtoupper(trim($colapsado));
    }
}
