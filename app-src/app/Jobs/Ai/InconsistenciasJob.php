<?php

namespace App\Jobs\Ai;

use App\Ai\Agents\InconsistenciasAgent;
use App\Enums\AiSuggestionType;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestDocument;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;

/**
 * Execução da detecção de inconsistências (HU-115). Subclasse fina do
 * RunAiAgentJob: carrega só IDs, monta o input MINIMIZADO com os dados
 * declarados do processo (endereço/área/CNAE — sem CPF/nome/razão social) e
 * anexa a foto da fachada do Storage quando houver. A saída SINALIZA divergências
 * para o analista (RN-004) e COMPLEMENTA as validações determinísticas, nunca as
 * substitui (RN-005). Persistência/guardrails/auditoria vivem na base.
 */
class InconsistenciasJob extends RunAiAgentJob
{
    private ?ViabilityRequest $processo = null;

    public function __construct(
        public readonly int $requestId,
        public readonly ?int $fachadaDocumentId = null,
        public readonly ?int $userId = null,
    ) {
        parent::__construct();
    }

    protected function function(): string
    {
        return 'inconsistencias';
    }

    protected function capability(): string
    {
        return 'vision';
    }

    protected function suggestionType(): AiSuggestionType
    {
        return AiSuggestionType::Inconsistencias;
    }

    protected function promptVersion(): string
    {
        return InconsistenciasAgent::PROMPT_VERSION;
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
     * Entrada minimizada (PII): só o necessário ao confronto documento × declaração.
     *
     * @return array<string, mixed>
     */
    protected function inputRef(): array
    {
        return [
            'viability_request_id' => $this->requestId,
            'declarado' => $this->dadosDeclarados(),
            'fachada_document_id' => $this->fachadaDocumentId,
        ];
    }

    protected function makeAgent(): Agent
    {
        return InconsistenciasAgent::make();
    }

    protected function promptText(): string
    {
        $declarado = $this->dadosDeclarados();

        $cnaes = array_map(
            fn (array $cnae): string => trim($cnae['codigo'].' '.$cnae['descricao']),
            $declarado['cnaes'],
        );

        $linhas = [
            'Confronte os dados DECLARADOS abaixo com a foto da fachada e os documentos anexados.',
            'Aponte apenas divergências com evidência no material; cite a fonte.',
            '',
            'Endereço declarado: '.($declarado['endereco'] ?? 'não informado'),
            'Área declarada (m²): '.($declarado['area_m2'] ?? 'não informada'),
            'Atividades (CNAE) declaradas: '.($cnaes === [] ? 'não informadas' : implode('; ', $cnaes)),
        ];

        return implode("\n", $linhas);
    }

    /**
     * Anexa a foto da fachada quando houver — capability 'vision'. Sem foto, o
     * confronto ocorre só sobre os dados declarados (sem inventar material).
     *
     * @return array<int, File>
     */
    protected function attachments(): array
    {
        if ($this->fachadaDocumentId === null) {
            return [];
        }

        $documento = ViabilityRequestDocument::query()->find($this->fachadaDocumentId);

        if ($documento === null) {
            return [];
        }

        return [Image::fromStorage($documento->path, $documento->disk)];
    }

    /**
     * Dados declarados minimizados do processo (endereço/área/CNAE). Sem CPF,
     * nome do requerente ou razão social — apenas o necessário ao confronto.
     *
     * @return array{endereco: string|null, area_m2: string|null, cnaes: list<array{codigo: string, descricao: string|null}>}
     */
    private function dadosDeclarados(): array
    {
        $processo = $this->processo();

        $partes = array_filter([
            trim((string) ($processo->address_street ?? '')),
            trim((string) ($processo->address_number ?? '')),
            trim((string) ($processo->address_neighborhood ?? '')),
        ], fn (string $parte): bool => $parte !== '');

        $cnaes = $processo->cnaes->map(fn (Cnae $cnae): array => [
            'codigo' => $cnae->formatted_code,
            'descricao' => $cnae->description,
        ])->all();

        return [
            'endereco' => $partes === [] ? null : implode(', ', $partes),
            'area_m2' => $processo->used_area_m2 === null ? null : (string) $processo->used_area_m2,
            'cnaes' => $cnaes,
        ];
    }

    private function processo(): ViabilityRequest
    {
        return $this->processo ??= ViabilityRequest::query()
            ->with('cnaes')
            ->findOrFail($this->requestId);
    }
}
