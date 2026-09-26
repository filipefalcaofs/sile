<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class TelefoneComDdd implements ValidationRule
{
    /**
     * Exige DDD + 8 ou 9 dígitos. Máscara é ignorada na contagem.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if (! preg_match('/^\d{10,11}$/', $digits)) {
            $fail('Informe um telefone com DDD e 8 ou 9 dígitos.');
        }
    }
}
