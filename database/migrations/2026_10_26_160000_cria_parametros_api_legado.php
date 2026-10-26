<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cria os parâmetros da API do Simplifica (legado) na própria migration: o
 * OKD só roda `migrate` ao subir, sem `db:seed`. Idempotente: linha que já
 * existe (seed ou gestor) não é tocada.
 */
return new class extends Migration
{
    /**
     * @return array<string, array{type: string, default: string|null, rules: list<string>, sensitive: bool, description: string}>
     */
    private function parametros(): array
    {
        return [
            'integrations.legado_api.em_producao' => [
                'type' => 'boolean', 'default' => '0', 'rules' => ['required', 'boolean'], 'sensitive' => false,
                'description' => 'Usa a URL de produção da API do Simplifica (legado, somente leitura); desligado usa a de homologação',
            ],
            'integrations.legado_api.url_homologacao' => [
                'type' => 'string', 'default' => null, 'rules' => ['nullable', 'url'], 'sensitive' => false,
                'description' => 'URL base da API do Simplifica (legado) em homologação',
            ],
            'integrations.legado_api.url_producao' => [
                'type' => 'string', 'default' => null, 'rules' => ['nullable', 'url'], 'sensitive' => false,
                'description' => 'URL base da API do Simplifica (legado) em produção',
            ],
            'integrations.legado_api.base_url' => [
                'type' => 'string', 'default' => null, 'rules' => ['nullable', 'url'], 'sensitive' => false,
                'description' => 'URL ativa da API do Simplifica (espelha a URL escolhida no formulário de APIs)',
            ],
            'integrations.legado_api.token' => [
                'type' => 'string', 'default' => null, 'rules' => ['nullable', 'string', 'max:2048'], 'sensitive' => true,
                'description' => 'Token de acesso à API do Simplifica (armazenado criptografado, nunca reexibido). Tem prioridade sobre usuário e senha',
            ],
            'integrations.legado_api.usuario' => [
                'type' => 'string', 'default' => null, 'rules' => ['nullable', 'string', 'max:255'], 'sensitive' => false,
                'description' => 'Usuário de acesso à API do Simplifica, usado quando não há token',
            ],
            'integrations.legado_api.senha' => [
                'type' => 'string', 'default' => null, 'rules' => ['nullable', 'string', 'max:255'], 'sensitive' => true,
                'description' => 'Senha de acesso à API do Simplifica (armazenada criptografada, nunca reexibida)',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->parametros() as $chave => $meta) {
            if (DB::table('parameters')->where('key', $chave)->exists()) {
                continue;
            }

            DB::table('parameters')->insert([
                'key' => $chave,
                'group' => 'integracoes',
                'governance' => 'operational',
                'type' => $meta['type'],
                'value' => null,
                'default_value' => $meta['default'],
                'validation_rules' => json_encode($meta['rules']),
                'sensitive' => $meta['sensitive'],
                'description' => $meta['description'],
                'requires_connection_test' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Cache::forget('viabiliza.parameters.'.$chave);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->parametros()) as $chave) {
            DB::table('parameters')->where('key', $chave)->delete();
            Cache::forget('viabiliza.parameters.'.$chave);
        }
    }
};
