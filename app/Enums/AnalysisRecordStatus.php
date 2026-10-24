<?php

namespace App\Enums;

/**
 * Estado da ficha de análise versionada (HU-126). A revisão nasce Rascunho
 * (editável/autosave) e vira Finalizada — imutável após finalizar (RN-003):
 * uma nova mudança gera nova revisão, nunca atualiza a finalizada. Substituida
 * marca o rascunho antigo que ficou aberto ao lado de outro do mesmo analista
 * (dado anterior à HU.183): não aceita edição e o conteúdo é mantido.
 */
enum AnalysisRecordStatus: string
{
    case Rascunho = 'rascunho';
    case Finalizada = 'finalizada';
    case Substituida = 'substituida';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Finalizada => 'Finalizada',
            self::Substituida => 'Substituída',
        };
    }

    public function isFinalizada(): bool
    {
        return $this === self::Finalizada;
    }
}
