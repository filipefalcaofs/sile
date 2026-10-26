<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Documento da SEDUR de 23/10/2026: "Revisão de Endereço" passa a "Alteração
 * de Endereço" e a linha de espaço fica "Revisão Alteração de Espaço". Só
 * troca o Para que ainda está com o nome de fábrica; o editado na tela fica.
 */
return new class extends Migration
{
    private const array NOMES = [
        'Revisão de Endereço' => 'Alteração de Endereço',
        'Revisão de Endereço - Alteração de Espaço' => 'Revisão Alteração de Espaço',
    ];

    public function up(): void
    {
        foreach (self::NOMES as $antigo => $novo) {
            $this->renomear($antigo, $novo);
        }
    }

    public function down(): void
    {
        foreach (self::NOMES as $antigo => $novo) {
            $this->renomear($novo, $antigo);
        }
    }

    private function renomear(string $de, string $para): void
    {
        DB::table('servico_nomenclaturas')->where('para', $de)->update(['para' => $para, 'updated_at' => now()]);
    }
};
