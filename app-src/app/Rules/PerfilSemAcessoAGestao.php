<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Spatie\Permission\Models\Role;

class PerfilSemAcessoAGestao implements ValidationRule
{
    /**
     * Rejeita perfil que concede a permissão acessar-gestao.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $perfil = Role::query()->where('name', (string) $value)->first();

        if ($perfil !== null && $perfil->permissions()->where('name', 'acessar-gestao')->exists()) {
            $fail('O perfil do cidadão não pode ter acesso à gestão.');
        }
    }
}
