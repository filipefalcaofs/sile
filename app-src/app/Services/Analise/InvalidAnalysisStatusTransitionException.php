<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStatus;
use RuntimeException;

/**
 * Transição de estado não permitida pela AnalysisStatusStateMachine. O estado
 * não muda e nenhuma timeline é gravada — o caller trata o erro (use `force`
 * para o override do gestor).
 */
class InvalidAnalysisStatusTransitionException extends RuntimeException
{
    public static function para(?AnalysisStatus $from, AnalysisStatus $to): self
    {
        $origem = $from?->value ?? '(inicial)';

        return new self("Transição de status de análise inválida: {$origem} → {$to->value}.");
    }
}
