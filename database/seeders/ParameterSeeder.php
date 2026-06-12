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
            $sensitive = (bool) ($meta['sensitive'] ?? false);
            unset($meta['sensitive']);

            $parameter = Parameter::query()->updateOrCreate(['key' => $key], $meta);

            // `sensitive` fica fora do fillable (decisão 02-02): só o seed a
            // define, via forceFill, sempre ANTES de qualquer gravação de value.
            if ($parameter->sensitive !== $sensitive) {
                $parameter->forceFill(['sensitive' => $sensitive])->save();
            }
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
            'ui.dashboard.acessos_janela_dias' => [
                'group' => 'ui',
                'type' => 'integer',
                'default_value' => '7',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:90'],
                'description' => 'Janela em dias do indicador de acessos no painel de gestão',
            ],
            'features.procuracoes' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o módulo de procurações no portal',
            ],
            'features.cnpj_lookup' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a consulta automática de dados por CNPJ no cadastro de empresas',
            ],
            'integrations.cnpj_lookup.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://brasilapi.com.br/api/cnpj/v1',
                'validation_rules' => ['required', 'url'],
                'requires_connection_test' => true,
                'description' => 'URL base do provedor de consulta de CNPJ (dados abertos da RFB)',
            ],
            'ui.companies.per_page' => [
                'group' => 'ui',
                'type' => 'integer',
                'default_value' => '15',
                'validation_rules' => ['required', 'integer', 'min:5', 'max:100'],
                'description' => 'Itens por página na listagem de empresas',
            ],
            'features.govbr_login' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita o login com a conta GOV.BR no portal do cidadão (exige credenciais configuradas)',
            ],
            'integrations.govbr.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://sso.staging.acesso.gov.br',
                'validation_rules' => ['required', 'url'],
                'requires_connection_test' => true,
                'description' => 'URL base do Login Único GOV.BR (staging: sso.staging.acesso.gov.br; produção: sso.acesso.gov.br)',
            ],
            'integrations.govbr.api_base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://api.staging.acesso.gov.br',
                'validation_rules' => ['required', 'url'],
                'requires_connection_test' => true,
                'description' => 'URL base da API de confiabilidades do GOV.BR (níveis bronze/prata/ouro)',
            ],
            'integrations.govbr.client_id' => [
                'group' => 'integracoes',
                'type' => 'string',
                'sensitive' => true,
                'default_value' => null,
                'validation_rules' => ['required', 'string', 'max:255'],
                'description' => 'Client ID da credencial do Login Único GOV.BR (Termo de Adesão SGD)',
            ],
            'integrations.govbr.client_secret' => [
                'group' => 'integracoes',
                'type' => 'string',
                'sensitive' => true,
                'default_value' => null,
                'validation_rules' => ['required', 'string', 'max:255'],
                'description' => 'Client Secret da credencial do Login Único GOV.BR (armazenado criptografado)',
            ],
            'security.govbr.minimum_level' => [
                'group' => 'seguranca',
                'type' => 'string',
                'default_value' => 'bronze',
                'validation_rules' => ['required', 'in:bronze,prata,ouro'],
                'description' => 'Nível mínimo de confiabilidade da conta GOV.BR aceito no login (bronze, prata ou ouro)',
            ],
        ];
    }
}
