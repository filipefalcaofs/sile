<?php

namespace App\Enums;

/**
 * Resultado escolhido na tramitação "Conclusão de Análise" (e na baixa da
 * malha fina, que usa a mesma lista). Deferido/Indeferido mapeiam os
 * desfechos já existentes da viabilidade; Desativado, Cassado e Revogado
 * encerram o processo — inclusive um já deferido.
 */
enum ConclusionOutcome: string
{
    case Deferido = 'deferido';
    case Indeferido = 'indeferido';
    case Desativado = 'desativado';
    case Cassado = 'cassado';
    case Revogado = 'revogado';

    public function label(): string
    {
        return match ($this) {
            self::Deferido => 'Deferido',
            self::Indeferido => 'Indeferido',
            self::Desativado => 'Desativado',
            self::Cassado => 'Cassado',
            self::Revogado => 'Revogado',
        };
    }

    public function status(): ViabilityRequestStatus
    {
        return match ($this) {
            self::Deferido => ViabilityRequestStatus::Deferida,
            self::Indeferido => ViabilityRequestStatus::Indeferida,
            self::Desativado => ViabilityRequestStatus::Desativado,
            self::Cassado => ViabilityRequestStatus::Cassado,
            self::Revogado => ViabilityRequestStatus::Revogado,
        };
    }

    public function decisionOutcome(): DecisionOutcome
    {
        return match ($this) {
            self::Deferido => DecisionOutcome::Deferida,
            self::Indeferido => DecisionOutcome::Indeferida,
            self::Desativado => DecisionOutcome::Desativado,
            self::Cassado => DecisionOutcome::Cassado,
            self::Revogado => DecisionOutcome::Revogado,
        };
    }

    public function encerraDeferimento(): bool
    {
        return match ($this) {
            self::Desativado, self::Cassado, self::Revogado => true,
            self::Deferido, self::Indeferido => false,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $resultado): array => ['value' => $resultado->value, 'label' => $resultado->label()],
            self::cases(),
        );
    }
}
