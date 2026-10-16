<?php

namespace App\Support\Migration;

use App\Models\Cidadao;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

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
     * Colunas do schema antigo com FK para `users` que a separação converte (só de cidadão ou
     * mistas). Podem citar cidadãos legitimamente, então ficam fora da checagem de conflito.
     */
    public const COLUNAS_CONVERTIDAS = [
        'viability_requests.requester_user_id',
        'viability_requests.created_by_user_id',
        'viability_requests.cancelled_by_user_id',
        'viability_request_documents.uploaded_by_user_id',
        'viability_request_transitions.actor_user_id',
        'access_logs.user_id',
        'legal_term_acceptances.user_id',
        'communications.recipient_user_id',
        'company_user.user_id',
        'gov_br_accounts.user_id',
        'procurations.grantor_user_id',
        'procurations.attorney_user_id',
        'procurations.revoked_by_user_id',
        'assisted_attendances.citizen_user_id',
        'activity_log.acting_for_user_id',
        'viability_queries.user_id',
        'service_declaration_acceptances.user_id',
    ];

    /**
     * Contas de cidadão: não AD, sem `acessar-gestao` (por perfil ou permissão direta) e sem
     * nenhum perfil além dos de cidadão (`perfisDeCidadao`). Conta sem perfil é cidadã. Conta
     * com outro perfil, mesmo sem permissão (perfil herdado ainda não configurado), é de servidor.
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
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('model_has_permissions')
                    ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                    ->whereColumn('model_has_permissions.model_id', 'users.id')
                    ->where('model_has_permissions.model_type', User::class)
                    ->where('permissions.name', 'acessar-gestao');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->whereNotIn('roles.name', self::perfisDeCidadao());
            });
    }

    /**
     * `cidadao` e o perfil do parâmetro `portal.perfil_cidadao` (valor ou, sem valor, o padrão),
     * enquanto o parâmetro existir (a migration 000910 o remove).
     *
     * @return list<string>
     */
    public static function perfisDeCidadao(): array
    {
        $perfis = ['cidadao'];

        if (Schema::hasTable('parameters')) {
            $parametro = DB::table('parameters')->where('key', 'portal.perfil_cidadao')->first(['value', 'default_value']);
            $perfil = trim((string) ($parametro->value ?? $parametro->default_value ?? ''));

            if ($perfil !== '') {
                $perfis[] = $perfil;
            }
        }

        return array_values(array_unique($perfis));
    }

    /**
     * Conta copiada: tem senha em `cidadaos` e há em `users` uma conta de cidadão com o mesmo
     * id E o mesmo CPF. Só o id não basta: as sequências das duas tabelas podem gerar o mesmo
     * número para pessoas diferentes depois da cópia. Espelhos de servidor (sem senha) e
     * servidores que assumiram o espelho no portal ficam fora.
     */
    public static function contasCopiadas(): Builder
    {
        return DB::table('cidadaos')
            ->select('cidadaos.id')
            ->whereNotNull('cidadaos.password')
            ->whereExists(
                self::consultaContasDeCidadao()
                    ->select(DB::raw(1))
                    ->whereColumn('users.id', 'cidadaos.id')
                    ->whereColumn('users.cpf', 'cidadaos.cpf')
            );
    }

    /**
     * Colunas com FK para `users` (fora de `COLUNAS_CONVERTIDAS`) que citam algum id de `$ids`.
     *
     * @return list<string> tabela.coluna (linhas)
     */
    public static function colunasDeServidorQueCitam(Builder $ids): array
    {
        $conflitos = [];

        foreach (Schema::getTableListing(schemaQualified: false) as $tabela) {
            foreach (Schema::getForeignKeys($tabela) as $chave) {
                if ($chave['foreign_table'] !== 'users') {
                    continue;
                }

                foreach ($chave['columns'] as $coluna) {
                    if (in_array("{$tabela}.{$coluna}", self::COLUNAS_CONVERTIDAS, true)) {
                        continue;
                    }

                    $linhas = DB::table($tabela)->whereIn($coluna, clone $ids)->count();

                    if ($linhas > 0) {
                        $conflitos[] = "{$tabela}.{$coluna} ({$linhas})";
                    }
                }
            }
        }

        return $conflitos;
    }

    /**
     * Anula em `$tabela.$coluna` (nullable) as referências a contas que não são de cidadão,
     * para que o retarget não crie espelho de servidor (ex.: consulta feita por servidor logado).
     */
    public static function anularReferenciasDeNaoCidadaos(string $tabela, string $coluna): void
    {
        DB::table($tabela)
            ->whereNotNull($coluna)
            ->whereNotIn($coluna, self::consultaContasDeCidadao()->select('id'))
            ->update([$coluna => null]);
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

        $colunas = self::colunasDoEspelho();

        DB::table('cidadaos')->insertUsing(
            $colunas,
            DB::table('users')->select($colunas)->where('id', $userId),
        );
    }

    /** @return list<string> */
    private static function colunasDoEspelho(): array
    {
        return array_values(array_diff(self::COLUNAS, ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']));
    }

    /** Espelha, em uma só instrução, os servidores citados em `$tabela.$coluna` que ainda não estão em `cidadaos`. */
    public static function espelharOrfaos(string $tabela, string $coluna): void
    {
        $colunas = self::colunasDoEspelho();

        DB::table('cidadaos')->insertUsing(
            $colunas,
            DB::table('users')
                ->select($colunas)
                ->whereIn('id', DB::table($tabela)->whereNotNull($coluna)->select($coluna))
                ->whereNotIn('id', DB::table('cidadaos')->select('id')),
        );

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

    /**
     * Troca `$antiga` (FK para users) por `$nova` (FK para cidadaos), mantendo os valores.
     * Índices que citavam a coluna são derrubados pelo nome real e recriados sobre a coluna nova.
     */
    public static function retargetar(string $tabela, string $antiga, string $nova, bool $nullable, string $onDelete = 'cascade'): void
    {
        if (strtolower($onDelete) === 'set null' && ! $nullable) {
            throw new InvalidArgumentException("onDelete 'set null' exige coluna nullable ({$tabela}.{$antiga}).");
        }

        self::espelharOrfaos($tabela, $antiga);

        $indices = self::indicesCitando($tabela, $antiga);

        self::derrubarIndices($tabela, $indices);
        Schema::table($tabela, fn (Blueprint $t) => $t->dropForeign([$antiga]));
        Schema::table($tabela, fn (Blueprint $t) => $t->renameColumn($antiga, $nova));
        Schema::table($tabela, function (Blueprint $t) use ($nova, $onDelete) {
            $t->foreign($nova)->references('id')->on('cidadaos')->onDelete($onDelete);
        });
        self::recriarIndices($tabela, $indices, $antiga, [$nova]);
    }

    /**
     * Como `retargetar`, mas sem chave estrangeira nova: derruba a FK para users e renomeia
     * (coluna de trilha de auditoria, que não deve travar a exclusão do cidadão). Não espelha
     * servidores: ids que não são de cidadão simplesmente deixam de resolver.
     */
    public static function renomearSemChave(string $tabela, string $antiga, string $nova): void
    {
        $indices = self::indicesCitando($tabela, $antiga);

        self::derrubarIndices($tabela, $indices);
        Schema::table($tabela, fn (Blueprint $t) => $t->dropForeign([$antiga]));
        Schema::table($tabela, fn (Blueprint $t) => $t->renameColumn($antiga, $nova));
        self::recriarIndices($tabela, $indices, $antiga, [$nova]);
    }

    /**
     * Revoga (revoked_at = agora, sem autor) as procurações ainda não revogadas em que o outorgante
     * ou o procurador não é conta de cidadão, para que nenhuma sobreviva ligada a um espelho de servidor.
     */
    public static function revogarProcuracoesDeServidores(string $tabela = 'procurations', string $outorgante = 'grantor_user_id', string $procurador = 'attorney_user_id'): void
    {
        $cidadaos = self::consultaContasDeCidadao()->select('id');

        DB::table($tabela)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNotIn($outorgante, $cidadaos)->orWhereNotIn($procurador, $cidadaos))
            ->update(['revoked_at' => now()]);
    }

    /**
     * Converte `$antiga` (FK para users) no par `{$base}_type` + `{$base}_id`, sem FK.
     * O tipo vem das contas que a consulta de cidadão classifica como tal (independe do conteúdo
     * atual de `cidadaos`); o resto é User. `{base}_type` herda a obrigatoriedade da coluna original.
     * Índices que citavam a coluna passam a conter `{base}_type` antes de `{base}_id`.
     */
    public static function paraMorph(string $tabela, string $antiga, string $base): void
    {
        $obrigatoria = ! collect(Schema::getColumns($tabela))->firstWhere('name', $antiga)['nullable'];
        $indices = self::indicesCitando($tabela, $antiga);

        Schema::table($tabela, function (Blueprint $t) use ($base) {
            $t->string("{$base}_type", 60)->nullable();
        });

        DB::table($tabela)->whereNotNull($antiga)
            ->whereIn($antiga, self::consultaContasDeCidadao()->select('id'))
            ->update(["{$base}_type" => Cidadao::class]);

        DB::table($tabela)->whereNotNull($antiga)->whereNull("{$base}_type")
            ->update(["{$base}_type" => User::class]);

        if ($obrigatoria) {
            Schema::table($tabela, fn (Blueprint $t) => $t->string("{$base}_type", 60)->nullable(false)->change());
        }

        self::derrubarIndices($tabela, $indices);
        Schema::table($tabela, fn (Blueprint $t) => $t->dropForeign([$antiga]));
        Schema::table($tabela, fn (Blueprint $t) => $t->renameColumn($antiga, "{$base}_id"));

        if ($indices === []) {
            $colunas = ["{$base}_type", "{$base}_id"];
            Schema::table($tabela, fn (Blueprint $t) => $t->index($colunas, self::nomeDeIndice($tabela, $colunas, 'index')));
        } else {
            self::recriarIndices($tabela, $indices, $antiga, ["{$base}_type", "{$base}_id"]);
        }
    }

    /** @return list<array{name: string, columns: list<string>, unique: bool, primary: bool}> */
    private static function indicesCitando(string $tabela, string $coluna): array
    {
        return array_values(array_filter(
            Schema::getIndexes($tabela),
            fn (array $indice) => ! $indice['primary'] && in_array($coluna, $indice['columns'], true),
        ));
    }

    /** @param list<array{name: string, columns: list<string>, unique: bool, primary: bool}> $indices */
    private static function derrubarIndices(string $tabela, array $indices): void
    {
        if ($indices === []) {
            return;
        }

        Schema::table($tabela, function (Blueprint $t) use ($indices) {
            foreach ($indices as $indice) {
                $indice['unique'] ? $t->dropUnique($indice['name']) : $t->dropIndex($indice['name']);
            }
        });
    }

    /**
     * Recria trocando `$antiga` por `$substituta` na mesma posição, sempre com nome explícito
     * (`nomeDeIndice`), para o resultado ser o mesmo no PostgreSQL e no SQLite.
     *
     * @param  list<array{name: string, columns: list<string>, unique: bool, primary: bool}>  $indices
     * @param  list<string>  $substituta
     */
    private static function recriarIndices(string $tabela, array $indices, string $antiga, array $substituta): void
    {
        if ($indices === []) {
            return;
        }

        Schema::table($tabela, function (Blueprint $t) use ($tabela, $indices, $antiga, $substituta) {
            foreach ($indices as $indice) {
                $colunas = [];
                foreach ($indice['columns'] as $coluna) {
                    array_push($colunas, ...($coluna === $antiga ? $substituta : [$coluna]));
                }
                $indice['unique']
                    ? $t->unique($colunas, self::nomeDeIndice($tabela, $colunas, 'unique'))
                    : $t->index($colunas, self::nomeDeIndice($tabela, $colunas, 'index'));
            }
        });
    }

    /**
     * Nome padrão do Laravel quando cabe nos 63 caracteres do PostgreSQL (que trunca o excedente);
     * senão `<tabela>_<hash das colunas>_<tipo>`, com a tabela encurtada se preciso.
     *
     * @param  list<string>  $colunas
     */
    private static function nomeDeIndice(string $tabela, array $colunas, string $tipo): string
    {
        $padrao = str_replace(['-', '.'], '_', strtolower($tabela.'_'.implode('_', $colunas).'_'.$tipo));

        if (strlen($padrao) <= 63) {
            return $padrao;
        }

        $sufixo = '_'.substr(md5(implode(',', $colunas)), 0, 8).'_'.$tipo;

        return substr($tabela, 0, 63 - strlen($sufixo)).$sufixo;
    }
}
