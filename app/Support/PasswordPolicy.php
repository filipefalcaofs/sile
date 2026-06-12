<?php

namespace App\Support;

class PasswordPolicy
{
    /**
     * Descrição legível (pt-BR) dos requisitos de senha em vigor,
     * derivada das mesmas Settings usadas por Password::defaults()
     * (App\Providers\AppServiceProvider). Substitui o
     * toPasswordRulesString() — que devolve o formato técnico da Apple
     * em inglês, próprio para o atributo HTML "passwordrules" e não para
     * leitura humana.
     */
    public static function description(): string
    {
        $min = (int) Settings::get('security.password.min_length', 8);

        $requirements = [];

        if (Settings::get('security.password.require_mixed_case', true)) {
            $requirements[] = 'letras maiúsculas e minúsculas';
        }

        if (Settings::get('security.password.require_numbers', true)) {
            $requirements[] = 'números';
        }

        if (Settings::get('security.password.require_symbols', false)) {
            $requirements[] = 'símbolos';
        }

        $text = "mínimo de {$min} caracteres";

        if ($requirements !== []) {
            $text .= ', com '.self::joinNatural($requirements);
        }

        return $text;
    }

    /**
     * Junta os itens no estilo "a, b e c".
     *
     * @param  list<string>  $items
     */
    private static function joinNatural(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' e '.$last;
    }
}
