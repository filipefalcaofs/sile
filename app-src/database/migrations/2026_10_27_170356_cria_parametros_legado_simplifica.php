<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cria na própria migration os três parâmetros da API do legado Simplifica, que
 * a tela de APIs configura. O OKD só roda `migrate` ao subir, sem `db:seed`, então
 * o parâmetro existente só no ParameterSeeder não apareceria na tela. Idempotente:
 * linha que já existe (seed ou gestor) não é tocada e mantém o valor gravado. A
 * consulta nasce desligada, e o token, como todo segredo, só é gravado pela tela.
 */
return new class extends Migration
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private function parametros(): array
    {
        return [
            'features.legado_simplifica' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'sensitive' => false,
                'description' => 'Consulta a viabilidade anterior da empresa no legado Simplifica (TVL deferida e ativa e os CNAEs dela) para comparar as atividades de uma alteração. Desligado, só vale o processo anterior no Viabiliza',
            ],
            'integrations.legado_simplifica.url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => null,
                'validation_rules' => ['nullable', 'url'],
                'sensitive' => false,
                'description' => 'Endereço da API do legado Simplifica (esquema e host, sem /v1), por exemplo http://simplifica-legado.pms.ba.gov.br',
            ],
            'integrations.legado_simplifica.token' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => null,
                'validation_rules' => ['nullable', 'string', 'max:2048'],
                'sensitive' => true,
                'description' => 'Token de máquina (lg_..., validade de 90 dias) da API do legado Simplifica, com os escopos provisorias:ler e processos:ler (armazenado criptografado, nunca reexibido)',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->parametros() as $chave => $dados) {
            if (DB::table('parameters')->where('key', $chave)->exists()) {
                continue;
            }

            DB::table('parameters')->insert([
                'key' => $chave,
                'group' => $dados['group'],
                'governance' => 'operational',
                'type' => $dados['type'],
                'value' => null,
                'default_value' => $dados['default_value'],
                'validation_rules' => json_encode($dados['validation_rules']),
                'sensitive' => $dados['sensitive'],
                'description' => $dados['description'],
                'requires_connection_test' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Cache::forget("viabiliza.parameters.{$chave}");
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->parametros()) as $chave) {
            DB::table('parameters')->where('key', $chave)->delete();
            Cache::forget("viabiliza.parameters.{$chave}");
        }
    }
};
