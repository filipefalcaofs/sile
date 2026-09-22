<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Expresso\BusinessDeadlineCalculator;
use App\Services\Expresso\IndeferirSemBapService;
use App\Support\Settings;
use Illuminate\Console\Command;

/**
 * HU-134 (DORMENTE): indefere "sem atuação" as solicitações paradas em
 * aguardando_bap cujo prazo de atuação na Junta (expresso.bap.prazo_horas,
 * parametrizável) venceu — calculado pelo seam BusinessDeadlineCalculator.
 *
 * NO-OP honesto em produção: nada entra em aguardando_bap hoje (o BapRegistry
 * está indisponível até o Regin, Fase 13), então a varredura encontra ZERO e o
 * comando sai 0 sem indeferir ninguém — prova de dormência, não de fachada. Liga
 * sozinho quando o Regin alimentar bap_due_at (muda a carga, não a lógica).
 *
 * Reusa o caminho REAL (IndeferirSemBapService → decisão+transição+evento; o
 * indeferimento comunica ao Regin e a SEFAZ ignora, RN-003). Idempotente e
 * reprocessável (RN-004): agendado com withoutOverlapping/onOneServer em
 * routes/console.php; como cada indeferimento muda o status para indeferida, a
 * próxima passada não reprocessa o que já decidiu.
 */
class ExpressoIndeferirSemBapCommand extends Command
{
    protected $signature = 'expresso:indeferir-sem-bap';

    protected $description = 'HU-134 (dormente): indefere as solicitações em aguardando_bap com prazo de atuação na Junta vencido (sem atuação)';

    public function handle(
        BusinessDeadlineCalculator $calculator,
        IndeferirSemBapService $service,
    ): int {
        $prazoHoras = (int) Settings::get('expresso.bap.prazo_horas', config('sile.expresso.bap.prazo_horas', 48));

        $vencidas = ViabilityRequest::query()
            ->where('status', ViabilityRequestStatus::AguardandoBap)
            ->orderBy('id')
            ->get()
            ->filter(function (ViabilityRequest $request) use ($calculator, $prazoHoras): bool {
                // Prefere o vencimento já gravado (bap_due_at); se só houver a
                // vinculação (bap_linked_at), deriva pelo prazo parametrizável.
                $dueAt = $request->bap_due_at
                    ?? ($request->bap_linked_at !== null
                        ? $calculator->dueAt($request->bap_linked_at, $prazoHoras)
                        : null);

                return $dueAt !== null && $calculator->isOverdue($dueAt);
            });

        if ($vencidas->isEmpty()) {
            $this->info('Nenhum processo aguardando BAP vencido.');

            return self::SUCCESS;
        }

        foreach ($vencidas as $request) {
            $service->indeferir($request);
        }

        $this->info("Indeferida(s) {$vencidas->count()} solicitação(ões) aguardando BAP além do prazo (sem atuação).");

        return self::SUCCESS;
    }
}
