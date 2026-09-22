<?php

namespace App\Enums;

/**
 * Estado HONESTO de uma sugestão de IA (Fase 14). A IA NUNCA decide
 * (AI-SPEC Failure Mode #1): por isso não existe — e jamais pode existir — o
 * estado "decidida".
 * - Sugerida: gerada e disponível para revisão humana.
 * - EscaladaHumano: guardrail acionado (baixa confiança, fonte ausente ou sinal
 *   de ilegibilidade) — exige análise humana antes de qualquer uso.
 * - Descartada: o analista rejeitou a sugestão.
 * - Aplicada: o analista aproveitou a sugestão na sua decisão (que segue humana).
 */
enum AiSuggestionStatus: string
{
    case Sugerida = 'sugerida';
    case EscaladaHumano = 'escalada_humano';
    case Descartada = 'descartada';
    case Aplicada = 'aplicada';

    public function label(): string
    {
        return match ($this) {
            self::Sugerida => 'Sugerida',
            self::EscaladaHumano => 'Escalada para análise humana',
            self::Descartada => 'Descartada',
            self::Aplicada => 'Aplicada',
        };
    }
}
