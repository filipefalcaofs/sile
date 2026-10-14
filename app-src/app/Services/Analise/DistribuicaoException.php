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

    public static function analistaSemSetor(User $analista, ViabilityRequest $request): self
    {
        return new self("{$analista->name} não está vinculado a nenhum setor — a solicitação #{$request->id} não foi distribuída.");
    }

    public static function setorAmbiguo(User $analista, ViabilityRequest $request): self
    {
        return new self("{$analista->name} está em mais de um setor — escolha o setor que recebe a solicitação #{$request->id}.");
    }

    public static function setorForaDoAnalista(User $analista, ViabilityRequest $request): self
    {
        return new self("O setor escolhido não é de {$analista->name} — a solicitação #{$request->id} não foi distribuída.");
    }

    public static function jaAtribuido(ViabilityRequest $request): self
    {
        return new self("Solicitação #{$request->id} já está com um analista. A troca de responsável é feita pela redistribuição.");
    }

    public static function analiseConcluida(ViabilityRequest $request): self
    {
        return new self("Solicitação #{$request->id} já teve a análise concluída — não pode ser redistribuída.");
    }

    public static function alvoSemPermissaoDeVistoria(User $alvo, ViabilityRequest $request): self
    {
        return new self("Solicitação #{$request->id} está em vistoria — só pode ser atribuída a um vistoriador, e {$alvo->name} não tem a permissão de ficha de vistoria.");
    }
}
