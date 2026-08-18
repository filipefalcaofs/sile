<?php

namespace App\Jobs\Ai;

use App\Ai\Agents\ResumoProcessoAgent;
use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Enums\AnalysisPendencyStatus;
use App\Models\AiSuggestion;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\File;

/**
 * Execução do resumo do processo para o analista (HU-117). Subclasse fina do
 * RunAiAgentJob: carrega só IDs e monta o input MINIMIZADO de TEXTO (capability
 * 'text', sem anexos) com a síntese do que já existe no processo — pré-análise
 * do motor (engine_snapshot/per_cnae), inconsistências sinalizadas (Onda 1),
 * pendências abertas e dados locacionais declarados (endereço/área/CNAE, sem
 * CPF/nome/razão social). A saída RESUME para a leitura humana — nunca decide
 * nem antecipa o desfecho (AI-SPEC Failure Mode #1). Guardrails/persistência/
 * auditoria vivem na base.
 */
class ResumoProcessoJob extends RunAiAgentJob
{
    private ?ViabilityRequest $processo = null;

    /** @var array<string, mixed>|null */
    private ?array $sintese = null;

    public function __construct(
        public readonly int $requestId,
        public readonly ?int $userId = null,
    ) {
        parent::__construct();
    }

    protected function function(): string
    {
        return 'resumo_processo';
    }

    /**
     * Toggle COMPARTILHADO da síntese (HU-014): o resumo da solicitação no portal
     * (HU-116) e o resumo do processo na ficha (HU-117) vivem ambos sob
     * features.ia_resumo. A function()/event de auditoria seguem específicas por
     * HU ('resumo_processo'); só o portão é compartilhado.
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
        return AiSuggestionType::ResumoProcesso;
    }

    protected function promptVersion(): string
    {
        return ResumoProcessoAgent::PROMPT_VERSION;
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
     * Entrada minimizada (PII): só a síntese necessária ao resumo.
     *
     * @return array<string, mixed>
     */
    protected function inputRef(): array
    {
        return [
            'viability_request_id' => $this->requestId,
            'sintese' => $this->sintese(),
        ];
    }

    protected function makeAgent(): Agent
    {
        return ResumoProcessoAgent::make();
    }

    protected function promptText(): string
    {
        $sintese = $this->sintese();

        $cnaes = array_map(
            fn (array $cnae): string => trim(
                $cnae['codigo']
                .($cnae['grupo_uso'] !== null ? ' — '.$cnae['grupo_uso'] : '')
                .($cnae['status_sugerido'] !== null ? ' (motor: '.$cnae['status_sugerido'].')' : '')
            ),
            $sintese['cnaes'],
        );

        $linhas = [
            'Resuma fielmente o estado deste processo de licenciamento eletrônico '
                .'a partir dos dados abaixo, para a leitura do analista. Não afirme o desfecho.',
            '',
            'Endereço declarado: '.($sintese['endereco'] ?? 'não informado'),
            'Área declarada (m²): '.($sintese['area_m2'] ?? 'não informada'),
            'Pré-análise do motor: '.($sintese['engine_consolidado'] ?? 'sem pré-análise (modo manual)'),
            'Atividades (CNAE) e enquadramento: '.($cnaes === [] ? 'não informadas' : implode('; ', $cnaes)),
            'Inconsistências sinalizadas pela IA: '.$this->descreverInconsistencias($sintese['inconsistencias']),
            'Pendências abertas: '.$this->descreverPendencias($sintese['pendencias']),
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
     * Síntese minimizada do processo (sem CPF/nome/razão social) reutilizada pelo
     * inputRef (idempotência) e pelo promptText (entrada ao provedor).
     *
     * @return array{endereco: string|null, area_m2: string|null, engine_consolidado: string|null, cnaes: list<array{codigo: string, grupo_uso: string|null, status_sugerido: string|null}>, inconsistencias: list<array{campo: string, severidade: string|null}>, pendencias: list<string>}
     */
    private function sintese(): array
    {
        if ($this->sintese !== null) {
            return $this->sintese;
        }

        $processo = $this->processo();
        $ficha = $processo->currentAnalysisRecord;

        $partes = array_filter([
            trim((string) ($processo->address_street ?? '')),
            trim((string) ($processo->address_number ?? '')),
            trim((string) ($processo->address_neighborhood ?? '')),
        ], fn (string $parte): bool => $parte !== '');

        return $this->sintese = [
            'endereco' => $partes === [] ? null : implode(', ', $partes),
            'area_m2' => $processo->used_area_m2 === null ? null : (string) $processo->used_area_m2,
            'engine_consolidado' => $this->engineConsolidado($ficha),
            'cnaes' => $this->cnaes($processo, $ficha),
            'inconsistencias' => $this->inconsistencias($processo),
            'pendencias' => $this->pendencias($processo),
        ];
    }

    private function engineConsolidado(?AnalysisRecord $ficha): ?string
    {
        $consolidado = $ficha?->engine_snapshot['consolidado'] ?? null;

        return is_string($consolidado) ? $consolidado : null;
    }

    /**
     * Enquadramento por CNAE: prioriza o per_cnae da ficha (sugestão do motor) e
     * cai para os CNAEs declarados quando a ficha não está pré-analisada.
     *
     * @return list<array{codigo: string, grupo_uso: string|null, status_sugerido: string|null}>
     */
    private function cnaes(ViabilityRequest $processo, ?AnalysisRecord $ficha): array
    {
        $perCnae = $ficha?->per_cnae ?? [];

        if (is_array($perCnae) && $perCnae !== []) {
            return array_values(array_map(fn (array $item): array => [
                'codigo' => (string) ($item['cnae_formatado'] ?? $item['cnae'] ?? ''),
                'grupo_uso' => isset($item['grupo_uso']) ? (string) $item['grupo_uso'] : null,
                'status_sugerido' => isset($item['status_sugerido']) ? (string) $item['status_sugerido'] : null,
            ], array_filter($perCnae, 'is_array')));
        }

        return $processo->cnaes->map(fn (Cnae $cnae): array => [
            'codigo' => $cnae->formatted_code,
            'grupo_uso' => $cnae->description,
            'status_sugerido' => null,
        ])->all();
    }

    /**
     * Inconsistências sinalizadas pela IA (Onda 1) — só campo + severidade, para
     * o analista saber o que revisar sem reexpor todo o conteúdo.
     *
     * @return list<array{campo: string, severidade: string|null}>
     */
    private function inconsistencias(ViabilityRequest $processo): array
    {
        return AiSuggestion::query()
            ->where('viability_request_id', $processo->id)
            ->where('type', AiSuggestionType::Inconsistencias)
            ->whereIn('status', [AiSuggestionStatus::Sugerida, AiSuggestionStatus::EscaladaHumano])
            ->get()
            ->flatMap(fn ($sugestao): array => array_map(fn (array $item): array => [
                'campo' => (string) ($item['campo'] ?? ''),
                'severidade' => isset($item['severidade']) ? (string) $item['severidade'] : null,
            ], array_filter((array) ($sugestao->output['inconsistencias'] ?? []), 'is_array')))
            ->values()
            ->all();
    }

    /**
     * Descrições das pendências abertas (texto autoral do analista sobre o
     * processo) para o resumo contextualizar o que está aguardando o requerente.
     *
     * @return list<string>
     */
    private function pendencias(ViabilityRequest $processo): array
    {
        return $processo->pendencies()
            ->where('status', AnalysisPendencyStatus::Aberta)
            ->orderBy('id')
            ->pluck('description')
            ->map(fn (?string $descricao): string => trim((string) $descricao))
            ->filter(fn (string $descricao): bool => $descricao !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{campo: string, severidade: string|null}>  $inconsistencias
     */
    private function descreverInconsistencias(array $inconsistencias): string
    {
        if ($inconsistencias === []) {
            return 'nenhuma';
        }

        return implode('; ', array_map(
            fn (array $item): string => trim($item['campo'].($item['severidade'] !== null ? ' (severidade '.$item['severidade'].')' : '')),
            $inconsistencias,
        ));
    }

    /**
     * @param  list<string>  $pendencias
     */
    private function descreverPendencias(array $pendencias): string
    {
        return $pendencias === [] ? 'nenhuma' : implode('; ', $pendencias);
    }

    private function processo(): ViabilityRequest
    {
        return $this->processo ??= ViabilityRequest::query()
            ->with(['cnaes', 'currentAnalysisRecord'])
            ->findOrFail($this->requestId);
    }
}
