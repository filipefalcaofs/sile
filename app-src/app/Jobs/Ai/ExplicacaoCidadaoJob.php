<?php

namespace App\Jobs\Ai;

use App\Ai\Agents\ExplicacaoCidadaoAgent;
use App\Enums\AiSuggestionType;
use App\Enums\ResultadoViabilidade;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\File;

/**
 * Execução da explicação ao cidadão (HU-119). Subclasse fina do RunAiAgentJob:
 * carrega só o ID e monta o input de TEXTO (capability 'text', sem anexos) a
 * partir da decisão REGISTRADA (ViabilityDecision): desfecho, resultado
 * consolidado, fundamentação legal e veredito por CNAE — a mesma fonte da
 * explicabilidade da HU-099, sem recomputar o motor. A saída TRADUZ a decisão
 * para linguagem cidadã — nunca a contraria, nunca promete além do que a lei
 * garante (AI-SPEC Failure Mode #1). A camada de serviço já garante que só roda
 * quando há decisão; aqui o material é lido, nunca fabricado. Guardrails/
 * persistência/auditoria vivem na base.
 */
class ExplicacaoCidadaoJob extends RunAiAgentJob
{
    private ?ViabilityRequest $processo = null;

    /** @var array<string, mixed>|null */
    private ?array $decisao = null;

    public function __construct(
        public readonly int $requestId,
        public readonly ?int $userId = null,
    ) {
        parent::__construct();
    }

    protected function function(): string
    {
        return 'explicacao';
    }

    protected function capability(): string
    {
        return 'text';
    }

    protected function suggestionType(): AiSuggestionType
    {
        return AiSuggestionType::Explicacao;
    }

    protected function promptVersion(): string
    {
        return ExplicacaoCidadaoAgent::PROMPT_VERSION;
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
     * Entrada minimizada (PII): só o desfecho/fundamentação da decisão registrada.
     *
     * @return array<string, mixed>
     */
    protected function inputRef(): array
    {
        return [
            'viability_request_id' => $this->requestId,
            'decisao' => $this->decisao(),
        ];
    }

    protected function makeAgent(): Agent
    {
        return ExplicacaoCidadaoAgent::make();
    }

    protected function promptText(): string
    {
        $decisao = $this->decisao();

        $cnaes = array_map(
            fn (array $cnae): string => trim(
                $cnae['codigo']
                .($cnae['tendencia'] !== null ? ' — '.$cnae['tendencia'] : '')
                .($cnae['fundamentacao'] !== [] ? ' [fundamentação: '.implode(', ', $cnae['fundamentacao']).']' : '')
            ),
            $decisao['cnaes'],
        );

        $linhas = [
            'Explique ao cidadão, em linguagem clara e acessível, o resultado já '
                .'decidido desta solicitação de viabilidade. Seja fiel à decisão '
                .'abaixo e não prometa o que a lei não garante.',
            '',
            'Desfecho registrado: '.$decisao['desfecho'],
            'Resultado consolidado: '.($decisao['resultado'] ?? 'não informado'),
            'Fundamentação legal registrada: '.($decisao['fundamentacao'] === [] ? 'não informada' : implode('; ', $decisao['fundamentacao'])),
            'Veredito por atividade (CNAE): '.($cnaes === [] ? 'não informado' : implode('; ', $cnaes)),
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
     * Decisão registrada minimizada reutilizada pelo inputRef (idempotência) e
     * pelo promptText (entrada ao provedor). Lê o que a ViabilityDecision gravou
     * (desfecho, resultado consolidado, fundamentação e veredito por CNAE) — a
     * fonte da explicabilidade da HU-099, jamais recomputada nem inventada.
     *
     * @return array{desfecho: string, resultado: string|null, fundamentacao: list<string>, cnaes: list<array{codigo: string, tendencia: string|null, fundamentacao: list<string>}>}
     */
    private function decisao(): array
    {
        if ($this->decisao !== null) {
            return $this->decisao;
        }

        $decisao = $this->processo()->decision;

        return $this->decisao = [
            'desfecho' => $decisao->outcome->label(),
            'resultado' => $this->resultadoLabel($decisao->consolidated_result),
            'fundamentacao' => $this->lista($decisao->fundamentacao),
            'cnaes' => $this->cnaes($decisao),
        ];
    }

    /**
     * Rótulo do veredito consolidado (LOUOS RN-009); mantém o valor cru como
     * fallback caso surja um resultado fora do enum — nunca esconde o dado real.
     */
    private function resultadoLabel(?string $resultado): ?string
    {
        if ($resultado === null) {
            return null;
        }

        return ResultadoViabilidade::tryFrom($resultado)?->label() ?? $resultado;
    }

    /**
     * Veredito por CNAE registrado na decisão (per_cnae): código formatado,
     * tendência rotulada e a fundamentação legal que o motor gravou.
     *
     * @return list<array{codigo: string, tendencia: string|null, fundamentacao: list<string>}>
     */
    private function cnaes(ViabilityDecision $decisao): array
    {
        $perCnae = $decisao->per_cnae;

        if (! is_array($perCnae) || $perCnae === []) {
            return [];
        }

        return array_values(array_map(fn (array $item): array => [
            'codigo' => (string) ($item['cnae_formatado'] ?? $item['cnae'] ?? ''),
            'tendencia' => isset($item['tendencia_label']) ? (string) $item['tendencia_label'] : null,
            'fundamentacao' => $this->lista($item['fundamentacao'] ?? null),
        ], array_filter($perCnae, 'is_array')));
    }

    /**
     * Normaliza uma fundamentação (lista de referências legais) em strings limpas.
     *
     * @return list<string>
     */
    private function lista(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($ref): string => trim((string) $ref),
            $valor,
        ), fn (string $ref): bool => $ref !== ''));
    }

    private function processo(): ViabilityRequest
    {
        return $this->processo ??= ViabilityRequest::query()
            ->with('decision')
            ->findOrFail($this->requestId);
    }
}
