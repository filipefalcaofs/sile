<?php

namespace App\Services\Analise;

/**
 * Semáforo do SLA da fila de análise (HU-144), calculado ON-THE-FLY pelo
 * AnalysisSlaService a partir do analysis_due_at materializado — NUNCA
 * persistido como cor. Verde = dentro do prazo e abaixo do limiar; Amarelo =
 * atingiu o limiar parametrizável (analise.sla.semaforo.amarelo_percentual);
 * Vermelho = prazo estourado. O tempo restante sai como valor separado no
 * statusFor (não é responsabilidade deste enum). label() alimenta o badge da UI.
 */
enum SlaStatus: string
{
    case Verde = 'verde';
    case Amarelo = 'amarelo';
    case Vermelho = 'vermelho';

    public function label(): string
    {
        return match ($this) {
            self::Verde => 'No prazo',
            self::Amarelo => 'Em alerta',
            self::Vermelho => 'Vencido',
        };
    }
}
