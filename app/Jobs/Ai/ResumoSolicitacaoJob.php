<?php

namespace App\Jobs\Ai;

use App\Ai\Agents\ResumoSolicitacaoAgent;
use App\Enums\AiSuggestionType;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\File;

/**
 * Execução do resumo da solicitação para o cidadão (HU-116), pré-protocolo.
 * Subclasse fina do RunAiAgentJob: carrega só o ID e monta o input de TEXTO
 * (capability 'text', sem anexos) com os dados DECLARADOS da solicitação —
 * empresa (razão social/CNPJ do próprio requerente), atividades (CNAE) e imóvel
 * (endereço/área), sem CPF nem dado sensível desnecessário. A saída RESUME para
 * a conferência do cidadão — nunca decide nem antecipa o desfecho da viabilidade
 * (AI-SPEC Failure Mode #1). Toggle COMPARTILHADO com o resumo do processo
 * (HU-117) sob features.ia_resumo; function()/auditoria seguem por HU.
 * Guardrails/persistência/auditoria vivem na base.
 */
class ResumoSolicitacaoJob extends RunAiAgentJob
{
    private ?ViabilityRequest $solicitacao = null;

    /** @var array<string, mixed>|null */
    private ?array $declarados = null;

    public function __construct(
        public readonly int $requestId,
        public readonly ?int $userId = null,
    ) {
        parent::__construct();
    }

    protected function function(): string
    {
        return 'resumo_solicitacao';
    }

    /**
     * Toggle COMPARTILHADO da síntese (HU-014): o resumo da solicitação no portal
     * (HU-116) e o resumo do processo na ficha (HU-117) vivem ambos sob
     * features.ia_resumo. A function()/event de auditoria seguem específicas por
     * HU ('resumo_solicitacao'); só o portão é compartilhado.
     */
    protected function featureName(): string
    {
        return 'resumo';
    }

    protected function capability(): string
    {
        return 'text';
    }

    protected function suggestionType(): AiSuggestionType
    {
        return AiSuggestionType::ResumoSolicitacao;
    }

    protected function promptVersion(): string
    {
        return ResumoSolicitacaoAgent::PROMPT_VERSION;
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
     * Entrada minimizada (PII): só os dados declarados necessários à conferência.
     *
     * @return array<string, mixed>
     */
    protected function inputRef(): array
    {
        return [
            'viability_request_id' => $this->requestId,
            'declarados' => $this->declarados(),
        ];
    }

    protected function makeAgent(): Agent
    {
        return ResumoSolicitacaoAgent::make();
    }

    protected function promptText(): string
    {
        $declarados = $this->declarados();

        $cnaes = array_map(
            fn (array $cnae): string => trim(
                $cnae['codigo'].($cnae['descricao'] !== null ? ' — '.$cnae['descricao'] : '')
            ),
            $declarados['cnaes'],
        );

        $linhas = [
            'Resuma fielmente, para o cidadão conferir antes de protocolar, os '
                .'dados declarados nesta solicitação de viabilidade. Não afirme o desfecho.',
            '',
            'Empresa: '.($declarados['empresa'] ?? 'não informada'),
            'CNPJ: '.($declarados['cnpj'] ?? 'não informado'),
            'Tipo de serviço: '.($declarados['tipo_servico'] ?? 'não informado'),
            'Endereço declarado: '.($declarados['endereco'] ?? 'não informado'),
            'Área declarada (m²): '.($declarados['area_m2'] ?? 'não informada'),
            'Atividades (CNAE) declaradas: '.($cnaes === [] ? 'não informadas' : implode('; ', $cnaes)),
        ];

        return implode("\n", $linhas);
    }

    /**
     * @return array<int, File>
     */
    protected function attachments(): array
    {
        return [];
    }

    /**
     * Dados declarados minimizados (sem CPF) reutilizados pelo inputRef
     * (idempotência) e pelo promptText (entrada ao provedor). A razão social e o
     * CNPJ são do próprio requerente, mostrados de volta para conferência.
     *
     * @return array{empresa: string|null, cnpj: string|null, tipo_servico: string|null, endereco: string|null, area_m2: string|null, cnaes: list<array{codigo: string, descricao: string|null}>}
     */
    private function declarados(): array
    {
        if ($this->declarados !== null) {
            return $this->declarados;
        }

        $solicitacao = $this->solicitacao();

        $partes = array_filter([
            trim((string) ($solicitacao->address_street ?? '')),
            trim((string) ($solicitacao->address_number ?? '')),
            trim((string) ($solicitacao->address_neighborhood ?? '')),
        ], fn (string $parte): bool => $parte !== '');

        return $this->declarados = [
            'empresa' => $solicitacao->company?->legal_name,
            'cnpj' => $solicitacao->company?->formatted_cnpj,
            'tipo_servico' => $solicitacao->serviceType?->name,
            'endereco' => $partes === [] ? null : implode(', ', $partes),
            'area_m2' => $solicitacao->used_area_m2 === null ? null : (string) $solicitacao->used_area_m2,
            'cnaes' => $solicitacao->cnaes->map(fn (Cnae $cnae): array => [
                'codigo' => $cnae->formatted_code,
                'descricao' => $cnae->description,
            ])->all(),
        ];
    }

    private function solicitacao(): ViabilityRequest
    {
        return $this->solicitacao ??= ViabilityRequest::query()
            ->with(['cnaes', 'company', 'serviceType'])
            ->findOrFail($this->requestId);
    }
}
