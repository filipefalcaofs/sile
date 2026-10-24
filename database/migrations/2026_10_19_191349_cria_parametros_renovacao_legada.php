<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cria os parâmetros da renovação de TVL legada na própria migration: o OKD só
 * roda `migrate` ao subir, sem `db:seed`. Idempotente: linha já existente
 * (seed ou gestor) não é tocada e preserva o valor gravado.
 */
return new class extends Migration
{
    /**
     * @return array<string, array{type: string, default_value: string, validation_rules: list<string>, description: string}>
     */
    private function parametros(): array
    {
        return [
            'renovacao.legado.janela_a_vencer_dias' => [
                'type' => 'integer',
                'default_value' => '90',
                'validation_rules' => ['required', 'integer', 'min:1', 'max:365'],
                'description' => 'Janela (dias) antes do vencimento em que uma provisória do sistema legado aparece como "a vencer" para o cidadão',
            ],
            'renovacao.legado.permite_vencida' => [
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Permite ao cidadão renovar uma provisória do sistema legado já vencida. Desligue se a SEDUR definir um atraso máximo',
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
                'group' => 'solicitacao',
                'governance' => 'decision',
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
