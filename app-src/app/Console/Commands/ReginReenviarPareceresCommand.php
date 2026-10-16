<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestOrigin;
use App\Events\ResultadoEmitido;
use App\Listeners\ComunicarResultadoRegin;
use App\Models\Activity;
use App\Models\ViabilityRequest;
use Illuminate\Console\Command;

/**
 * Reenvia à JUCEB o parecer dos processos REGIN que já têm decisão e ainda não
 * tiveram o recebimento confirmado. Usa o mesmo listener do envio original
 * (ComunicarResultadoRegin), então a auditoria `integracoes`/`regin-parecer`
 * continua a única fonte: `sucesso` = RECEBIDO_SUCESSO, `bloqueado` = não
 * confirmou. Quem já tem `sucesso` nunca é reenviado.
 *
 * Para quando a JUCEB recusa em sequência (ex.: 403 de autorização): continuar
 * só repetiria a recusa em centenas de processos.
 */
class ReginReenviarPareceresCommand extends Command
{
    protected $signature = 'regin:reenviar-pareceres
        {--protocolo=* : Só estes protocolos (BAP…); sem a opção, todos os pendentes}
        {--exceto=* : Protocolos que não entram nesta passada}
        {--limite= : Máximo de processos nesta passada}
        {--parar-apos=3 : Interrompe após esta quantidade de falhas seguidas}
        {--dry-run : Lista o que seria reenviado, sem enviar}';

    protected $description = 'Reenvia à JUCEB o parecer dos processos REGIN com decisão e sem recebimento confirmado';

    public function handle(ComunicarResultadoRegin $listener): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pararApos = max(1, (int) $this->option('parar-apos'));

        $jaEntregues = Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', 'regin-parecer')
            ->where('result', 'sucesso')
            ->where('subject_type', (new ViabilityRequest)->getMorphClass())
            ->pluck('subject_id')
            ->all();

        $query = ViabilityRequest::query()
            ->where('origin', ViabilityRequestOrigin::Regin)
            ->whereNotNull('external_reference')
            ->whereHas('decision')
            ->whereNotIn('id', $jaEntregues)
            ->with('decision')
            ->orderBy('id');

        $protocolos = array_filter((array) $this->option('protocolo'));

        if ($protocolos !== []) {
            $query->whereIn('external_reference', $protocolos);
        }

        $exceto = array_filter((array) $this->option('exceto'));

        if ($exceto !== []) {
            $query->whereNotIn('external_reference', $exceto);
        }

        if ($this->option('limite') !== null && (int) $this->option('limite') > 0) {
            $query->limit((int) $this->option('limite'));
        }

        $pendentes = $query->get();
        $enviados = 0;
        $falhas = 0;
        $seguidas = 0;

        foreach ($pendentes as $processo) {
            $protocolo = (string) $processo->external_reference;

            if ($dryRun) {
                $this->line("{$protocolo}: seria reenviado ({$processo->decision->outcome->value})");

                continue;
            }

            $listener->handle(new ResultadoEmitido($processo, $processo->decision));

            $resultado = Activity::query()
                ->where('log_name', 'integracoes')
                ->where('event', 'regin-parecer')
                ->where('subject_type', $processo->getMorphClass())
                ->where('subject_id', $processo->getKey())
                ->latest('id')
                ->value('result');

            if ($resultado === 'sucesso') {
                $this->line("{$protocolo}: enviado e confirmado pela JUCEB");
                $enviados++;
                $seguidas = 0;

                continue;
            }

            $this->line("{$protocolo}: JUCEB não confirmou o recebimento");
            $falhas++;
            $seguidas++;

            if ($seguidas >= $pararApos) {
                $this->warn("Interrompido após {$seguidas} falhas seguidas. Resolva o acesso à JUCEB e rode de novo; quem já foi confirmado não é reenviado.");

                break;
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."pendentes {$pendentes->count()}, confirmados {$enviados}, não confirmados {$falhas}");

        return $falhas > 0 && ! $dryRun ? self::FAILURE : self::SUCCESS;
    }
}
