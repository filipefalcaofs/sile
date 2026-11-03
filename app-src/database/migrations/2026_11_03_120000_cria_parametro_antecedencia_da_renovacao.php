<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cria o parâmetro da janela de renovação na própria migration: o OKD só roda
 * `migrate` ao subir, sem `db:seed`. Idempotente: linha já existente (seed ou
 * gestor) não é tocada e preserva o valor gravado.
 */
return new class extends Migration
{
    private const CHAVE = 'renovacao.antecedencia_meses';

    public function up(): void
    {
        if (DB::table('parameters')->where('key', self::CHAVE)->exists()) {
            return;
        }

        DB::table('parameters')->insert([
            'key' => self::CHAVE,
            'group' => 'portal',
            'governance' => 'decision',
            'type' => 'integer',
            'value' => null,
            'default_value' => '3',
            'validation_rules' => json_encode(['required', 'integer', 'min:1', 'max:24']),
            'sensitive' => false,
            'description' => 'Antecedência (meses) em relação ao vencimento a partir da qual o cidadão pode solicitar a renovação. Antes disso o sistema recusa o protocolo',
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
