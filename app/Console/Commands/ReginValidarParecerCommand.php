<?php

namespace App\Console\Commands;

use App\Models\ViabilityRequest;
use App\Services\Regin\HttpReginParecerNotifier;
use App\Services\Regin\ReginHttpClient;
use App\Services\Regin\ReginUnavailableException;
use Illuminate\Console\Command;

/**
 * Submete o parecer de um processo REGIN ao validador da JUCEB
 * (/teste/validaResposta) e mostra o que ela responde. Não envia o parecer e
 * não altera nada: serve para conferir o envelope em produção.
 */
class ReginValidarParecerCommand extends Command
{
    protected $signature = 'regin:validar-parecer
        {protocolo : Protocolo REGIN (BAP…) de um processo com decisão}
        {--mostrar : Imprime o envelope enviado ao validador}';

    protected $description = 'Valida o envelope do parecer de um processo no /teste/validaResposta da JUCEB, sem enviar';

    public function handle(HttpReginParecerNotifier $notifier, ReginHttpClient $client): int
    {
        $processo = ViabilityRequest::query()
            ->with('decision')
            ->where('external_reference', $this->argument('protocolo'))
            ->first();

        if ($processo === null || $processo->decision === null) {
            $this->error('Processo não encontrado ou sem decisão.');

            return self::FAILURE;
        }

        $envelope = $notifier->envelope($processo, $processo->decision);

        if ($this->option('mostrar')) {
            $this->line(json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        try {
            $resultado = $client->validarResposta($envelope);
        } catch (ReginUnavailableException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("HTTP {$resultado['status']}: ".($resultado['corpo'] === '' ? '(sem corpo)' : $resultado['corpo']));

        if ($resultado['status'] >= 200 && $resultado['status'] < 300) {
            $this->info('Envelope aceito pelo validador da JUCEB.');

            return self::SUCCESS;
        }

        $this->warn('Envelope rejeitado pelo validador; ajuste os campos apontados acima.');

        return self::FAILURE;
    }
}
