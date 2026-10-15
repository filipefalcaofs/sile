<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IdentidadeSeparada::retargetar('company_user', 'user_id', 'cidadao_id', false);
        IdentidadeSeparada::retargetar('gov_br_accounts', 'user_id', 'cidadao_id', false);
    }

    /**
     * Sem reversão: devolver as chaves para `users` exigiria desfazer a separação inteira.
     * A reversão completa é a restauração do backup do banco (consolidada nas Tarefas 12/13).
     */
    public function down(): void {}
};
