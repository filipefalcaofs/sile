<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestOrigin;
use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Services\Solicitacao\VinculosCpfProcesso;
use Illuminate\Console\Command;

/**
 * Vincula aos processos do REGIN já protocolados os CPFs dos sócios (do
 * recebimento mais recente do mesmo protocolo) e o do solicitante declarado,
 * para que vejam o processo no portal e pelo Detalhar do Portal de Serviços.
 */
class ReginVincularCpfsCommand extends Command
{
    protected $signature = 'regin:vincular-cpfs {--dry-run : Mostra as contagens, sem gravar}';

    protected $description = 'Vincula sócios e solicitante declarado aos processos do REGIN';

    public function handle(ReginProcessoProtocolador $protocolador, VinculosCpfProcesso $vinculos): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $processos = 0;
        $comSocios = 0;
        $semRecebimento = 0;

        ViabilityRequest::query()
            ->where('origin', ViabilityRequestOrigin::Regin)
            ->orderBy('id')
            ->each(function (ViabilityRequest $processo) use ($protocolador, $vinculos, $dryRun, &$processos, &$comSocios, &$semRecebimento): void {
                $recebimento = $processo->external_reference === null ? null : ReginRecebimento::query()
                    ->where('protocolo', $processo->external_reference)
                    ->latest('id')
                    ->first();

                $socios = $recebimento === null ? [] : $protocolador->sociosDoRecebimento($recebimento);

                $processos++;
                $comSocios += $socios === [] ? 0 : 1;
                $semRecebimento += $recebimento === null ? 1 : 0;

                if (! $dryRun) {
                    $vinculos->sincronizar($processo, $socios);
                }
            });

        $this->info(($dryRun ? '[dry-run] ' : '')."Processos do REGIN: {$processos}; com sócios: {$comSocios}; sem recebimento guardado: {$semRecebimento}.");

        return self::SUCCESS;
    }
}
