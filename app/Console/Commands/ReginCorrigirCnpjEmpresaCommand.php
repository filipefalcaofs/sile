<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestOrigin;
use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginProcessoProtocolador;
use Illuminate\Console\Command;

/**
 * Nas alterações da JUCEB, RUC_GENERAL traz o CPF de quem pediu e o CNPJ da
 * empresa vem em PSC_PROTOCOLO. Os processos gravados antes da correção ficaram
 * ligados a uma "empresa" com esse CPF: este comando os liga à empresa do CNPJ,
 * pelo envelope guardado, e guarda o CPF como solicitante. Também desfaz a
 * ligação de inscrições ao CNPJ de preenchimento 11111111111111.
 */
class ReginCorrigirCnpjEmpresaCommand extends Command
{
    protected $signature = 'regin:corrigir-cnpj-empresa {--dry-run : Mostra as contagens, sem gravar}';

    protected $description = 'Liga as alterações da JUCEB gravadas com o CPF do solicitante à empresa do CNPJ do protocolo';

    public function handle(ReginProcessoProtocolador $protocolador): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $corrigidos = 0;
        $semEnvelope = 0;

        ViabilityRequest::query()
            ->where('origin', ViabilityRequestOrigin::Regin)
            ->whereNotNull('external_reference')
            ->whereHas('company', fn ($q) => $q->whereRaw('length(cnpj) = 11')->orWhere('cnpj', '11111111111111'))
            ->with('company')
            ->lazyById(200)
            ->each(function (ViabilityRequest $processo) use ($protocolador, $dryRun, &$corrigidos, &$semEnvelope): void {
                $recebimento = ReginRecebimento::query()
                    ->where('protocolo', $processo->external_reference)
                    ->latest('id')
                    ->first();

                if ($recebimento === null) {
                    $semEnvelope++;

                    return;
                }

                if ($protocolador->corrigirCnpjDaEmpresa($processo, $recebimento, gravar: ! $dryRun)) {
                    $corrigidos++;
                }
            });

        $this->info(($dryRun ? 'A corrigir: ' : 'Corrigidos: ').$corrigidos);
        $this->info("Sem envelope guardado: {$semEnvelope}");

        return self::SUCCESS;
    }
}
