<?php

namespace App\Enums;

/**
 * Categoria de análise da solicitação (HU-082). Expresso = caminho elegível ao
 * fluxo automático que caiu para análise humana (ex.: sem zona); SemiExpresso =
 * gatilho de risco/CNAE que exige análise (RN-008). A categoria de CONSULTA
 * completa combina este enum + a flag in_fine_mesh + is_virtual_office — é
 * derivada na HU-082, não persistida aqui.
 */
enum AnalysisCategory: string
{
    case Expresso = 'expresso';
    case SemiExpresso = 'semi_expresso';

    public function label(): string
    {
        return match ($this) {
            self::Expresso => 'Expresso',
            self::SemiExpresso => 'Semi-expresso',
        };
    }
}
