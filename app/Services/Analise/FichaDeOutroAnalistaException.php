<?php

namespace App\Services\Analise;

use App\Models\AnalysisRecord;
use RuntimeException;

/**
 * Tentativa de salvar ou finalizar a ficha de análise de outro analista
 * (HU.183): cada analista grava só na própria ficha. O caller traduz em 403.
 */
class FichaDeOutroAnalistaException extends RuntimeException
{
    public static function de(AnalysisRecord $record): self
    {
        return new self("A revisão {$record->revision} da ficha pertence a outro analista. Abra a sua ficha para registrar a sua análise.");
    }
}
