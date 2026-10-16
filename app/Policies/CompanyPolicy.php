<?php

namespace App\Policies;

use App\Models\Cidadao;
use App\Models\Company;
use App\Support\Representation\CurrentRepresentation;

/**
 * Autorização sobre o cadastro empresarial integrada à representação "em
 * nome de" (Fase 1, [01-07]): toda checagem é sobre o cidadão EFETIVO — o
 * próprio, ou o representado quando há representação ativa. A listagem
 * (index) é escopada no controller; aqui ficam as ações por empresa.
 */
class CompanyPolicy
{
    /**
     * Cidadão efetivo: o representado quando em representação ativa, senão
     * o próprio cidadão autenticado ([01-07]).
     */
    private function effectiveUser(Cidadao $cidadao): Cidadao
    {
        return app(CurrentRepresentation::class)->grantor() ?? $cidadao;
    }

    /**
     * Ver a empresa: qualquer vínculo do cidadão efetivo (ativo OU encerrado
     * — o histórico do vínculo continua visível, HU-027/HU-028).
     */
    public function view(Cidadao $cidadao, Company $company): bool
    {
        return $company->links()
            ->where('cidadao_id', $this->effectiveUser($cidadao)->id)
            ->exists();
    }

    /**
     * Atualizar dados da empresa: somente com vínculo ATIVO do cidadão
     * efetivo (HU-024 CA-04).
     */
    public function update(Cidadao $cidadao, Company $company): bool
    {
        return $company->links()
            ->where('cidadao_id', $this->effectiveUser($cidadao)->id)
            ->whereNull('ended_at')
            ->exists();
    }

    /**
     * Gerir CNAEs (principal/secundários): exige vínculo ATIVO (HU-025/026).
     */
    public function manageCnaes(Cidadao $cidadao, Company $company): bool
    {
        return $company->links()
            ->where('cidadao_id', $this->effectiveUser($cidadao)->id)
            ->whereNull('ended_at')
            ->exists();
    }

    /**
     * Encerrar o próprio vínculo: exige vínculo ATIVO do cidadão efetivo
     * (HU-028).
     */
    public function endLink(Cidadao $cidadao, Company $company): bool
    {
        return $company->links()
            ->where('cidadao_id', $this->effectiveUser($cidadao)->id)
            ->whereNull('ended_at')
            ->exists();
    }
}
