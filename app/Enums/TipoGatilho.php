<?php

namespace App\Enums;

/**
 * Tipos de gatilho de risco (categoria semi-expresso) que, quando acionados,
 * derrubam o encaminhamento para análise técnica com motivo auditado
 * (HU-049/HU-051). São os 3 conhecidos hoje (CONTEXT da Fase 6); a lista
 * definitiva é parametrizada na tabela risk_triggers (pendente SEDUR), nunca
 * hardcoded — novos gatilhos entram como dado, não como código.
 */
enum TipoGatilho: string
{
    case EnquadramentoAusente = 'enquadramento_ausente';
    case ZeisEspecial = 'zeis_especial';
    case DadosDoProcesso = 'dados_do_processo';

    public function label(): string
    {
        return match ($this) {
            self::EnquadramentoAusente => 'Enquadramento ausente',
            self::ZeisEspecial => 'ZEIS especial',
            self::DadosDoProcesso => 'Dados do processo',
        };
    }
}
