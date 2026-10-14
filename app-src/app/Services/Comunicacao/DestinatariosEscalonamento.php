<?php

namespace App\Services\Comunicacao;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Quem recebe escalonamento de prazo e alerta de TLL: todo usuário cujo perfil
 * tem a permissão receber-escalonamento-sla (o administrador define o perfil).
 */
class DestinatariosEscalonamento
{
    public const string PERMISSAO = 'receber-escalonamento-sla';

    /**
     * @return Collection<int, User>
     */
    public function usuarios(): Collection
    {
        $usuarios = User::query()
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', self::PERMISSAO))
            ->orderBy('id')
            ->get();

        if ($usuarios->isEmpty()) {
            Log::warning('Escalonamento sem destinatário: nenhum perfil tem a permissão '.self::PERMISSAO.'.');
        }

        return $usuarios;
    }
}
