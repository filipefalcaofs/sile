<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cria a tolerância, em metros, da linha da centralidade linear (ZCLMu e ZCLMe)
 * em relação ao lote. Zero mantém a regra atual, a linha passar por cima do
 * lote; a SEDUR define o valor ao validar a regra territorial, sem deploy. Muda
 * a zona do imóvel, então a edição segue o fluxo de aprovação (governança de
 * decisão). O OKD só roda `migrate` ao subir, por isso a linha nasce aqui.
 * Idempotente: linha existente não é tocada.
 */
return new class extends Migration
{
    private const CHAVE = 'territorio.zona_linear.tolerancia_m';

    public function up(): void
    {
        if (DB::table('parameters')->where('key', self::CHAVE)->exists()) {
            return;
        }

        DB::table('parameters')->insert([
            'key' => self::CHAVE,
            'group' => 'geo',
            'governance' => 'decision',
            'type' => 'decimal',
            'value' => null,
            'default_value' => '0',
            'validation_rules' => json_encode(['required', 'numeric', 'min:0', 'max:500']),
            'sensitive' => false,
            'description' => 'Distância máxima, em metros, entre a linha da centralidade linear (ZCLMu e ZCLMe) e o lote para a zona linear valer. Zero mantém a regra de a linha passar por cima do lote. A SEDUR define o valor ao validar a regra territorial',
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
