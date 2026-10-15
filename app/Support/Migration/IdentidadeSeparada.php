<?php

namespace App\Support\Migration;

use App\Models\Cidadao;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operações compartilhadas pelas migrations da separação cidadão x servidor.
 * Os `id` das contas migradas são preservados, então as colunas "só cidadão"
 * mantêm os valores e só trocam o alvo da chave estrangeira.
 */
final class IdentidadeSeparada
{
    private const COLUNAS = [
        'id', 'name', 'cpf', 'email', 'phone', 'email_verified_at', 'password',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
        'remember_token', 'inactivated_at', 'created_at', 'updated_at',
    ];

    /**
     * Contas que não são de servidor: não AD e sem perfil com `acessar-gestao`
     * (mesmo critério de `User::scopeUsuarioPortal`).
     */
    public static function consultaContasDeCidadao(): Builder
    {
        return DB::table('users')
            ->select(self::COLUNAS)
            ->where('auth_type', '!=', 'ad')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
                    ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('permissions.name', 'acessar-gestao');
            });
    }

    public static function copiarContasDeCidadao(): void
    {
        DB::table('cidadaos')->insertUsing(self::COLUNAS, self::consultaContasDeCidadao());
        self::acertarSequencia();
    }

    /** Servidor que aparece como requerente/outorgante: ganha espelho sem senha, mesmo id. */
    public static function espelharServidor(int $userId): void
    {
        if (DB::table('cidadaos')->where('id', $userId)->exists()) {
            return;
        }

        $colunas = array_diff(self::COLUNAS, ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);

        DB::table('cidadaos')->insertUsing(
            $colunas,
            DB::table('users')->select($colunas)->where('id', $userId),
        );
    }

    public static function espelharOrfaos(string $tabela, string $coluna): void
    {
        DB::table($tabela)
            ->whereNotNull($coluna)
            ->whereNotIn($coluna, DB::table('cidadaos')->select('id'))
            ->distinct()
            ->pluck($coluna)
            ->each(fn (int $id) => self::espelharServidor($id));

        self::acertarSequencia();
    }

    /**
     * Leva a sequência de `cidadaos.id` acima dos ids de cidadão e de servidor, para que
     * ids novos não colidam com sessões antigas de servidor. Só age no PostgreSQL.
     */
    public static function acertarSequencia(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "SELECT setval(pg_get_serial_sequence('cidadaos','id'), GREATEST((SELECT COALESCE(MAX(id),0) FROM cidadaos), (SELECT COALESCE(MAX(id),0) FROM users), 1), true)"
        );
    }

    /** Troca `$antiga` (FK para users) por `$nova` (FK para cidadaos), mantendo os valores. */
    public static function retargetar(string $tabela, string $antiga, string $nova, bool $nullable, string $onDelete = 'cascade'): void
    {
        self::espelharOrfaos($tabela, $antiga);

        Schema::table($tabela, fn (Blueprint $t) => $t->dropForeign([$antiga]));
        Schema::table($tabela, fn (Blueprint $t) => $t->renameColumn($antiga, $nova));
        Schema::table($tabela, function (Blueprint $t) use ($nova, $onDelete) {
            $t->foreign($nova)->references('id')->on('cidadaos')->onDelete($onDelete);
        });
    }

    /** Converte `$antiga` (FK para users) no par `{$base}_type` + `{$base}_id`, sem FK. */
    public static function paraMorph(string $tabela, string $antiga, string $base): void
    {
        Schema::table($tabela, function (Blueprint $t) use ($base) {
            $t->string("{$base}_type", 60)->nullable();
        });

        DB::table($tabela)->whereNotNull($antiga)
            ->whereIn($antiga, DB::table('cidadaos')->select('id'))
            ->update(["{$base}_type" => Cidadao::class]);

        DB::table($tabela)->whereNotNull($antiga)->whereNull("{$base}_type")
            ->update(["{$base}_type" => User::class]);

        Schema::table($tabela, fn (Blueprint $t) => $t->dropForeign([$antiga]));
        Schema::table($tabela, fn (Blueprint $t) => $t->renameColumn($antiga, "{$base}_id"));
        Schema::table($tabela, fn (Blueprint $t) => $t->index(["{$base}_type", "{$base}_id"]));
    }
}
