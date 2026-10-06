<?php

namespace App\Support;

use NumberFormatter;

class AreaMetros
{
    public static function formatar(mixed $area): ?string
    {
        if ($area === null || $area === '') {
            return null;
        }

        if (! is_numeric($area)) {
            return null;
        }

        $formatter = new NumberFormatter('pt_BR', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
        $formatter->setSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL, '.');
        $formatter->setSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL, ',');

        $formatado = $formatter->format((float) $area);

        return $formatado === false ? null : $formatado;
    }
}
