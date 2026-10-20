<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Atribui uma permissão (por padrão `analisar-processos`) a todos os perfis que
 * ainda não a têm. Perfis protegidos (administrador e cidadão) ficam de fora.
 * Sem `--aplicar` apenas lista (simulação). Vale para todos os usuários que
 * têm cada perfil, hoje e no futuro.
 */
class PerfisAtribuirPermissaoCommand extends Command
{
    protected $signature = 'perfis:atribuir-permissao
        {permissao=analisar-processos : Nome da permissão a atribuir}
        {--aplicar : Grava a permissão; sem a opção só lista quais perfis seriam alterados}';

    protected $description = 'Atribui uma permissão (padrão: analisar-processos) a todos os perfis que ainda não a têm, exceto os protegidos (simulação por padrão)';

    public function handle(AuditService $audit): int
    {
        $nome = (string) $this->argument('permissao');

        if (! Permission::query()->where('name', $nome)->where('guard_name', 'web')->exists()) {
            $this->error("A permissão \"{$nome}\" não existe.");

            return self::FAILURE;
        }

        $perfis = Role::query()
            ->where('guard_name', 'web')
            ->where('protegido', false)
            ->whereDoesntHave('permissions', fn ($permissoes) => $permissoes->where('name', $nome))
            ->orderBy('name')
            ->get();

        // Conta pela tabela de vínculos: a relação `users` do Spatie segue o guard
        // padrão, que no terminal pode apontar para outro modelo.
        $usuariosPorPerfil = DB::table('model_has_roles')
            ->where('model_type', (new User)->getMorphClass())
            ->selectRaw('role_id, count(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        if ($perfis->isEmpty()) {
            $this->info('Nenhum perfil a alterar.');

            return self::SUCCESS;
        }

        $this->table(
            ['Perfil', 'Usuários'],
            $perfis->map(fn (Role $perfil): array => [$perfil->name, (int) ($usuariosPorPerfil[$perfil->id] ?? 0)])->all(),
        );

        if (! $this->option('aplicar')) {
            $this->warn("{$perfis->count()} perfil(is) receberiam \"{$nome}\". Rode com --aplicar para gravar.");

            return self::SUCCESS;
        }

        foreach ($perfis as $perfil) {
            $perfil->givePermissionTo($nome);

            $audit->log(
                logName: 'perfis',
                event: 'permissao-atribuida',
                description: "Permissão {$nome} atribuída ao perfil por comando",
                properties: ['perfil' => $perfil->name, 'permissao' => $nome, 'usuarios' => (int) ($usuariosPorPerfil[$perfil->id] ?? 0)],
                subject: $perfil,
            );
        }

        $this->info("{$perfis->count()} perfil(is) alterado(s).");

        return self::SUCCESS;
    }
}
