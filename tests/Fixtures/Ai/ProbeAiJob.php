<?php

namespace Tests\Fixtures\Ai;

use App\Enums\AiSuggestionType;
use App\Jobs\Ai\RunAiAgentJob;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\File;

/**
 * Subclasse fina de teste do RunAiAgentJob: amarra a mecânica base ao
 * ProbeStructuredAgent, com função 'probe' e capacidade 'text'. Carrega só a
 * entrada serializável (proveniência/dedup) — nenhum anexo.
 */
class ProbeAiJob extends RunAiAgentJob
{
    /**
     * @param  array<string, mixed>  $entrada
     */
    public function __construct(
        public readonly array $entrada,
        public readonly ?int $requestId = null,
        public readonly ?int $userId = null,
    ) {
        parent::__construct();
    }

    protected function function(): string
    {
        return 'probe';
    }

    protected function capability(): string
    {
        return 'text';
    }

    protected function suggestionType(): AiSuggestionType
    {
        return AiSuggestionType::Ocr;
    }

    protected function promptVersion(): string
    {
        return ProbeStructuredAgent::PROMPT_VERSION;
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
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function inputRef(): array
    {
        return $this->entrada;
    }

    protected function makeAgent(): Agent
    {
        return ProbeStructuredAgent::make();
    }

    protected function promptText(): string
    {
        return 'probe prompt';
    }

    /**
     * @return array<int, File>
     */
    protected function attachments(): array
    {
        return [];
    }
}
