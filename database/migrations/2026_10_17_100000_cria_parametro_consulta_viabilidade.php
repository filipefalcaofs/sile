<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cria o interruptor da consulta prévia (features.consulta_viabilidade) na
 * própria migration. O OKD só roda `migrate` ao subir, sem `db:seed`, então um
 * parâmetro criado apenas no ParameterSeeder não aparece na tela de Parâmetros
 * até alguém semear à mão. Idempotente: se a linha já existe (seed ou gestor),
 * não mexe nela e preserva o valor gravado.
 */
return new class extends Migration
{
    private const CHAVE = 'features.consulta_viabilidade';

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
            'value' => null,
            'default_value' => '1',
            'validation_rules' => json_encode(['required', 'boolean']),
            'sensitive' => false,
            'description' => 'Habilita a consulta prévia de viabilidade do portal. Desligada, só a página e as consultas ficam indisponíveis com aviso; os demais serviços do portal e o histórico das consultas já feitas continuam',
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
