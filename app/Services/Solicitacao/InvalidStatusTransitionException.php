<?php

namespace App\Services\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use RuntimeException;

/**
 * Transição de estado não permitida pela ViabilityRequestStateMachine (CA-03).
 * O estado não muda e nenhuma timeline é gravada — o caller trata o erro.
 */
class InvalidStatusTransitionException extends RuntimeException
{
    public static function para(ViabilityRequestStatus $from, ViabilityRequestStatus $to): self
    {
        return new self("Transição inválida da solicitação de viabilidade: {$from->value} → {$to->value}.");
    }
}
