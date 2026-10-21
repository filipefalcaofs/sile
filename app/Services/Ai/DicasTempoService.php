<?php

namespace App\Services\Ai;

use App\Enums\AiSuggestionType;
use App\Jobs\Ai\DicasTempoJob;
use App\Models\AiSuggestion;
use App\Services\Relatorios\ReportFilters;

/**
 * Camada de serviço das dicas de IA para reduzir o tempo de viabilidade.
 * Degradação honesta: NÃO despacha, NÃO chama o provedor e NÃO simula quando o
 * toggle features.ia_dicas_tempo está desligado, não há provedor de texto
 * ativo ou o período não tem processo decidido. A saída é sempre sugestão ao
 * gestor.
 */
class DicasTempoService
{
    public const FUNCAO = 'dicas_tempo';

    public function __construct(
        private readonly AiFeatureGate $gate,
        private readonly DicasTempoDossie $dossie,
    ) {}

    /**
     * @return bool true se havia dados e a função estava disponível, com o job
     *              despachado; false caso contrário (ver {@see motivoDeNaoGerar()}).
     */
    public function gerar(ReportFilters $f, ?int $userId = null): bool
    {
        if (! $this->gate->available(self::FUNCAO, 'text')) {
            return false;
        }

        $dossie = $this->dossie->montar($f);

        if ($dossie === null) {
            return false;
        }

        DicasTempoJob::dispatch($dossie, $userId);

        return true;
    }

    /**
     * Motivo comunicável de a geração não ter acontecido.
     */
    public function motivoDeNaoGerar(ReportFilters $f): string
    {
        return $this->gate->unavailableReason(self::FUNCAO, 'text')
            ?? 'Não há processos decididos no período para gerar dicas.';
    }

    /**
     * Estado do card: se a função está disponível e a última geração.
     *
     * @return array{disponivel: bool, motivo: ?string, periodo: array{de: ?string, ate: ?string}, ultima: array{resumo: string, dicas: list<array<string, string>>, confianca: ?string, status: string, periodo: array{de: ?string, ate: ?string}, gerado_em: string}|null}
     */
    public function painel(?ReportFilters $f = null): array
    {
        $motivo = $this->gate->unavailableReason(self::FUNCAO, 'text');

        $ultima = AiSuggestion::query()
            ->where('type', AiSuggestionType::DicasTempo)
            ->latest('id')
            ->first();

        return [
            'disponivel' => $motivo === null,
            'motivo' => $motivo,
            'periodo' => [
                'de' => $f?->from()?->toDateString(),
                'ate' => $f?->to()?->toDateString(),
            ],
            'ultima' => $ultima === null ? null : [
                'resumo' => (string) ($ultima->output['resumo'] ?? ''),
                'dicas' => array_values((array) ($ultima->output['dicas'] ?? [])),
                'confianca' => $ultima->confidence,
                'status' => $ultima->status->value,
                'periodo' => [
                    'de' => $ultima->input_ref['periodo']['de'] ?? null,
                    'ate' => $ultima->input_ref['periodo']['ate'] ?? null,
                ],
                'gerado_em' => $ultima->created_at->toIso8601String(),
            ],
        ];
    }
}
