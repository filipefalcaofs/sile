<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cria os parâmetros da entrada pelo Portal de Serviços da SEDUR (HU-142) na
 * própria migration: o OKD só roda `migrate` ao subir, sem `db:seed`.
 * Idempotente: linha já existente (seed ou gestor) não é tocada e preserva o
 * valor gravado. A entrada nasce desligada.
 */
return new class extends Migration
{
    /**
     * @return array<string, array{group: string, type: string, default_value: string, validation_rules: list<string>, description: string}>
     */
    private function parametros(): array
    {
        return [
            'features.login_portal_servicos' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Entrada do cidadão pelo Portal de Serviços da SEDUR. Ligada, o portal deixa de ter login, cadastro e senha próprios',
            ],
            'integrations.portal_servicos.bff_base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-bff-portal-servicos',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL base do BFF do Portal de Serviços da SEDUR, onde a sessão do cidadão é conferida',
            ],
            'integrations.portal_servicos.login_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://sso-homo.salvador.ba.gov.br/govbr/authorize/?client_id=SedurMais-PortalServicos-Hml',
                'validation_rules' => ['required', 'url'],
                'description' => 'Endereço de entrada do Portal de Serviços da SEDUR (login da Prefeitura com GOV.BR)',
            ],
            'integrations.portal_servicos.carta_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://servicos.sedur.salvador.ba.gov.br/carta-servicos/17',
                'validation_rules' => ['required', 'url'],
                'description' => 'Página da Carta de Serviços para onde o cidadão volta ao sair do portal',
            ],
            'integrations.portal_servicos.intervalo_checagem_sessao' => [
                'group' => 'integracoes',
                'type' => 'integer',
                'default_value' => '300',
                'validation_rules' => ['required', 'integer', 'min:60', 'max:3600'],
                'description' => 'Intervalo, em segundos, para conferir se a sessão do cidadão no Portal de Serviços ainda vale',
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
                'group' => $meta['group'],
                'governance' => 'operational',
                'type' => $meta['type'],
                'value' => null,
                'default_value' => $meta['default_value'],
                'validation_rules' => json_encode($meta['validation_rules']),
                'sensitive' => false,
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
