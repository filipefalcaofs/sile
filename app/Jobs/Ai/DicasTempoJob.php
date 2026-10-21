<?php

namespace App\Jobs\Ai;

use App\Ai\Agents\DicasTempoAgent;
use App\Enums\AiSuggestionType;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\File;

/**
 * Execução das dicas de IA para reduzir o tempo de viabilidade. Subclasse fina
 * do RunAiAgentJob: o prompt é o dossiê AGREGADO do período (sem dado pessoal
 * nem protocolo), e a saída é só sugestão ao gestor — não atua em nada.
 */
class DicasTempoJob extends RunAiAgentJob
{
    /**
     * @param  array<string, mixed>  $dossie
     */
    public function __construct(
        public readonly array $dossie,
        public readonly ?int $userId = null,
    ) {
        parent::__construct();
    }

    protected function function(): string
    {
        return 'dicas_tempo';
    }

    protected function capability(): string
    {
        return 'text';
    }

    protected function suggestionType(): AiSuggestionType
    {
        return AiSuggestionType::DicasTempo;
    }

    protected function promptVersion(): string
    {
        return DicasTempoAgent::PROMPT_VERSION;
    }

    protected function viabilityRequestId(): ?int
    {
        return null;
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
        return $this->dossie;
    }

    protected function makeAgent(): Agent
    {
        return DicasTempoAgent::make();
    }

    protected function promptText(): string
    {
        return 'Dê dicas para reduzir o tempo de viabilidade de Salvador com base no dossiê agregado abaixo (JSON). '
            ."Use somente estes números.\n\n"
            .json_encode($this->dossie, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * @return array<int, File>
     */
    protected function attachments(): array
    {
        return [];
    }
}
