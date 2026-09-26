<?php

namespace App\Support;

use App\Models\ViabilityDecision;

/**
 * Parecer da decisão como quem consulta o processo o lê: resultado, motivo
 * (justificativa) e fundamentação legal. Mesmo formato na gestão e no portal.
 */
final class ParecerDecisao
{
    /**
     * @return array{outcome: string, outcome_label: string, motivo: ?string, fundamentacao: list<string>, decidido_em: ?string}|null
     */
    public static function de(?ViabilityDecision $decision): ?array
    {
        if ($decision === null) {
            return null;
        }

        $fundamentacao = is_array($decision->fundamentacao)
            ? array_values(array_filter($decision->fundamentacao, fn (mixed $linha): bool => is_string($linha) && trim($linha) !== ''))
            : [];

        $motivo = trim((string) $decision->reason);

        return [
            'outcome' => $decision->outcome->value,
            'outcome_label' => $decision->isDeferida() ? 'Deferida' : 'Indeferida',
            'motivo' => $motivo !== '' ? $motivo : null,
            'fundamentacao' => $fundamentacao,
            'decidido_em' => $decision->decided_at?->toIso8601String(),
        ];
    }
}
