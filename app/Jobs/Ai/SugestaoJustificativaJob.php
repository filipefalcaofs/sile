<?php

namespace App\Jobs\Ai;

use App\Ai\Agents\SugestaoJustificativaAgent;
use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Models\AiSuggestion;
use App\Models\ViabilityRequest;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\File;

/**
 * Execução da sugestão de justificativa da atividade (regra SEDUR 22/09/2026,
 * item 6). Subclasse fina do RunAiAgentJob: monta o input de TEXTO com o
 * enquadramento OBJETIVO do motor para o CNAE (grupo de uso, fundamentos,
 * fluxo) MAIS a decisão que o analista já registrou na ficha — a IA redige a
 * minuta daquela decisão, nunca escolhe o desfecho. Atuando: quando a
 * sugestão nasce válida e o campo da atividade está vazio (e a revisão não
 * está finalizada), a minuta preenche a justificativa — sempre editável pelo
 * analista; NUNCA grava ViabilityDecision nem muda status_escolhido.
 */
class SugestaoJustificativaJob extends RunAiAgentJob
{
    private ?ViabilityRequest $processo = null;

    /** @var array<string, mixed>|null */
    private ?array $item = null;

    public function __construct(
        public readonly int $requestId,
        public readonly string $cnae,
        public readonly string $decisao,
        public readonly ?int $userId = null,
    ) {
        parent::__construct();
    }

    protected function function(): string
    {
        return 'justificativa';
    }

    protected function capability(): string
    {
        return 'text';
    }

    protected function suggestionType(): AiSuggestionType
    {
        return AiSuggestionType::Justificativa;
    }

    protected function promptVersion(): string
    {
        return SugestaoJustificativaAgent::PROMPT_VERSION;
    }

    protected function viabilityRequestId(): ?int
    {
        return $this->requestId;
    }

    protected function createdByUserId(): ?int
    {
        return $this->userId;
    }

    protected function personalData(): bool
    {
        return true;
    }

    /**
     * Entrada minimizada: o CNAE, a decisão do analista e o enquadramento do
     * motor daquela atividade (dedup por CNAE+decisão via input_hash).
     *
     * @return array<string, mixed>
     */
    protected function inputRef(): array
    {
        return [
            'viability_request_id' => $this->requestId,
            'cnae' => $this->cnae,
            'decisao' => $this->decisao,
            'enquadramento' => $this->itemCnae(),
        ];
    }

    protected function makeAgent(): Agent
    {
        return SugestaoJustificativaAgent::make();
    }

    protected function promptText(): string
    {
        $item = $this->itemCnae();
        $fundamentos = $item['fundamentacao'] ?? [];

        $linhas = [
            'Redija a MINUTA da justificativa da atividade abaixo, defendendo a '
                .'decisão que o ANALISTA já registrou na ficha. A decisão é do '
                .'analista — não a questione nem sugira outro desfecho. '
                .'Fundamente-se EXCLUSIVAMENTE no enquadramento do motor abaixo; '
                .'não invente artigo, quadro ou decreto.',
            '',
            'Atividade (CNAE): '.($item['cnae_formatado'] ?? $this->cnae),
            'Decisão do analista: '.$this->decisao,
            'Grupo de uso (LOUOS): '.($item['grupo_uso'] ?? 'não informado'),
            'Fluxo definido pela regra: '.($item['fluxo'] ?? 'não informado'),
            'Fundamentos registrados pelo motor: '
                .($fundamentos === [] ? 'não informados' : implode('; ', $fundamentos)),
        ];

        $processo = $this->processo();
        $endereco = trim(implode(', ', array_filter([
            trim((string) ($processo->address_street ?? '')),
            trim((string) ($processo->address_number ?? '')),
            trim((string) ($processo->address_neighborhood ?? '')),
        ], fn (string $parte): bool => $parte !== '')));

        $linhas[] = 'Endereço declarado: '.($endereco === '' ? 'não informado' : $endereco);
        $linhas[] = 'Área declarada (m²): '.($processo->used_area_m2 === null ? 'não informada' : (string) $processo->used_area_m2);

        return implode("\n", $linhas);
    }

    protected function afterPersisted(AiSuggestion $suggestion): void
    {
        if ($suggestion->status !== AiSuggestionStatus::Sugerida) {
            return;
        }

        $minuta = trim((string) ($suggestion->output['justificativa'] ?? ''));

        if ($minuta === '') {
            return;
        }

        $ficha = $this->processo()->currentAnalysisRecord;

        if ($ficha === null || $ficha->isFinalizada()) {
            return;
        }

        $perCnae = collect($ficha->per_cnae ?? [])
            ->map(function (array $item) use ($minuta): array {
                if ((string) ($item['cnae'] ?? '') !== $this->cnae) {
                    return $item;
                }

                // Nunca sobrescreve a manifestação já escrita pelo analista.
                if (trim((string) ($item['justificativa'] ?? '')) !== '') {
                    return $item;
                }

                $item['justificativa'] = $minuta;

                return $item;
            })
            ->all();

        $ficha->forceFill(['per_cnae' => $perCnae])->save();
    }

    /**
     * @return array<int, File>
     */
    protected function attachments(): array
    {
        return [];
    }

    /**
     * Item per_cnae do CNAE na revisão vigente — o enquadramento objetivo do
     * motor, lido (nunca fabricado).
     *
     * @return array<string, mixed>
     */
    private function itemCnae(): array
    {
        if ($this->item !== null) {
            return $this->item;
        }

        $perCnae = $this->processo()->currentAnalysisRecord?->per_cnae ?? [];

        foreach ($perCnae as $item) {
            if (is_array($item) && (string) ($item['cnae'] ?? '') === $this->cnae) {
                return $this->item = $item;
            }
        }

        return $this->item = [];
    }

    private function processo(): ViabilityRequest
    {
        return $this->processo ??= ViabilityRequest::query()
            ->with('currentAnalysisRecord')
            ->findOrFail($this->requestId);
    }
}
