<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Pré-voo da separação cidadão x servidor, antes de qualquer escrita: se uma coluna de
     * servidor (FK para `users` fora das colunas convertidas) citar uma conta de cidadão, a
     * migração para aqui, sem alterar nada. Se só restarem colunas nullable com ON DELETE SET NULL
     * (o código antigo gravava o cidadão como autor de sugestões de IA e de transições), as
     * referências são anuladas, como a exclusão da conta faria. A migration 000900 repete a checagem.
     */
    public function up(): void
    {
        $cidadaos = IdentidadeSeparada::consultaContasDeCidadao()->select('users.id');
        $conflitos = IdentidadeSeparada::colunasDeServidorQueCitam($cidadaos, ignorarSetNull: true);

        if ($conflitos !== []) {
            throw new RuntimeException(
                'Colunas de servidor apontam para contas de cidadão que sairiam de users: '
                .implode(', ', $conflitos).'. Corrigir os dados antes de migrar.'
            );
        }

        $anuladas = IdentidadeSeparada::anularReferenciasDeCidadaosEmColunasSetNull($cidadaos);

        if ($anuladas !== []) {
            Log::info('Pré-voo da separação cidadão x servidor anulou referências de cidadão: '.implode(', ', $anuladas));
        }
    }

    public function down(): void {}
};
