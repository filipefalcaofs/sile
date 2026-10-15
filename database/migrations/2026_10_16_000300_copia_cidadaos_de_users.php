<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IdentidadeSeparada::copiarContasDeCidadao();
    }

    /**
     * Sem reversão: apagar `cidadaos` em cascata destruiria vínculos já retargetados.
     * A reversão completa é a restauração do backup do banco (consolidada nas Tarefas 12/13).
     */
    public function down(): void {}
};
