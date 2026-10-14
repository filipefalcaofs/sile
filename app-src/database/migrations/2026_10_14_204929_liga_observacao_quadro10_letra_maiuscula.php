<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Liga as observações (a)/(b) do Quadro 10 nas cargas feitas pelo importador
 * antigo, que gravou a letra maiúscula em condicionante_ref ("Observação (A)
 * do Quadro 10 ..."). A migration 2026_10_14_194452 só reconhecia a minúscula,
 * e essas linhas ficaram sem a verificação da allow-list (RT-05). Não toca a
 * observação (c) nem a anotação do mantenedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['a', 'b'] as $nota) {
            DB::table('louos_quadro10_permissoes')
                ->where('condicionante_ref', $this->referencia($nota))
                ->whereNull('observacao')
                ->update(['observacao' => "({$nota})", 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (['a', 'b'] as $nota) {
            DB::table('louos_quadro10_permissoes')
                ->where('condicionante_ref', $this->referencia($nota))
                ->where('observacao', "({$nota})")
                ->update(['observacao' => null, 'updated_at' => now()]);
        }
    }

    private function referencia(string $nota): string
    {
        return 'Observação ('.mb_strtoupper($nota).') do Quadro 10 da Lei nº 9.148/2016';
    }
};
