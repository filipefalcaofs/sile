<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    /**
     * Registra a política de senha usada por Password::default() / Fortify.
     */
    public static function configureDefaults(): void
    {
        Password::defaults(fn (): Password => static::buildRule());
    }

    /**
     * Regra de validação alinhada aos parâmetros administráveis (HU-014).
     */
    public static function buildRule(): Password
    {
        $rule = Password::min((int) Settings::get('security.password.min_length', 8));

        if (static::requires('security.password.require_mixed_case', true)) {
            $rule->mixedCase();
        }

        if (static::requires('security.password.require_numbers', true)) {
            $rule->numbers();
        }

        if (static::requires('security.password.require_symbols', false)) {
            $rule->symbols();
        }

        return $rule;
    }

    private static function requires(string $key, bool $default): bool
    {
        return filter_var(Settings::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

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

        if (static::requires('security.password.require_mixed_case', true)) {
            $requirements[] = 'letras maiúsculas e minúsculas';
        }

        if (static::requires('security.password.require_numbers', true)) {
            $requirements[] = 'números';
        }

        if (static::requires('security.password.require_symbols', false)) {
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
