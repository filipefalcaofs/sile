<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IdentidadeSeparada::revogarProcuracoesDeServidores();

        IdentidadeSeparada::retargetar('procurations', 'grantor_user_id', 'grantor_cidadao_id', false);
        IdentidadeSeparada::retargetar('procurations', 'attorney_user_id', 'attorney_cidadao_id', false);
        IdentidadeSeparada::anularReferenciasDeNaoCidadaos('procurations', 'revoked_by_user_id');
        IdentidadeSeparada::retargetar('procurations', 'revoked_by_user_id', 'revoked_by_cidadao_id', true, 'set null');

        IdentidadeSeparada::retargetar('assisted_attendances', 'citizen_user_id', 'citizen_cidadao_id', false);

        IdentidadeSeparada::renomearSemChave('activity_log', 'acting_for_user_id', 'acting_for_cidadao_id');
    }

    /**
     * Sem reversão: devolver as chaves para `users` exigiria desfazer a separação inteira.
     * A reversão completa é a restauração do backup do banco (consolidada nas Tarefas 12/13).
     */
    public function down(): void {}
};
