<?php

namespace App\Jobs;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Support\Audit\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Executa a decisão do fluxo expresso FORA do request do protocolo (HU-076).
 * Envelopa o FluxoExpressoService::decide (motor real, 09-05) num job de fila
 * com retry/timeout/backoff — a lógica de domínio, a idempotência (Cache::lock +
 * re-check) e a auditoria síncrona já vivem no serviço; aqui adicionamos
 * resiliência (FA-03) e visibilidade de falha.
 *
 * Despachado pelo listener AUTO-DESCOBERTO AvaliarFluxoExpresso (1 por protocolo)
 * e reprocessável pela rede de segurança expresso:reavaliar — seguro porque o
 * serviço é idempotente. Carrega só o id (serialização segura; recarrega o
 * estado fresco no handle). Um job que esgota as tentativas vai para failed_jobs
 * e AUDITA a falha (RN-002), nunca silenciosa — sem decisão falsa.
 */
class DecidirFluxoExpressoJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    /** @var array<int, int> */
    public array $backoff;

    public function __construct(public readonly int $viabilityRequestId)
    {
        // Resiliência parametrizada por constante técnica (config/sile.php,
        // precedente [02-02]) — fila/tries/timeout/backoff ajustáveis sem deploy.
        $this->tries = (int) config('sile.expresso.job.tries', 3);
        $this->timeout = (int) config('sile.expresso.job.timeout', 120);
        $this->backoff = config('sile.expresso.job.backoff', [30, 60, 120]);
    }

    public function handle(FluxoExpressoService $service): void
    {
        $request = ViabilityRequest::query()->find($this->viabilityRequestId);

        // Guard idempotente: a solicitação foi removida ou já saiu de protocolada
        // (decidida/encaminhada por outra passada) → no-op. O serviço também
        // re-checa o status sob o lock, então o reprocesso é sempre seguro.
        if ($request === null || $request->status !== ViabilityRequestStatus::Protocolada) {
            return;
        }

        $service->decide($request);
    }

    public function failed(?Throwable $exception): void
    {
        app(AuditService::class)->log(
            logName: 'expresso',
            event: 'decisao-falha',
            description: "Falha no processamento assíncrono da decisão expressa da solicitação #{$this->viabilityRequestId}",
            properties: [
                'viability_request_id' => $this->viabilityRequestId,
                'erro' => $exception?->getMessage(),
            ],
            result: 'falha',
            rulesVersion: 'fluxo-expresso-v1',
        );
    }
}
