<?php

namespace App\Jobs;

use App\Models\ReginRecebimento;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Support\Audit\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Abre o processo de viabilidade a partir do envelope REGIN já recebido, FORA
 * do request que devolveu o ack.
 *
 * O código 3 do guia é o "recebimento do processo na prefeitura" — confirma
 * que o envelope foi gravado, não que a análise terminou (o parecer volta
 * depois pelo POST /recebe do REGIN). A abertura do processo consulta o
 * Cadastro Imobiliário e percorre as camadas do GeoServer uma a uma; medido no
 * ambiente real, isso levava ~10s presos no ack. Aqui vale o mesmo desenho do
 * protocolo do portal (SolicitacaoProtocolada → AvaliarFluxoExpresso →
 * DecidirFluxoExpressoJob): o request responde rápido e a fila faz o resto,
 * com retry/backoff.
 *
 * Carrega só o id (serialização segura; recarrega o estado no handle). Um job
 * que esgota as tentativas AUDITA a falha (RN-002): recebimento sem processo é
 * pendência visível, nunca silêncio.
 */
class ProtocolarRecebimentoReginJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    /** @var array<int, int> */
    public array $backoff;

    public function __construct(public readonly int $reginRecebimentoId)
    {
        $this->tries = (int) config('sile.integrations.regin.job.tries', 3);
        $this->timeout = (int) config('sile.integrations.regin.job.timeout', 120);
        $this->backoff = config('sile.integrations.regin.job.backoff', [30, 60, 120]);

        $fila = (string) config('sile.integrations.regin.job.fila', 'default');

        if ($fila !== '' && $fila !== 'default') {
            $this->onQueue($fila);
        }
    }

    public function handle(ReginProcessoProtocolador $protocolador): void
    {
        $recebimento = ReginRecebimento::query()->find($this->reginRecebimentoId);

        // Guard idempotente: recebimento removido, ou processo já aberto por
        // outra passada (reprocesso da fila) → no-op.
        if ($recebimento === null || $recebimento->viability_request_id !== null) {
            return;
        }

        $processo = $protocolador->protocolar($recebimento);

        if ($processo !== null) {
            $recebimento->update(['viability_request_id' => $processo->id]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $recebimento = ReginRecebimento::query()->find($this->reginRecebimentoId);

        // Só a classe da exceção nas propriedades: a mensagem pode carregar
        // valores do envelope (dado pessoal). O detalhe fica no log da fila.
        app(AuditService::class)->log(
            'regin',
            'protocolar',
            'Processo recebido via REGIN não pôde ser aberto',
            [
                'protocolo' => $recebimento?->protocolo,
                'excecao' => $exception === null ? null : $exception::class,
            ],
            $recebimento,
            'falha',
        );
    }
}
