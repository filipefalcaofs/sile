<?php

namespace App\Enums;

/**
 * Função de IA que originou a sugestão (Fase 14). Cada onda do AI-SPEC registra
 * suas funções aqui — a Onda 1 (documentos) usa ocr/classificacao/ilegibilidade/
 * inconsistencias; as ondas 2-3 (síntese e assistentes) já têm seus casos
 * declarados para o ledger ai_suggestions ser estável quando entrarem.
 */
enum AiSuggestionType: string
{
    case Ocr = 'ocr';
    case Classificacao = 'classificacao';
    case Ilegibilidade = 'ilegibilidade';
    case Inconsistencias = 'inconsistencias';
    case ResumoSolicitacao = 'resumo_solicitacao';
    case ResumoProcesso = 'resumo_processo';
    case Parecer = 'parecer';
    case Explicacao = 'explicacao';
    case Assistente = 'assistente';

    public function label(): string
    {
        return match ($this) {
            self::Ocr => 'Leitura de documento (OCR)',
            self::Classificacao => 'Classificação documental',
            self::Ilegibilidade => 'Detecção de ilegibilidade',
            self::Inconsistencias => 'Detecção de inconsistências',
            self::ResumoSolicitacao => 'Resumo da solicitação',
            self::ResumoProcesso => 'Resumo do processo',
            self::Parecer => 'Minuta de parecer',
            self::Explicacao => 'Explicação ao cidadão',
            self::Assistente => 'Assistente conversacional',
        };
    }
}
