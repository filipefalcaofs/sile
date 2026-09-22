<?php

namespace App\Enums;

/**
 * Tipo de comunicação de PROCESSO que vira uma linha do ledger communications.
 * Cobre TODOS os tipos das Waves 3/5 do EP11 para que nenhum plano posterior
 * precise editar este enum: o ciclo de pendência (aberta/respondida/expirada),
 * os alertas de prazo (HU-093) e escalonamento por SLA (HU-147) e o resultado
 * do fluxo expresso (HU-077).
 */
enum CommunicationType: string
{
    case PendenciaAberta = 'pendencia_aberta';
    case PendenciaRespondida = 'pendencia_respondida';
    case PendenciaExpirada = 'pendencia_expirada';
    case PrazoVencendo = 'prazo_vencendo';
    case EscalonamentoSla = 'escalonamento_sla';
    case Resultado = 'resultado';

    public function label(): string
    {
        return match ($this) {
            self::PendenciaAberta => 'Convite aberto',
            self::PendenciaRespondida => 'Convite respondido',
            self::PendenciaExpirada => 'Convite expirado',
            self::PrazoVencendo => 'Prazo vencendo',
            self::EscalonamentoSla => 'Escalonamento por SLA',
            self::Resultado => 'Resultado da análise',
        };
    }
}
