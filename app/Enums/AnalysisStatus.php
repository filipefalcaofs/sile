<?php

namespace App\Enums;

/**
 * Status OPERACIONAL da análise do processo (relatório de teste SEDUR
 * 2026-07-09, pág. 6). Eixo PARALELO ao ViabilityRequestStatus canônico: o
 * analista gerencia este estado durante a análise técnica. Convite/vistoria
 * são acionados por evento (Fases 2/3); aqui só o enum e os grupos.
 */
enum AnalysisStatus: string
{
    case ParaDistribuir = 'para_distribuir';
    case Encaminhado = 'encaminhado';
    case Analisar = 'analisar';
    case EmAnalise = 'em_analise';
    case AnaliseConcluida = 'analise_concluida';
    case EmConvite = 'em_convite';
    case ConviteRespondido = 'convite_respondido';
    case ConviteCancelado = 'convite_cancelado';
    case ConviteExpirado = 'convite_expirado';
    case Vistoriar = 'vistoriar';
    case Vistoriado = 'vistoriado';

    public function label(): string
    {
        return match ($this) {
            self::ParaDistribuir => 'Para distribuir',
            self::Encaminhado => 'Encaminhado para',
            self::Analisar => 'Analisar',
            self::EmAnalise => 'Em análise',
            self::AnaliseConcluida => 'Análise concluída',
            self::EmConvite => 'Em convite',
            self::ConviteRespondido => 'Convite respondido',
            self::ConviteCancelado => 'Convite cancelado',
            self::ConviteExpirado => 'Prazo para convite expirado',
            self::Vistoriar => 'Vistoriar',
            self::Vistoriado => 'Vistoriado',
        };
    }

    public function grupo(): string
    {
        return match ($this) {
            self::ParaDistribuir, self::Encaminhado => 'Distribuição',
            self::Analisar, self::EmAnalise, self::AnaliseConcluida => 'Análise',
            self::EmConvite, self::ConviteRespondido, self::ConviteCancelado, self::ConviteExpirado => 'Convite',
            self::Vistoriar, self::Vistoriado => 'Vistoria',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $s): array => ['value' => $s->value, 'label' => $s->label()],
            self::cases(),
        );
    }

    /**
     * Próximas transições MANUAIS oferecidas ao analista no dropdown. Espelha o
     * grafo da AnalysisStatusStateMachine, omitindo os estados dirigidos por
     * evento (respondido/expirado, setados pelo sistema).
     *
     * @return list<self>
     */
    public function proximas(): array
    {
        return match ($this) {
            self::ParaDistribuir => [self::Encaminhado],
            self::Encaminhado => [self::Analisar],
            self::Analisar => [self::EmAnalise],
            self::EmAnalise => [self::AnaliseConcluida, self::EmConvite, self::Vistoriar],
            self::EmConvite => [self::ConviteCancelado],
            self::Vistoriar => [self::Vistoriado],
            self::Vistoriado, self::ConviteRespondido, self::ConviteCancelado => [self::EmAnalise],
            self::AnaliseConcluida, self::ConviteExpirado => [],
        };
    }
}
