<?php

namespace App\Services\Analise;

use App\Models\User;
use App\Models\ViabilityRequest;
use RuntimeException;

/**
 * Distribuição/assunção não permitida (HU-080/081): o processo não está numa
 * caixa de setor, ou o analista não está vinculado ao setor do processo. A
 * atribuição NÃO acontece e o caller trata o erro — no lote (RN-007), vira uma
 * falha por item, sem abortar os demais.
 */
class DistribuicaoException extends RuntimeException
{
    public static function semSetor(ViabilityRequest $request): self
    {
        return new self("Solicitação #{$request->id} não está em uma caixa de setor — não pode ser distribuída.");
    }

    public static function analistaForaDoSetor(User $analista, ViabilityRequest $request): self
    {
        return new self("Analista #{$analista->id} não está vinculado ao setor da solicitação #{$request->id}.");
    }
}
