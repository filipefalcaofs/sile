<?php

namespace App\Services\Analise;

use App\Models\AnalysisRecord;
use RuntimeException;

/**
 * Tentativa de editar/finalizar uma revisão FINALIZADA da ficha (HU-135 RN-003):
 * a revisão finalizada é imutável — qualquer ajuste posterior nasce numa NOVA
 * revisão. O caller traduz esta exceção em 422 (não edita silenciosamente).
 */
class AnalysisRecordImutavelException extends RuntimeException
{
    public static function finalizada(AnalysisRecord $record): self
    {
        return new self("A revisão {$record->revision} da ficha está finalizada e é imutável (RN-003).");
    }
}
