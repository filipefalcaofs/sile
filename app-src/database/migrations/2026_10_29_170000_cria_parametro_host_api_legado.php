<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cabeçalho Host opcional da API do Simplifica (legado). Com o gateway roteando
 * por nome de host e sem DNS interno, a URL pode ser o IP e o nome entra aqui. O
 * OKD só roda `migrate` ao subir, sem `db:seed`, então o parâmetro nasce na própria
 * migration. Idempotente: linha que já existe não é tocada.
 */
return new class extends Migration
{
    private const CHAVE = 'integrations.legado_api.host';

    public function up(): void
    {
        if (DB::table('parameters')->where('key', self::CHAVE)->exists()) {
            return;
        }

        DB::table('parameters')->insert([
            'key' => self::CHAVE,
            'group' => 'integracoes',
            'governance' => 'operational',
            'type' => 'string',
            'value' => null,
            'default_value' => null,
            'validation_rules' => json_encode(['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+(:\d{1,5})?$/']),
            'sensitive' => false,
            'description' => 'Nome de host enviado no cabeçalho Host da API do Simplifica (legado), para usar a URL por IP enquanto não há DNS. Vazio: o host da própria URL',
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
