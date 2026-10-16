<?php

use App\Models\User;
use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Remove de `users` as contas de cidadão já copiadas para `cidadaos` e o papel `cidadao`.
     * Antes de apagar, confere toda chave estrangeira que ainda aponta para `users`: se alguma
     * linha citar uma conta que vai sair, a migration para sem apagar nada.
     */
    public function up(): void
    {
        $conflitos = IdentidadeSeparada::colunasDeServidorQueCitam(IdentidadeSeparada::contasCopiadas());

        if ($conflitos !== []) {
            throw new RuntimeException(
                'Colunas de servidor apontam para contas de cidadão que sairiam de users: '
                .implode(', ', $conflitos).'. Revisar antes de migrar.'
            );
        }

        DB::table('model_has_roles')->where('model_type', User::class)
            ->whereIn('model_id', IdentidadeSeparada::contasCopiadas())->delete();

        DB::table('model_has_permissions')->where('model_type', User::class)
            ->whereIn('model_id', IdentidadeSeparada::contasCopiadas())->delete();

        DB::table('users')->whereIn('id', IdentidadeSeparada::contasCopiadas())->delete();

        $papel = DB::table('roles')->where('name', 'cidadao')->pluck('id');

        if ($papel->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('role_id', $papel)->delete();
            DB::table('model_has_roles')->whereIn('role_id', $papel)->delete();
            DB::table('roles')->whereIn('id', $papel)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Sem reversão: as contas já vivem em `cidadaos` e as colunas de cidadão foram
     * retargetadas; devolvê-las a `users` exigiria desfazer a separação inteira.
     * A reversão completa é a restauração do backup do banco.
     */
    public function down(): void {}
};
