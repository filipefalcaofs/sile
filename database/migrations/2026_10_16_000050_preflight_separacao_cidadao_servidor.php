<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Pré-voo da separação cidadão x servidor, antes de qualquer escrita: se uma coluna de
     * servidor (FK para `users` fora das colunas convertidas) citar uma conta de cidadão, a
     * migração para aqui. A migration 000900 repete a checagem antes de apagar.
     */
    public function up(): void
    {
        $conflitos = IdentidadeSeparada::colunasDeServidorQueCitam(
            IdentidadeSeparada::consultaContasDeCidadao()->select('users.id'),
        );

        if ($conflitos !== []) {
            throw new RuntimeException(
                'Colunas de servidor apontam para contas de cidadão que sairiam de users: '
                .implode(', ', $conflitos).'. Corrigir os dados antes de migrar.'
            );
        }
    }

    public function down(): void {}
};
