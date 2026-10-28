<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Junta os parâmetros do portal do cidadão na aba "portal" e cria um
 * interruptor por serviço (a chave dos extras só entra pelo seeder, para
 * não sobrepor a variável de ambiente). Roda na migration porque o OKD só faz `migrate` ao
 * subir, sem `db:seed`. Idempotente: não toca em valor já gravado.
 */
return new class extends Migration
{
    /**
     * Chaves que passam para a aba portal e o grupo de onde vieram.
     *
     * @return array<string, string>
     */
    private function movidas(): array
    {
        return [
            'features.portal_servicos' => 'features',
            'features.login_portal_servicos' => 'features',
            'features.consulta_viabilidade' => 'features',
            'features.govbr_login' => 'features',
            'renovacao.intervalo_meses' => 'solicitacao',
            'renovacao.legado.janela_a_vencer_dias' => 'solicitacao',
            'renovacao.legado.permite_vencida' => 'solicitacao',
            'integrations.portal_servicos.bff_base_url' => 'integracoes',
            'integrations.portal_servicos.login_url' => 'integracoes',
            'integrations.portal_servicos.carta_url' => 'integracoes',
            'integrations.portal_servicos.intervalo_checagem_sessao' => 'integracoes',
        ];
    }

    /**
     * @return array<string, array{default: string, description: string}>
     */
    private function novas(): array
    {
        return [
            'features.portal_renovacao' => [
                'default' => '1',
                'description' => 'Serviço Renovar viabilidade do portal (TVL do Viabiliza e do sistema anterior). Só vale com o interruptor geral ligado',
            ],
            'features.portal_consulta_processo' => [
                'default' => '1',
                'description' => 'Serviço Consultar processo do portal (por número do processo, da viabilidade ou do BAP). Só vale com o interruptor geral ligado',
            ],
            'features.portal_convites' => [
                'default' => '1',
                'description' => 'Serviço Responder convite do portal (pedidos da SEDUR ao requerente). Só vale com o interruptor geral ligado',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->novas() as $chave => $meta) {
            if (DB::table('parameters')->where('key', $chave)->exists()) {
                continue;
            }

            DB::table('parameters')->insert([
                'key' => $chave,
                'group' => 'portal',
                'governance' => 'operational',
                'type' => 'boolean',
                'value' => null,
                'default_value' => $meta['default'],
                'validation_rules' => json_encode(['required', 'boolean']),
                'sensitive' => false,
                'description' => $meta['description'],
                'requires_connection_test' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Cache::forget('viabiliza.parameters.'.$chave);
        }

        DB::table('parameters')->whereIn('key', array_keys($this->movidas()))->update(['group' => 'portal']);

        DB::table('parameters')->where('key', 'features.portal_servicos')->update([
            'description' => 'Interruptor geral dos serviços do portal do cidadão: renovação, consulta de processo e convite. Desligado, todos ficam indisponíveis com aviso; painel, notificações e conta continuam. Cada serviço tem também o seu interruptor',
        ]);
    }

    public function down(): void
    {
        foreach ($this->movidas() as $chave => $grupo) {
            DB::table('parameters')->where('key', $chave)->update(['group' => $grupo]);
        }

        foreach (array_keys($this->novas()) as $chave) {
            DB::table('parameters')->where('key', $chave)->delete();
            Cache::forget('viabiliza.parameters.'.$chave);
        }
    }
};
