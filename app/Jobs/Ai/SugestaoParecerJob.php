<?php

namespace App\Jobs\Ai;

use App\Ai\Agents\SugestaoParecerAgent;
use App\Enums\AiSuggestionType;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\File;

/**
 * Execução da sugestão de minuta de parecer (HU-118) — Failure Mode #1. Subclasse
 * fina do RunAiAgentJob: carrega só o ID do processo e monta o input de TEXTO
 * (capability 'text', sem anexos) com a FUNDAMENTAÇÃO REAL do motor (resultado
 * consolidado, enquadramento por CNAE e versões dos quadros da LOUOS aplicados),
 * minimizando PII (endereço/área declarados, sem CPF/nome/razão social). A saída
 * é SEMPRE uma minuta-sugestão revisável — o job apenas cria a AiSuggestion;
 * NUNCA grava parecer na ficha nem decisão no processo (não-decisão). Guardrails
 * (fonte + confiança), persistência e auditoria RN-002 vivem na base.
 */
class SugestaoParecerJob extends RunAiAgentJob
{
    private ?ViabilityRequest $processo = null;

    /** @var array<string, mixed>|null */
    private ?array $fundamentacao = null;

    public function __construct(
        public readonly int $requestId,
        public readonly ?int $userId = null,
    ) {
        parent::__construct();
    }

    protected function function(): string
    {
        return 'parecer';
    }

    protected function capability(): string
    {
        return 'text';
    }

    protected function suggestionType(): AiSuggestionType
    {
        return AiSuggestionType::Parecer;
    }

    protected function promptVersion(): string
    {
        return SugestaoParecerAgent::PROMPT_VERSION;
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
     * Entrada minimizada (PII): só a fundamentação do motor necessária à minuta.
     *
     * @return array<string, mixed>
     */
    protected function inputRef(): array
    {
        return [
            'viability_request_id' => $this->requestId,
            'fundamentacao' => $this->fundamentacao(),
        ];
    }

    protected function makeAgent(): Agent
    {
        return SugestaoParecerAgent::make();
    }

    protected function promptText(): string
    {
        $fundamentacao = $this->fundamentacao();

        $cnaes = array_map(
            fn (array $cnae): string => trim(
                $cnae['codigo']
                .($cnae['grupo_uso'] !== null ? ' — '.$cnae['grupo_uso'] : '')
                .($cnae['status_sugerido'] !== null ? ' (motor: '.$cnae['status_sugerido'].')' : '')
                .($cnae['fundamentos'] !== [] ? ' [fundamentos do motor: '.implode(', ', $cnae['fundamentos']).']' : '')
            ),
            $fundamentacao['cnaes'],
        );

        $linhas = [
            'Redija uma MINUTA de parecer técnico, de apoio ao analista, fundamentada '
                .'EXCLUSIVAMENTE na pré-análise do motor abaixo. Não decida o desfecho '
                .'nem invente artigo, quadro ou decreto. Apresente como sugestão a revisar.',
            '',
            'Endereço declarado: '.($fundamentacao['endereco'] ?? 'não informado'),
            'Área declarada (m²): '.($fundamentacao['area_m2'] ?? 'não informada'),
            'Resultado consolidado do motor: '.($fundamentacao['consolidado'] ?? 'não informado'),
            'Atividades (CNAE) e enquadramento do motor: '.($cnaes === [] ? 'não informadas' : implode('; ', $cnaes)),
            'Versões das regras aplicadas pelo motor (LOUOS/risco/território): '
                .($fundamentacao['rules'] ?? 'não informadas'),
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
     * Fundamentação REAL do motor (sem PII desnecessária) reutilizada pelo inputRef
     * (idempotência) e pelo promptText (entrada ao provedor). A camada de serviço
     * já garante que só roda quando há engine_snapshot — aqui o material é lido,
     * nunca fabricado.
     *
     * @return array{endereco: string|null, area_m2: string|null, consolidado: string|null, rules: string|null, cnaes: list<array{codigo: string, grupo_uso: string|null, status_sugerido: string|null, fundamentos: list<string>}>}
     */
    private function fundamentacao(): array
    {
        if ($this->fundamentacao !== null) {
            return $this->fundamentacao;
        }

        $processo = $this->processo();
        $ficha = $processo->currentAnalysisRecord;

        $partes = array_filter([
            trim((string) ($processo->address_street ?? '')),
            trim((string) ($processo->address_number ?? '')),
            trim((string) ($processo->address_neighborhood ?? '')),
        ], fn (string $parte): bool => $parte !== '');

        $consolidado = $ficha?->engine_snapshot['consolidado'] ?? null;

        return $this->fundamentacao = [
            'endereco' => $partes === [] ? null : implode(', ', $partes),
            'area_m2' => $processo->used_area_m2 === null ? null : (string) $processo->used_area_m2,
            'consolidado' => is_string($consolidado) ? $consolidado : null,
            'rules' => $this->rules($ficha),
            'cnaes' => $this->cnaes($processo, $ficha),
        ];
    }

    /**
     * Versões dos quadros/regras efetivamente aplicados pelo motor (proveniência
     * para a fundamentação), achatadas em texto legível — nunca inventadas.
     */
    private function rules(?AnalysisRecord $ficha): ?string
    {
        $versions = $ficha?->engine_rules_versions;

        if (! is_array($versions) || $versions === []) {
            return null;
        }

        $itens = [];

        foreach ($versions as $dominio => $valor) {
            if (is_array($valor)) {
                foreach ($valor as $quadro => $versao) {
                    $itens[] = trim((string) $dominio).'.'.trim((string) $quadro).' '.trim((string) $versao);
                }

                continue;
            }

            $itens[] = trim((string) $dominio).' '.trim((string) $valor);
        }

        return $itens === [] ? null : implode('; ', $itens);
    }

    /**
     * Enquadramento por CNAE do motor (per_cnae da ficha): código, grupo de uso,
     * status sugerido e os fundamentos legais que o próprio motor registrou. Cai
     * para os CNAEs declarados quando a ficha não traz per_cnae.
     *
     * @return list<array{codigo: string, grupo_uso: string|null, status_sugerido: string|null, fundamentos: list<string>}>
     */
    private function cnaes(ViabilityRequest $processo, ?AnalysisRecord $ficha): array
    {
        $perCnae = $ficha?->per_cnae ?? [];

        if (is_array($perCnae) && $perCnae !== []) {
            return array_values(array_map(fn (array $item): array => [
                'codigo' => (string) ($item['cnae_formatado'] ?? $item['cnae'] ?? ''),
                'grupo_uso' => isset($item['grupo_uso']) ? (string) $item['grupo_uso'] : null,
                'status_sugerido' => isset($item['status_sugerido']) ? (string) $item['status_sugerido'] : null,
                'fundamentos' => $this->fundamentos($item['fundamentacao'] ?? null),
            ], array_filter($perCnae, 'is_array')));
        }

        return $processo->cnaes->map(fn (Cnae $cnae): array => [
            'codigo' => $cnae->formatted_code,
            'grupo_uso' => $cnae->description,
            'status_sugerido' => null,
            'fundamentos' => [],
        ])->all();
    }

    /**
     * @return list<string>
     */
    private function fundamentos(mixed $fundamentacao): array
    {
        if (! is_array($fundamentacao)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($ref): string => trim((string) $ref),
            $fundamentacao,
        ), fn (string $ref): bool => $ref !== ''));
    }

    private function processo(): ViabilityRequest
    {
        return $this->processo ??= ViabilityRequest::query()
            ->with(['cnaes', 'currentAnalysisRecord'])
            ->findOrFail($this->requestId);
    }
}
