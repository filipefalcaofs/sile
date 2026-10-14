<?php

namespace App\Jobs;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\PoligonoEnderecamentoService;
use App\Services\Realty\LocalizacaoInscricao;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
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

        // A fila é fonte única de verdade DO JOB — tanto o gatilho (listener)
        // quanto a rede de segurança (expresso:reavaliar) enfileiram no mesmo
        // lugar. 'default' não sobrescreve a conexão.
        $fila = (string) config('sile.expresso.fila', 'default');

        if ($fila !== '' && $fila !== 'default') {
            $this->onQueue($fila);
        }
    }

    public function handle(FluxoExpressoService $service, ?ProtocolarSolicitacaoService $protocolo = null, ?PoligonoEnderecamentoService $poligono = null): void
    {
        $request = ViabilityRequest::query()->find($this->viabilityRequestId);

        // Guard idempotente: a solicitação foi removida ou já saiu de protocolada
        // (decidida/encaminhada por outra passada) → no-op. O serviço também
        // re-checa o status sob o lock, então o reprocesso é sempre seguro.
        if ($request === null || $request->status !== ViabilityRequestStatus::Protocolada) {
            return;
        }

        if ($this->aguardarCadastroImobiliario($request, $protocolo ?? app(ProtocolarSolicitacaoService::class))) {
            return;
        }

        // O polígono do lote vem ANTES da decisão: é ele que zoneia pela maior
        // porção. Só aqui (fila, 120 s) a consulta espacial lenta é aceitável; o
        // protocolo síncrono não a pede. Falha não impede a decisão.
        ($poligono ?? app(PoligonoEnderecamentoService::class))->aplicarPorPonto($request);

        $service->decide($request);
    }

    /**
     * Sem ponto do imóvel e com inscrição, repete a localização antes de
     * decidir: o Cadastro Imobiliário fora do ar é falha transitória, e o
     * encaminhamento à análise não se desfaz depois. Enquanto houver tentativas
     * o job volta para a fila; na última, decide com a degradação honesta
     * (veredito pendente → análise técnica). Inscrição não encontrada ou sem
     * coordenada é definitiva e não adia.
     */
    private function aguardarCadastroImobiliario(ViabilityRequest $request, ProtocolarSolicitacaoService $protocolo): bool
    {
        if ($request->pontoDoImovel() !== null || blank($request->property_registration)) {
            return false;
        }

        $status = $protocolo->completarTerritorio($request);

        if ($status !== LocalizacaoInscricao::INDISPONIVEL || $this->attempts() >= $this->tries) {
            return false;
        }

        $this->release($this->backoff[$this->attempts() - 1] ?? (end($this->backoff) ?: 30));

        return true;
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
