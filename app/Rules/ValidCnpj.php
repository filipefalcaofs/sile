<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Valida CNPJ numérico E alfanumérico (IN RFB 2.229/2024). A partir de
 * julho/2026 a RFB emite raiz/ordem alfanuméricas (12 caracteres A-Z/0-9)
 * com 2 dígitos verificadores SEMPRE numéricos. O cálculo do DV usa módulo
 * 11 sobre o valor ASCII-48 de cada caractere (ord($char) - 48), que cobre
 * dígitos (0-9 ⇒ 0-9) e letras (A-Z ⇒ 17-42) com a mesma fórmula oficial.
 */
class ValidCnpj implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));

        if (preg_match('/^[A-Z\d]{12}\d{2}$/', $cnpj) !== 1 || preg_match('/^(.)\1{13}$/', $cnpj)) {
            $fail('O campo :attribute não é um CNPJ válido.');

            return;
        }

        // 13 pesos: o 1º DV usa os 12 últimos (offset 1), o 2º DV usa todos os 13.
        $weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([12, 13] as $position) {
            $slice = array_slice($weights, 13 - $position);

            $sum = 0;

            for ($i = 0; $i < $position; $i++) {
                $sum += (ord($cnpj[$i]) - 48) * $slice[$i];
            }

            $remainder = $sum % 11;
            $digit = $remainder < 2 ? 0 : 11 - $remainder;

            if ((int) $cnpj[$position] !== $digit) {
                $fail('O campo :attribute não é um CNPJ válido.');

                return;
            }
        }
    }
}
