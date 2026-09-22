<?php

namespace App\Jobs\Ai;

use App\Ai\Agents\ClassificacaoDocumentoAgent;
use App\Enums\AiSuggestionType;
use App\Models\ViabilityRequestDocument;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;

/**
 * Execução da classificação documental (HU-113). Subclasse fina do RunAiAgentJob:
 * carrega só IDs, monta o anexo (imagem ou PDF) do Storage e, quando o documento
 * tem uma exigência esperada (DocumentRequirement), leva-a ao prompt para o
 * confronto categoria × exigência — que é sempre um ALERTA na sugestão, nunca um
 * bloqueio do protocolo (RN-005).
 */
class ClassificacaoDocumentoJob extends RunAiAgentJob
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
        return 'classificacao';
    }

    protected function capability(): string
    {
        return 'vision';
    }

    protected function suggestionType(): AiSuggestionType
    {
        return AiSuggestionType::Classificacao;
    }

    protected function promptVersion(): string
    {
        return ClassificacaoDocumentoAgent::PROMPT_VERSION;
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
        return ClassificacaoDocumentoAgent::make();
    }

    protected function promptText(): string
    {
        $texto = 'Classifique o tipo do documento anexado.';

        $exigencia = $this->documento()->requirement?->name;

        if ($exigencia !== null) {
            $texto .= ' Exigência esperada para este anexo: "'.$exigencia.'". '
                .'Indique em compativel_com_exigencia se o documento corresponde a essa exigência (apenas alerta, não rejeite).';
        }

        return $texto;
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

    private function documento(): ViabilityRequestDocument
    {
        return $this->documento ??= ViabilityRequestDocument::query()->findOrFail($this->documentId);
    }
}
