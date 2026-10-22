<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestOrigin;
use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginProcessoProtocolador;
use Illuminate\Console\Command;

/**
 * Separa o solicitante da empresa nos processos REGIN de empresa a constituir: o
 * REGIN manda em RUC_GENERAL o nome e o CPF de quem pediu, e o processo ainda
 * mostra isso como empresa. O envelope vem do recebimento guardado do protocolo.
 */
class ReginCorrigirSolicitantesCommand extends Command
{
    protected $signature = 'regin:corrigir-solicitantes {--dry-run : Mostra as contagens, sem gravar}';

    protected $description = 'Separa solicitante e razão social pretendida nos processos REGIN de empresa a constituir';

    public function handle(ReginProcessoProtocolador $protocolador): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $corrigidos = 0;
        $semEnvelope = 0;

        ViabilityRequest::query()
            ->where('origin', ViabilityRequestOrigin::Regin)
            ->whereNull('solicitante_cpf')
            ->whereNotNull('external_reference')
            ->with('company')
            ->orderBy('id')
            ->each(function (ViabilityRequest $processo) use ($protocolador, $dryRun, &$corrigidos, &$semEnvelope): void {
                if (strlen((string) $processo->company?->cnpj) !== 11) {
                    return;
                }

                $recebimento = ReginRecebimento::query()
                    ->where('protocolo', $processo->external_reference)
                    ->latest('id')
                    ->first();

                if ($recebimento === null) {
                    $semEnvelope++;

                    return;
                }

                if ($protocolador->corrigirEmpresaESolicitante($processo, $recebimento, ! $dryRun)) {
                    $corrigidos++;
                }
            });

        $this->info(($dryRun ? 'A corrigir: ' : 'Corrigidos: ').$corrigidos);
        $this->info("Sem envelope guardado: {$semEnvelope}");

        return self::SUCCESS;
    }
}
