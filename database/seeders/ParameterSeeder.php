<?php

namespace Database\Seeders;

use App\Models\Parameter;
use Illuminate\Database\Seeder;

/**
 * Catálogo inicial de parâmetros administráveis (HU-014).
 *
 * Upsert por key APENAS dos metadados — `value` nunca entra no array de
 * update: re-seed em deploy preserva o que o administrador gravou (Pitfall 2).
 */
class ParameterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::catalog() as $key => $meta) {
            Parameter::query()->updateOrCreate(['key' => $key], $meta);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function catalog(): array
    {
        return [
            'security.password.min_length' => [
                'group' => 'seguranca',
                'type' => 'integer',
                'default_value' => '8',
                'validation_rules' => ['required', 'integer', 'min:6', 'max:64'],
                'description' => 'Tamanho mínimo da senha dos usuários',
            ],
            'security.password.require_mixed_case' => [
                'group' => 'seguranca',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Exigir letras maiúsculas e minúsculas na senha',
            ],
            'security.password.require_numbers' => [
                'group' => 'seguranca',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Exigir números na senha',
            ],
            'security.password.require_symbols' => [
                'group' => 'seguranca',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Exigir símbolos na senha',
            ],
            'security.login.max_attempts' => [
                'group' => 'seguranca',
                'type' => 'integer',
                'default_value' => '5',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:20'],
                'description' => 'Tentativas de login antes do bloqueio temporário',
            ],
            'security.password_reset_expire' => [
                'group' => 'seguranca',
                'type' => 'integer',
                'default_value' => '60',
                'validation_rules' => ['required', 'integer', 'min:10', 'max:1440'],
                'description' => 'Validade em minutos do link de recuperação de senha',
            ],
            'ui.access_history.per_page' => [
                'group' => 'ui',
                'type' => 'integer',
                'default_value' => '15',
                'validation_rules' => ['required', 'integer', 'min:5', 'max:100'],
                'description' => 'Itens por página no histórico de acessos',
            ],
            'ui.cnaes.per_page' => [
                'group' => 'ui',
                'type' => 'integer',
                'default_value' => '15',
                'validation_rules' => ['required', 'integer', 'min:5', 'max:100'],
                'description' => 'Itens por página na listagem de CNAEs',
            ],
            'ui.users.per_page' => [
                'group' => 'ui',
                'type' => 'integer',
                'default_value' => '15',
                'validation_rules' => ['required', 'integer', 'min:5', 'max:100'],
                'description' => 'Itens por página na listagem de usuários',
            ],
            'features.procuracoes' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o módulo de procurações no portal',
            ],
        ];
    }
}
