<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CODIGO_AREA do parecer ao REGIN passa de 1 (valor do exemplo do guia da
 * JUCEB) para 70, a área da viabilidade de Salvador. Só troca o 1; uma área
 * diferente gravada pelo gestor fica como está.
 */
return new class extends Migration
{
    private const CHAVE = 'integrations.regin.codigo_area';

    public function up(): void
    {
        $this->trocar('1', '70');
    }

    public function down(): void
    {
        $this->trocar('70', '1');
    }

    private function trocar(string $de, string $para): void
    {
        foreach (['value', 'default_value'] as $coluna) {
            DB::table('parameters')
                ->where('key', self::CHAVE)
                ->where($coluna, $de)
                ->update([$coluna => $para, 'updated_at' => now()]);
        }

        Cache::forget('viabiliza.parameters.'.self::CHAVE);
    }
};
