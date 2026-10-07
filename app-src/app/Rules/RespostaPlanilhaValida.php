<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Resposta a uma pergunta da planilha: Sim/Não (booleano), uma opção (inteiro
 * de 1 a 99) ou uma lista de opções.
 */
class RespostaPlanilhaValida implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
            return;
        }

        if (is_int($value) && $value >= 1 && $value <= 99) {
            return;
        }

        if (is_array($value) && $value !== [] && array_is_list($value)
            && array_filter($value, fn (mixed $item): bool => ! is_int($item) || $item < 1 || $item > 99) === []) {
            return;
        }

        $fail('A resposta do campo :attribute deve ser Sim, Não ou as opções marcadas.');
    }
}
