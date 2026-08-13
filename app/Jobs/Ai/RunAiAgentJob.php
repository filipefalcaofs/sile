<?php

namespace App\Jobs\Ai;

use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Models\AiConfiguration;
use App\Models\AiSuggestion;
use App\Services\Ai\AiCallAuditor;
use App\Services\Ai\AiCostEstimator;
use App\Services\Ai\AiFeatureGate;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\File;
use Throwable;

/**
 * Job base de EXECUÇÃO de uma função de IA (Fase 14). Mantém os Agents "burros"
 * (só prompt + schema + roteamento): toda a mecânica anti-fachada vive aqui.
 *
 * Garantias (AI-SPEC §4b/§6):
 *  - re-check do toggle no handle (a config pode mudar entre enfileirar e rodar)
 *    ⇒ OFF vira no-op AUDITADO ('desativado'), sem chamar o provedor;
 *  - idempotência por input_hash (entrada + versão do prompt + função) ⇒ não
 *    reprocessa a mesma entrada;
 *  - guardrails: fonte obrigatória e limiar de confiança ⇒ abaixo do limiar ou
 *    sem fonte a sugestão NASCE 'escalada_humano' (nunca aceita às cegas);
 *  - persistência da AiSuggestion SÓ após sucesso validado (nunca decisão);
 *  - auditoria RN-002 de toda chamada (sucesso/desativado/falha) sem api_key/PII;
 *  - falha ⇒ failed() audita 'falha' e NENHUMA sugestão é criada (sem fachada).
 */
abstract class RunAiAgentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Ordem dos níveis de confiança para comparar com o limiar do guardrail. */
    private const CONFIDENCE_ORDER = ['baixa' => 0, 'media' => 1, 'alta' => 2];

    public int $tries;

    public int $timeout;

    public function __construct()
    {
        $this->tries = (int) config('sile.ai.job.tries', 3);
        $this->timeout = (int) config('sile.ai.job.timeout', 120);
        $this->onQueue((string) config('sile.ai.job.fila', 'default'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return (array) config('sile.ai.job.backoff', [30, 60, 120]);
    }

    public function handle(AiFeatureGate $gate, AiCallAuditor $auditor, AiCostEstimator $costs): void
    {
        $function = $this->function();
        $promptVersion = $this->promptVersion();

        // 1. Re-check do portão: desligado após o enfileiramento ⇒ no-op auditado.
        //    O portão usa featureName() (o TOGGLE), não function() — uma família
        //    de funções pode compartilhar um toggle (ex.: ia_resumo p/ 116 e 117)
        //    mantendo function()/auditoria distintas por HU.
        if (! $gate->available($this->featureName(), $this->capability())) {
            $auditor->record($function, $promptVersion, [], result: 'desativado', personalData: $this->personalData());

            return;
        }

        // 2. Idempotência: a mesma entrada + versão do prompt não reprocessa.
        $inputRef = $this->inputRef();
        $inputHash = $this->inputHash($inputRef, $promptVersion);

        if (AiSuggestion::query()->where('input_hash', $inputHash)->exists()) {
            return;
        }

        // 3. Proveniência (provider/model) da configuração ATIVA da capacidade —
        //    da config real, não da resposta (o fake de teste não traz meta).
        $configuration = $this->activeConfiguration();
        $provider = $configuration?->provider;
        $model = $configuration?->model;

        // 4. Chamada síncrona ao agente. Exceção ⇒ failed() audita; nada é criado.
        $response = $this->makeAgent()->prompt($this->promptText(), $this->attachments());

        /** @var array<string, mixed> $output */
        $output = $response->toArray();

        // 5. Tokens (o SDK não entrega custo) ⇒ custo estimado ou null (nunca inventado).
        $promptTokens = (int) ($response->usage->promptTokens ?? 0);
        $completionTokens = (int) ($response->usage->completionTokens ?? 0);
        $cost = $costs->estimate($model, $promptTokens, $completionTokens);

        // 6. Guardrails (AI-SPEC §6): fonte obrigatória + limiar de confiança +
        //    escalonamento específico da função (ex.: ilegibilidade na HU-114).
        $confidence = is_string($output['confianca'] ?? null) ? $output['confianca'] : null;
        $status = $this->shouldEscalate($output)
            ? AiSuggestionStatus::EscaladaHumano
            : $this->resolveStatus($confidence, $output['fonte'] ?? null);

        // 7. Persiste a sugestão (apenas no sucesso validado) — sempre revisável.
        $suggestion = AiSuggestion::query()->create([
            'type' => $this->suggestionType(),
            'viability_request_id' => $this->viabilityRequestId(),
            'input_ref' => $inputRef,
            'input_hash' => $inputHash,
            'output' => $output,
            'provider' => $provider,
            'model' => $model,
            'prompt_version' => $promptVersion,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cost_estimated' => $cost,
            'confidence' => $confidence,
            'status' => $status,
            'created_by_user_id' => $this->createdByUserId(),
        ]);

        // 8. Auditoria RN-002 — só metadados seguros (sem api_key, sem PII da saída).
        $auditor->record(
            $function,
            $promptVersion,
            [
                'provider' => $provider,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'cost_estimated' => $cost,
                'ai_suggestion_id' => $suggestion->id,
                'status' => $status->value,
            ],
            subject: $suggestion,
            result: 'sucesso',
            personalData: $this->personalData(),
        );
    }

    public function failed(Throwable $exception): void
    {
        app(AiCallAuditor::class)->record(
            $this->function(),
            $this->promptVersion(),
            ['erro' => class_basename($exception)],
            result: 'falha',
            personalData: $this->personalData(),
        );
    }

    /**
     * Hook de escalonamento específico da função: além de fonte/confiança, a
     * subclasse pode forçar a revisão humana a partir da própria saída
     * estruturada (ex.: HU-114 — documento ilegível). Padrão: nada a acrescentar.
     *
     * @param  array<string, mixed>  $output
     */
    protected function shouldEscalate(array $output): bool
    {
        return false;
    }

    /**
     * Nome do TOGGLE da função no portão (HU-014). Por padrão coincide com a
     * function(); uma subclasse pode sobrescrever para COMPARTILHAR um toggle
     * entre funções afins sem perder a identidade de auditoria/dedup — ex.: o
     * resumo da solicitação (HU-116) e o do processo (HU-117) vivem ambos sob
     * features.ia_resumo, enquanto function()/event de auditoria seguem por HU.
     */
    protected function featureName(): string
    {
        return $this->function();
    }

    /**
     * Sem fonte rastreável ou com confiança abaixo do limiar parametrizado, a
     * sugestão é escalada para o humano (nunca aceita silenciosamente).
     */
    private function resolveStatus(?string $confidence, mixed $fonte): AiSuggestionStatus
    {
        if (! is_string($fonte) || trim($fonte) === '') {
            return AiSuggestionStatus::EscaladaHumano;
        }

        $limiar = (string) Settings::get('ai.limiar_confianca', 'media');

        if (
            $confidence !== null
            && isset(self::CONFIDENCE_ORDER[$confidence], self::CONFIDENCE_ORDER[$limiar])
            && self::CONFIDENCE_ORDER[$confidence] < self::CONFIDENCE_ORDER[$limiar]
        ) {
            return AiSuggestionStatus::EscaladaHumano;
        }

        return AiSuggestionStatus::Sugerida;
    }

    private function activeConfiguration(): ?AiConfiguration
    {
        return AiConfiguration::query()
            ->where('capability', $this->capability())
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $inputRef
     */
    private function inputHash(array $inputRef, string $promptVersion): string
    {
        return hash(
            'sha256',
            json_encode($inputRef).'|'.$promptVersion.'|'.$this->function().'|'.($this->viabilityRequestId() ?? ''),
        );
    }

    abstract protected function function(): string;

    abstract protected function capability(): string;

    abstract protected function suggestionType(): AiSuggestionType;

    abstract protected function promptVersion(): string;

    abstract protected function viabilityRequestId(): ?int;

    abstract protected function createdByUserId(): ?int;

    abstract protected function personalData(): bool;

    /**
     * @return array<string, mixed>
     */
    abstract protected function inputRef(): array;

    abstract protected function makeAgent(): Agent;

    abstract protected function promptText(): string;

    /**
     * @return array<int, File>
     */
    abstract protected function attachments(): array;
}
