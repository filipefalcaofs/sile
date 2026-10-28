<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cria o interruptor geral dos serviços do portal (features.portal_servicos) na
 * própria migration. O OKD só roda `migrate` ao subir, sem `db:seed`, então o
 * parâmetro existente só no ParameterSeeder não aparecia na tela de Parâmetros.
 * Idempotente: se a linha já existe (seed ou gestor), não mexe nela e preserva
 * o valor gravado. Quando o ambiente já ligava os serviços pela variável
 * SILE_PORTAL_SERVICOS, o valor nasce ligado para a migration não desligar o portal.
 */
return new class extends Migration
{
    private const CHAVE = 'features.portal_servicos';

    public function up(): void
    {
        if (DB::table('parameters')->where('key', self::CHAVE)->exists()) {
            return;
        }

        DB::table('parameters')->insert([
            'key' => self::CHAVE,
            'group' => 'features',
            'governance' => 'operational',
            'type' => 'boolean',
            'value' => config('viabiliza.features.portal_servicos') ? '1' : null,
            'default_value' => '0',
            'validation_rules' => json_encode(['required', 'boolean']),
            'sensitive' => false,
            'description' => 'Habilita os serviços do portal do cidadão (consulta, solicitação, renovação, empresas e procurações). Desligado, esses serviços ficam indisponíveis com aviso; painel, notificações e conta continuam',
            'requires_connection_test' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('viabiliza.parameters.'.self::CHAVE);
    }

    public function down(): void
    {
        DB::table('parameters')->where('key', self::CHAVE)->delete();

        Cache::forget('viabiliza.parameters.'.self::CHAVE);
    }
};
