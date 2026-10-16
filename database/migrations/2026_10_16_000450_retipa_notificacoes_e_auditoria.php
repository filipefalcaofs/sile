<?php

use App\Models\Cidadao;
use App\Models\User;
use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Só contas copiadas (`IdentidadeSeparada::contasCopiadas`, a mesma noção da migration
     * final: senha em cidadaos e mesmo id e CPF em users) são retipadas; espelhos de
     * servidor (password nulo) continuam como User.
     * A coluna acting_for_cidadao_id já foi renomeada na migration de colunas mistas.
     */
    public function up(): void
    {
        $contas = fn () => IdentidadeSeparada::contasCopiadas();

        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $contas())
            ->update(['notifiable_type' => Cidadao::class]);

        DB::table('activity_log')
            ->where('causer_type', User::class)
            ->whereIn('causer_id', $contas())
            ->update(['causer_type' => Cidadao::class]);

        DB::table('activity_log')
            ->where('subject_type', User::class)
            ->whereIn('subject_id', $contas())
            ->update(['subject_type' => Cidadao::class]);
    }

    public function down(): void
    {
        // Sem reversão: restaurar pelo backup do banco.
    }
};
