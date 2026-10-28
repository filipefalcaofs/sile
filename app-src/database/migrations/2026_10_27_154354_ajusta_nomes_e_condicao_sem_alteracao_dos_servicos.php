<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Linha do De x Para para o pedido de alteração de atividade que não muda nenhum
 * CNAE em relação à última viabilidade da empresa: Revisão de Atividade. Os nomes
 * de endereço e de espaço vêm da migration de 26/10 (documento da SEDUR de 23/10).
 */
return new class extends Migration
{
    private const string ATIVIDADES = 'alteracao de atividades economicas (principal e secundarias)';

    public function up(): void
    {
        $agora = now();

        $existe = DB::table('servico_nomenclaturas')
            ->where('chave', self::ATIVIDADES)
            ->where('condicao', 'cnae_sem_alteracao')
            ->exists();

        $base = DB::table('servico_nomenclaturas')->where('chave', self::ATIVIDADES)->orderBy('id')->first();

        if ($existe || $base === null) {
            return;
        }

        DB::table('servico_nomenclaturas')->insert([
            'de' => $base->de,
            'chave' => self::ATIVIDADES,
            'condicao' => 'cnae_sem_alteracao',
            'para' => 'Revisão de Atividade',
            'exibir' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);
    }

    /**
     * Não desfaz: renomear de volta apagaria edição feita na tela depois da carga.
     */
    public function down(): void
    {
        //
    }
};
