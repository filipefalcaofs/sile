<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cria a lista administrável dos CNAEs que a observação (a) do Quadro 11A mantém
 * vedados em Via Local. Antes ela ficava fixa no código; a SEDUR precisa
 * ajustá-la sem deploy quando validar a associação dos CNAEs. O OKD só roda
 * `migrate` ao subir, sem `db:seed`, por isso a linha nasce aqui. Muda o
 * resultado de viabilidade, então a edição segue o fluxo de aprovação
 * (governança de decisão). Idempotente: linha existente não é tocada.
 */
return new class extends Migration
{
    private const CHAVE = 'louos.via_local.cnaes_vedados';

    public function up(): void
    {
        if (DB::table('parameters')->where('key', self::CHAVE)->exists()) {
            return;
        }

        DB::table('parameters')->insert([
            'key' => self::CHAVE,
            'group' => 'louos',
            'governance' => 'decision',
            'type' => 'string',
            'value' => null,
            'default_value' => '9609207,8011102',
            'validation_rules' => json_encode(['required', 'string', 'max:500']),
            'sensitive' => false,
            'description' => 'CNAEs que a observação (a) do Quadro 11A da LOUOS mantém vedados em Via Local, mesmo nas zonas ZCMu, ZCMe, ZCLMu e ZCLMe (adestramento e hospedagem de animais de estimação). Códigos só com dígitos ou com máscara, separados por vírgula. A SEDUR valida a associação definitiva dos CNAEs',
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
