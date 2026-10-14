<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestOrigin;
use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;

/**
 * Refaz as respostas da planilha dos processos REGIN a partir do envelope
 * recebido (corpo + xml) e reenfileira a decisão. Serve a qualquer ajuste de
 * casamento entre as perguntas da JUCEB e a planilha, ou à carga corrigida da
 * planilha. Só toca processo que ainda pode voltar ao motor (protocolado, ou em
 * análise sem analista e sem decisão); o resto é listado como ignorado.
 */
class ReginRefazerRespostasCommand extends Command
{
    protected $signature = 'regin:refazer-respostas
        {--protocolo=* : Só estes protocolos (BAP…); sem a opção, todos os processos REGIN}
        {--limite= : Máximo de processos nesta passada}
        {--dry-run : Mostra o que mudaria, sem gravar nem reenfileirar}';

    protected $description = 'Refaz as respostas da planilha dos processos REGIN pelo envelope recebido e reenfileira a decisão';

    public function handle(ReginProcessoProtocolador $protocolador, FluxoExpressoService $expresso, AuditService $audit): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = ViabilityRequest::query()
            ->where('origin', ViabilityRequestOrigin::Regin)
            ->whereNotNull('external_reference')
            ->orderBy('id');

        $protocolos = array_filter((array) $this->option('protocolo'));

        if ($protocolos !== []) {
            $query->whereIn('external_reference', $protocolos);
        }

        if ($this->option('limite') !== null && (int) $this->option('limite') > 0) {
            $query->limit((int) $this->option('limite'));
        }

        $contagem = ['alterados' => 0, 'iguais' => 0, 'ignorados' => 0];

        foreach ($query->get() as $processo) {
            $protocolo = (string) $processo->external_reference;

            if (! $expresso->podeReavaliar($processo)) {
                $this->line("{$protocolo}: ignorado (já assumido, decidido ou fora do motor)");
                $contagem['ignorados']++;

                continue;
            }

            $recebimento = ReginRecebimento::query()->where('protocolo', $protocolo)->latest('id')->first();
            $novo = $recebimento === null ? null : $protocolador->snapshotDoRecebimento($recebimento);

            if ($novo === null) {
                $this->line("{$protocolo}: ignorado (sem envelope ou sem perguntas)");
                $contagem['ignorados']++;

                continue;
            }

            $atual = is_array($processo->simulation_snapshot) ? $processo->simulation_snapshot : [];
            $mudou = ($atual['respostas_tratamento_por_cnae'] ?? []) != $novo['respostas_tratamento_por_cnae'];

            $this->line($protocolo.': '.($mudou ? 'respostas alteradas '.json_encode($novo['respostas_tratamento_por_cnae']) : 'respostas iguais; decisão reenfileirada'));
            $contagem[$mudou ? 'alterados' : 'iguais']++;

            if ($dryRun) {
                continue;
            }

            if ($mudou) {
                $processo->forceFill(['simulation_snapshot' => array_merge($atual, $novo)])->save();

                $audit->log(
                    logName: 'regin',
                    event: 'respostas-refeitas',
                    description: "Respostas da planilha refeitas pelo envelope do protocolo {$protocolo}",
                    properties: ['anterior' => $atual['respostas_tratamento_por_cnae'] ?? [], 'novo' => $novo['respostas_tratamento_por_cnae']],
                    subject: $processo,
                );
            }

            DecidirFluxoExpressoJob::dispatch($processo->id, reavaliacao: true);
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."alterados {$contagem['alterados']}, iguais {$contagem['iguais']}, ignorados {$contagem['ignorados']}");

        return self::SUCCESS;
    }
}
