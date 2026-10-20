<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Libera `acessar-gestao` (permissão direta) aos usuários internos com perfil
 * que ainda não aparecem na equipe SEDUR — e, por isso, não entram na lista de
 * vínculo usuário↔setor. Ficam de fora o administrador, quem já é equipe
 * (conta AD ou perfil com o acesso), usuários inativos e usuários do portal
 * (sem perfil ou só com `cidadao`). Sem `--aplicar` apenas lista (simulação).
 */
class UsuariosLiberarAcessoGestaoCommand extends Command
{
    protected $signature = 'usuarios:liberar-acesso-gestao {--aplicar : Grava a permissão; sem a opção só lista quem seria liberado}';

    protected $description = 'Dá acessar-gestao aos usuários internos com perfil que ainda não aparecem na lista de vínculo de setor (simulação por padrão)';

    public function handle(AuditService $audit): int
    {
        $usuarios = User::query()
            ->whereNull('inactivated_at')
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', 'administrador'))
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', '!=', 'cidadao'))
            ->whereNot(fn (Builder $equipe) => $equipe->equipeSedur())
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
