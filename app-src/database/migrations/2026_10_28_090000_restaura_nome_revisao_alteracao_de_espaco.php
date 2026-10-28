<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A SEDUR confirmou em 28/10 que o nome do serviço de mudança de espaço no mesmo
 * endereço é "Revisão Alteração de Espaço" (documento de 23/10). A migration de
 * 27/10 chegou a renomeá-lo para "Revisão de Espaço" nos ambientes em que já rodou.
 * Só volta o nome que ainda é esse padrão: o que a SEDUR editou na tela fica como está.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('servico_nomenclaturas')
            ->where('para', 'Revisão de Espaço')
            ->update(['para' => 'Revisão Alteração de Espaço', 'updated_at' => now()]);
    }

    /**
     * Não desfaz: voltar ao nome antigo apagaria edição feita na tela depois.
     */
    public function down(): void
    {
        //
    }
};
