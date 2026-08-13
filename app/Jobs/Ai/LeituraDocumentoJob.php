<?php

namespace App\Jobs\Ai;

use App\Ai\Agents\LeituraDocumentoAgent;
use App\Enums\AiSuggestionType;
use App\Models\ViabilityRequestDocument;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;

/**
 * Execução da leitura por visão (HU-112 OCR + HU-114 ilegibilidade) de um
 * documento da solicitação. Subclasse fina do RunAiAgentJob: carrega só IDs e
 * monta o anexo (imagem ou PDF) a partir do Storage da época. A ilegibilidade
 * força a revisão humana (shouldEscalate) — a leitura nunca vira verdade.
 */
class LeituraDocumentoJob extends RunAiAgentJob
{
    private ?ViabilityRequestDocument $documento = null;

    public function __construct(
        public readonly int $documentId,
        public readonly ?int $requestId = null,
        public readonly ?int $userId = null,
    ) {
        parent::__construct();
    }

    protected function function(): string
    {
        return 'ocr';
    }

    protected function capability(): string
    {
        return 'vision';
    }

    protected function suggestionType(): AiSuggestionType
    {
        return AiSuggestionType::Ocr;
    }

    protected function promptVersion(): string
    {
        return LeituraDocumentoAgent::PROMPT_VERSION;
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
     * @return array<string, mixed>
     */
    protected function inputRef(): array
    {
        $documento = $this->documento();

        return [
            'document_id' => $documento->id,
            'disk' => $documento->disk,
            'path' => $documento->path,
        ];
    }

    protected function makeAgent(): Agent
    {
        return LeituraDocumentoAgent::make();
    }

    protected function promptText(): string
    {
        return 'Transcreva fielmente o texto do documento anexado e avalie sua legibilidade. '
            .'Não invente dados ausentes; marque como ilegível o que não puder ser lido com segurança.';
    }

    /**
     * @return array<int, File>
     */
    protected function attachments(): array
    {
        $documento = $this->documento();

        $anexo = str_starts_with((string) $documento->mime_type, 'image/')
            ? Image::fromStorage($documento->path, $documento->disk)
            : Document::fromStorage($documento->path, $documento->disk);

        return [$anexo];
    }

    /**
     * HU-114: documento marcado como ilegível força a revisão humana mesmo com
     * confiança alta — a leitura nunca é aceita às cegas como verdade.
     *
     * @param  array<string, mixed>  $output
     */
    protected function shouldEscalate(array $output): bool
    {
        return ($output['legivel'] ?? true) === false;
    }

    private function documento(): ViabilityRequestDocument
    {
        return $this->documento ??= ViabilityRequestDocument::query()->findOrFail($this->documentId);
    }
}
