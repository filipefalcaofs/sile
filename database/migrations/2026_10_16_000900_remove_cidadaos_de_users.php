<?php

use App\Models\User;
use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $conflitos = $this->colunasQueCitamContasCopiadas();

        if ($conflitos !== []) {
            throw new RuntimeException(
                'Colunas de servidor apontam para contas de cidadão que sairiam de users: '
                .implode(', ', $conflitos).'. Revisar antes de migrar.'
            );
        }

        DB::table('model_has_roles')->where('model_type', User::class)
            ->whereIn('model_id', $this->contasCopiadas())->delete();

        DB::table('model_has_permissions')->where('model_type', User::class)
            ->whereIn('model_id', $this->contasCopiadas())->delete();

        DB::table('users')->whereIn('id', $this->contasCopiadas())->delete();

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

    /**
     * Conta copiada: tem senha em `cidadaos` e há em `users` uma conta de cidadão com o mesmo
     * id E o mesmo CPF. Só o id não basta: as sequências das duas tabelas podem gerar o mesmo
     * número para pessoas diferentes depois da cópia. Espelhos de servidor (sem senha) e
     * servidores que assumiram o espelho no portal ficam fora.
     */
    private function contasCopiadas(): Builder
    {
        return DB::table('cidadaos')
            ->select('cidadaos.id')
            ->whereNotNull('cidadaos.password')
            ->whereExists(
                IdentidadeSeparada::consultaContasDeCidadao()
                    ->select(DB::raw(1))
                    ->whereColumn('users.id', 'cidadaos.id')
                    ->whereColumn('users.cpf', 'cidadaos.cpf')
            );
    }

    /** @return list<string> tabela.coluna (linhas) */
    private function colunasQueCitamContasCopiadas(): array
    {
        $conflitos = [];

        foreach (Schema::getTableListing(schemaQualified: false) as $tabela) {
            foreach (Schema::getForeignKeys($tabela) as $chave) {
                if ($chave['foreign_table'] !== 'users') {
                    continue;
                }

                foreach ($chave['columns'] as $coluna) {
                    $linhas = DB::table($tabela)->whereIn($coluna, $this->contasCopiadas())->count();

                    if ($linhas > 0) {
                        $conflitos[] = "{$tabela}.{$coluna} ({$linhas})";
                    }
                }
            }
        }

        return $conflitos;
    }
};
