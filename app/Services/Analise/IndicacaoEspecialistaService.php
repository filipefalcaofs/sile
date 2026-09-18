<?php

namespace App\Services\Analise;

use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Enums\DecisionOutcome;
use App\Enums\ResultadoViabilidade;
use App\Models\AiSuggestion;
use App\Models\AnalysisRecord;
use App\Models\ViabilityRequest;

/**
 * Indicação de desfecho para a ficha em análise: o especialista sugere
 * deferir ou indeferir; o analista decide. Nunca grava decisão.
 */
class IndicacaoEspecialistaService
{
    /**
     * @return array{
     *     desfecho: string|null,
     *     desfecho_label: string,
     *     motivo: string,
     *     fonte: string,
     *     analista_decide: true,
     *     por_cnae: list<array{cnae: string, status_sugerido: string|null, desfecho: string|null}>
     * }
     */
    public function paraProcesso(ViabilityRequest $processo): array
    {
        $ficha = $processo->currentAnalysisRecord;

        return $this->paraFicha($ficha, $this->parecerMaisRecente($processo));
    }

    /**
     * @return array{
     *     desfecho: string|null,
     *     desfecho_label: string,
     *     motivo: string,
     *     fonte: string,
     *     analista_decide: true,
     *     por_cnae: list<array{cnae: string, status_sugerido: string|null, desfecho: string|null}>
     * }
     */
    public function paraFicha(?AnalysisRecord $ficha, ?AiSuggestion $parecer = null): array
    {
        $porCnae = [];
        $desfechos = [];

        foreach (array_values((array) ($ficha?->per_cnae ?? [])) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $sugerido = isset($item['status_sugerido']) ? (string) $item['status_sugerido'] : null;
            $desfecho = $this->normalizar($sugerido);
            $porCnae[] = [
                'cnae' => (string) ($item['cnae'] ?? ''),
                'status_sugerido' => $sugerido,
                'desfecho' => $desfecho,
            ];
            $desfechos[] = $desfecho;
        }

        $motor = $this->consolidar($desfechos);
        $ia = $this->recomendacaoDoParecer($parecer);
        $desfecho = $motor;
        $fonte = 'motor';

        if ($motor !== null && $ia === $motor) {
            $fonte = 'motor_e_ia';
        }

        return [
            'desfecho' => $desfecho,
            'desfecho_label' => $this->rotulo($desfecho),
            'motivo' => $this->motivo($ficha, $desfecho, $porCnae),
            'fonte' => $fonte,
            'analista_decide' => true,
            'por_cnae' => $porCnae,
        ];
    }

    private function parecerMaisRecente(ViabilityRequest $processo): ?AiSuggestion
    {
        return AiSuggestion::query()
            ->where('viability_request_id', $processo->id)
            ->where('type', AiSuggestionType::Parecer)
            ->where('status', AiSuggestionStatus::Sugerida)
            ->orderByDesc('id')
            ->first();
    }

    private function normalizar(?string $status): ?string
    {
        return match ($status) {
            DecisionOutcome::Deferida->value,
            ResultadoViabilidade::Permitido->value,
            ResultadoViabilidade::PermitidoComCondicoes->value => DecisionOutcome::Deferida->value,
            DecisionOutcome::Indeferida->value,
            ResultadoViabilidade::NaoPermitido->value => DecisionOutcome::Indeferida->value,
            default => null,
        };
    }

    /**
     * @param  list<string|null>  $desfechos
     */
    private function consolidar(array $desfechos): ?string
    {
        if ($desfechos === [] || in_array(null, $desfechos, true)) {
            return null;
        }

        foreach ($desfechos as $desfecho) {
            if ($desfecho !== DecisionOutcome::Deferida->value) {
                return DecisionOutcome::Indeferida->value;
            }
        }

        return DecisionOutcome::Deferida->value;
    }

    private function recomendacaoDoParecer(?AiSuggestion $parecer): ?string
    {
        if ($parecer === null || ! is_array($parecer->output)) {
            return null;
        }

        $bruta = $parecer->output['recomendacao'] ?? null;

        return is_string($bruta) ? $this->normalizar($bruta) : null;
    }

    private function rotulo(?string $desfecho): string
    {
        return match ($desfecho) {
            DecisionOutcome::Deferida->value => 'Deferir',
            DecisionOutcome::Indeferida->value => 'Indeferir',
            default => 'Sem indicação de deferir ou indeferir',
        };
    }

    /**
     * @param  list<array{cnae: string, status_sugerido: string|null, desfecho: string|null}>  $porCnae
     */
    private function motivo(?AnalysisRecord $ficha, ?string $desfecho, array $porCnae): string
    {
        foreach (array_values((array) ($ficha?->per_cnae ?? [])) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $justificativa = trim((string) ($item['justificativa'] ?? ''));
            if ($justificativa !== '') {
                return $justificativa;
            }

            $tendencia = trim((string) ($item['tendencia_label'] ?? ''));
            if ($tendencia !== '') {
                return $tendencia;
            }
        }

        if ($desfecho === DecisionOutcome::Deferida->value) {
            return 'O motor enquadrou as atividades como passíveis de deferimento.';
        }

        if ($desfecho === DecisionOutcome::Indeferida->value) {
            return 'O motor enquadrou ao menos uma atividade como passível de indeferimento.';
        }

        if ($porCnae === []) {
            return 'Não há enquadramento do motor nesta ficha. O analista decide o desfecho.';
        }

        return 'O motor não fechou o enquadramento locacional. O analista decide se defere ou indefere.';
    }
}
