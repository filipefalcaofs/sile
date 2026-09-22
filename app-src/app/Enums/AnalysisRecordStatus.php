<?php

namespace App\Enums;

/**
 * Estado da ficha de análise versionada (HU-135). A revisão nasce Rascunho
 * (editável/autosave) e vira Finalizada — imutável após finalizar (RN-003):
 * uma nova mudança gera nova revisão, nunca atualiza a finalizada.
 */
enum AnalysisRecordStatus: string
{
    case Rascunho = 'rascunho';
    case Finalizada = 'finalizada';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Finalizada => 'Finalizada',
        };
    }

    public function isFinalizada(): bool
    {
        return $this === self::Finalizada;
    }
}
