<?php

namespace App\Policies;

use App\Enums\ViabilityRequestStatus;
use App\Models\Cidadao;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Representation\CurrentRepresentation;

/**
 * Autorização sobre a solicitação de viabilidade (HU-061 CA-04) integrada à
 * representação "em nome de" (Fase 1, [01-07]) — espelha a CompanyPolicy
 * ([03-04]): toda checagem é sobre o cidadão EFETIVO (o próprio, ou o
 * representado quando há representação ativa); servidor não opera pelo portal. A listagem "Minhas
 * solicitações" é escopada no controller; aqui ficam as ações por solicitação.
 */
class ViabilityRequestPolicy
{
    /**
     * O ator é cidadão e o requerente da solicitação é ele (ou o representado
     * em representação ativa). Servidor (User) nunca é dono: o portal é do
     * cidadão e ids de users e cidadaos podem coincidir.
     */
    private function owns(User|Cidadao $ator, ViabilityRequest $request): bool
    {
        if (! $ator instanceof Cidadao) {
            return false;
        }

        $efetivo = app(CurrentRepresentation::class)->grantor() ?? $ator;

        return $request->requester_cidadao_id === $efetivo->id;
    }

    /**
     * Ver a solicitação: somente o dono/representado (qualquer status — o
     * histórico continua visível ao requerente).
     */
    public function view(User|Cidadao $user, ViabilityRequest $request): bool
    {
        return $this->owns($user, $request);
    }

    /**
     * Editar a solicitação: dono E status rascunho (protocolada/cancelada não
     * são mais editáveis).
     */
    public function update(User|Cidadao $user, ViabilityRequest $request): bool
    {
        return $this->owns($user, $request)
            && $request->status === ViabilityRequestStatus::Rascunho;
    }

    /**
     * Protocolar: dono E status rascunho (HU-068, fluxo do protocolo no 08-10).
     */
    public function protocol(User|Cidadao $user, ViabilityRequest $request): bool
    {
        return $this->owns($user, $request)
            && $request->status === ViabilityRequestStatus::Rascunho;
    }

    /**
     * Cancelar: dono E ainda não decidido (rascunho ou protocolada). A regra
     * fina dos estados canceláveis por parâmetro fica no 08-12; a policy só
     * garante propriedade + não-decidido.
     */
    public function cancel(User|Cidadao $user, ViabilityRequest $request): bool
    {
        return $this->owns($user, $request)
            && in_array($request->status, [
                ViabilityRequestStatus::Rascunho,
                ViabilityRequestStatus::Protocolada,
            ], true);
    }
}
