<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nomes que a SEDUR pediu em 27/10: Alteração de Endereço (codlog, número ou
 * bairro), Revisão de Espaço (mesmo endereço) e Revisão de Atividade para o
 * pedido que não muda nenhum CNAE. Só troca o nome que ainda é o padrão da
 * carga: o que a SEDUR já editou na tela fica como está.
 */
return new class extends Migration
{
    private const string ATIVIDADES = 'alteracao de atividades economicas (principal e secundarias)';

    public function up(): void
    {
        $agora = now();

        DB::table('servico_nomenclaturas')
            ->where('para', 'Revisão de Endereço')
            ->update(['para' => 'Alteração de Endereço', 'updated_at' => $agora]);

        DB::table('servico_nomenclaturas')
            ->where('para', 'Revisão de Endereço - Alteração de Espaço')
            ->update(['para' => 'Revisão de Espaço', 'updated_at' => $agora]);

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
