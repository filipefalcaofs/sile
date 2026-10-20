<?php

namespace App\Services\Vistoria;

use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quem vê e abre cada processo de vistoria. Quem distribui vê tudo; o
 * vistoriador, só o que está atribuído a ele ou cuja ficha é dele, como na
 * Caixa de entrada. Quem analisou o processo também consulta a ficha de
 * vistoria (somente leitura) na tramitação.
 */
class VistoriaAcesso
{
    /**
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    public function escopar(Builder $query, User $user): Builder
    {
        if ($user->can('distribuir-processos')) {
            return $query;
        }

        return $query->where(fn (Builder $dele) => $dele
            ->where('assigned_user_id', $user->id)
            ->orWhereHas('inspection', fn (Builder $ficha) => $ficha->where('vistoriador_user_id', $user->id)));
    }

    /**
     * Abre e preenche a ficha: quem distribui, o responsável pelo processo ou
     * o vistoriador da ficha.
     */
    public function podeAtuar(ViabilityRequest $processo, User $user): bool
    {
        return $user->can('distribuir-processos')
            || $processo->assigned_user_id === $user->id
            || $processo->inspection()->where('vistoriador_user_id', $user->id)->exists();
    }

    /**
     * Consulta a capa, o PDF, a ficha de vistoria e os anexos: quem pode atuar,
     * quem recebeu o processo para concluir e quem o analisou (ficha de análise
     * ou tramitação feita por ele).
     */
    public function podeConsultar(ViabilityRequest $processo, User $user): bool
    {
        return $this->podeAtuar($processo, $user)
            || $processo->conclusao_encaminhada_para_user_id === $user->id
            || $processo->analysisRecords()->where('analyst_user_id', $user->id)->exists()
            || $processo->analysisStatusTransitions()->where('actor_user_id', $user->id)->exists();
    }
}
