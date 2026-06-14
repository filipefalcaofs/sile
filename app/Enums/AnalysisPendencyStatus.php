<?php

namespace App\Enums;

/**
 * Estado da pendência da análise (HU-083/084). Aberta = aguardando resposta do
 * requerente dentro do prazo (due_at); Respondida = resposta recebida; Expirada
 * = prazo vencido sem resposta (a rotina de SLA marca e segue o trâmite).
 */
enum AnalysisPendencyStatus: string
{
    case Aberta = 'aberta';
    case Respondida = 'respondida';
    case Expirada = 'expirada';

    public function label(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Respondida => 'Respondida',
            self::Expirada => 'Expirada',
        };
    }
}
