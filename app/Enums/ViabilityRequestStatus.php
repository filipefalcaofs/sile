<?php

namespace App\Enums;

/**
 * Estado da solicitação de viabilidade (EP08). label() é o rótulo técnico da
 * retaguarda; publicLabel() fala ao cidadão em linguagem simples na consulta de
 * protocolo (HU-069 RN-004).
 *
 * ATIVOS nesta fase: Rascunho, Protocolada, Cancelada — as únicas transições da
 * ViabilityRequestStateMachine. Os demais são GANCHOS das fases seguintes
 * (AguardandoBap = Regin/Fase 13; EmAnalise/Deferida/Indeferida/EmPendencia =
 * EP09/10/11): existem como casos para a timeline e os listeners futuros, mas a
 * máquina NÃO os transiciona aqui (cada fase só ADICIONA entradas no mapa).
 */
enum ViabilityRequestStatus: string
{
    case Rascunho = 'rascunho';
    case Protocolada = 'protocolada';
    case Cancelada = 'cancelada';

    // Ganchos futuros (não transicionados nesta fase).
    case AguardandoBap = 'aguardando_bap';
    case EmAnalise = 'em_analise';
    case Deferida = 'deferida';
    case Indeferida = 'indeferida';
    case EmPendencia = 'em_pendencia';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Protocolada => 'Protocolada',
            self::Cancelada => 'Cancelada',
            self::AguardandoBap => 'Aguardando Junta (BAP)',
            self::EmAnalise => 'Em análise',
            self::Deferida => 'Deferida',
            self::Indeferida => 'Indeferida',
            self::EmPendencia => 'Em pendência',
        };
    }

    public function publicLabel(): string
    {
        return match ($this) {
            self::Rascunho => 'Em preenchimento',
            self::Protocolada => 'Recebida — em processamento',
            self::Cancelada => 'Cancelada',
            self::AguardandoBap => 'Aguardando confirmação da Junta Comercial — nada a fazer por enquanto',
            self::EmAnalise => 'Em análise técnica',
            self::Deferida => 'Deferida — viabilidade reconhecida',
            self::Indeferida => 'Indeferida',
            self::EmPendencia => 'Pendência — ação necessária do requerente',
        };
    }
}
