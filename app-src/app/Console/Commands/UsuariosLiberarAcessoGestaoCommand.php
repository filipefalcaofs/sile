<?php

namespace App\Console\Commands;

use App\Enums\AuthType;
use App\Models\User;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Libera `acessar-gestao` (permissão direta) aos usuários internos com perfil
 * que ainda não o têm, nem por perfil nem direto. Ficam de fora o administrador,
 * contas do AD, contas de sistema, usuários inativos e quem não tem perfil
 * (ou só tem o papel legado `cidadao`). Sem `--aplicar` apenas lista (simulação).
 */
class UsuariosLiberarAcessoGestaoCommand extends Command
{
    protected $signature = 'usuarios:liberar-acesso-gestao {--aplicar : Grava a permissão; sem a opção só lista quem seria liberado}';

    protected $description = 'Dá acessar-gestao aos usuários internos com perfil que ainda não o têm (simulação por padrão)';

    public function handle(AuditService $audit): int
    {
        $usuarios = User::query()
            ->whereNull('inactivated_at')
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', 'administrador'))
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', '!=', 'cidadao'))
            ->semContasDeSistema()
            ->where('auth_type', '!=', AuthType::Ad->value)
            ->whereDoesntHave('roles.permissions', fn (Builder $permissoes) => $permissoes->where('name', 'acessar-gestao'))
            ->whereDoesntHave('permissions', fn (Builder $permissoes) => $permissoes->where('name', 'acessar-gestao'))
            ->with('roles:id,name')
            ->orderBy('name')
            ->get();

        if ($usuarios->isEmpty()) {
            $this->info('Nenhum usuário a liberar.');

            return self::SUCCESS;
        }

        $aplicar = (bool) $this->option('aplicar');

        $this->table(
            ['ID', 'Nome', 'Perfis'],
            $usuarios->map(fn (User $usuario): array => [
                $usuario->id,
                $usuario->name,
                $usuario->roles->pluck('name')->sort()->implode(', '),
            ])->all(),
        );

        if (! $aplicar) {
            $this->warn("{$usuarios->count()} usuário(s) seriam liberados. Rode com --aplicar para gravar.");

            return self::SUCCESS;
        }

        foreach ($usuarios as $usuario) {
            $usuario->givePermissionTo('acessar-gestao');

            $audit->log(
                logName: 'usuarios',
                event: 'acesso-gestao-liberado',
                description: 'Acesso à gestão liberado por comando',
                properties: ['perfis' => $usuario->roles->pluck('name')->sort()->values()->all()],
                subject: $usuario,
            );
        }

        $this->info("{$usuarios->count()} usuário(s) liberado(s).");

        return self::SUCCESS;
    }
}
