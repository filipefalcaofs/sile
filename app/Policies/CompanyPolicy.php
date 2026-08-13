<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\Representation\CurrentRepresentation;

/**
 * Autorização sobre o cadastro empresarial integrada à representação "em
 * nome de" (Fase 1, [01-07]): toda checagem é sobre o usuário EFETIVO — o
 * próprio, ou o representado quando há representação ativa. A listagem
 * (index) é escopada no controller; aqui ficam as ações por empresa.
 */
class CompanyPolicy
{
    /**
     * Usuário efetivo: o representado quando em representação ativa, senão
     * o próprio usuário autenticado ([01-07]).
     */
    private function effectiveUser(User $user): User
    {
        return app(CurrentRepresentation::class)->grantor() ?? $user;
    }

    /**
     * Ver a empresa: qualquer vínculo do usuário efetivo (ativo OU encerrado
     * — o histórico do vínculo continua visível, HU-027/HU-028).
     */
    public function view(User $user, Company $company): bool
    {
        return $company->links()
            ->where('user_id', $this->effectiveUser($user)->id)
            ->exists();
    }

    /**
     * Atualizar dados da empresa: somente com vínculo ATIVO do usuário
     * efetivo (HU-024 CA-04).
     */
    public function update(User $user, Company $company): bool
    {
        return $company->links()
            ->where('user_id', $this->effectiveUser($user)->id)
            ->whereNull('ended_at')
            ->exists();
    }

    /**
     * Gerir CNAEs (principal/secundários): exige vínculo ATIVO (HU-025/026).
     */
    public function manageCnaes(User $user, Company $company): bool
    {
        return $company->links()
            ->where('user_id', $this->effectiveUser($user)->id)
            ->whereNull('ended_at')
            ->exists();
    }

    /**
     * Encerrar o próprio vínculo: exige vínculo ATIVO do usuário efetivo
     * (HU-028).
     */
    public function endLink(User $user, Company $company): bool
    {
        return $company->links()
            ->where('user_id', $this->effectiveUser($user)->id)
            ->whereNull('ended_at')
            ->exists();
    }
}
