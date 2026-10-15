<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestOrigin;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Regin\RecebimentoProcesso;
use App\Services\Regin\ReginProcessoProtocolador;
use Illuminate\Console\Command;

/**
 * Refaz o vínculo dos processos REGIN com pessoas: o criador é sempre a conta da
 * integração JUCEB e o requerente é o solicitante que veio no envelope (ou a
 * própria integração quando o envelope não trouxe solicitante). Só toca processo
 * hoje ligado à conta da integração ou a conta de desenvolvedor/suporte.
 */
class ReginCorrigirRequerentesCommand extends Command
{
    protected $signature = 'regin:corrigir-requerentes {--dry-run : Mostra as contagens, sem gravar}';

    protected $description = 'Refaz criador e requerente dos processos REGIN a partir do envelope da JUCEB';

    public function handle(ReginProcessoProtocolador $protocolador): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $integracao = $protocolador->integracao();
        $suspeitos = User::query()->get()->filter(fn (User $u): bool => $u->isAtorOcultoNaTramitacao())->pluck('id')->push($integracao->id)->all();

        $contagem = ['com_solicitante' => 0, 'sem_solicitante' => 0];

        ViabilityRequest::query()
            ->where('origin', ViabilityRequestOrigin::Regin)
            ->where(fn ($q) => $q->whereIn('requester_user_id', $suspeitos)->orWhereIn('created_by_user_id', $suspeitos))
            ->orderBy('id')
            ->each(function (ViabilityRequest $processo) use ($protocolador, $integracao, $dryRun, &$contagem): void {
                $declarada = is_array($processo->origem_declarada) ? $processo->origem_declarada : null;
                $requerente = $declarada !== null
                    ? $protocolador->requerenteDaDeclaracao(RecebimentoProcesso::fromStored($declarada), ! $dryRun)
                    : null;

                $contagem[$requerente !== null && $requerente->id !== $integracao->id ? 'com_solicitante' : 'sem_solicitante']++;

                if ($dryRun) {
                    return;
                }

                $processo->forceFill([
                    'requester_user_id' => ($requerente ?? $integracao)->id,
                    'created_by_user_id' => $integracao->id,
                ])->save();
            });

        $this->info("Com solicitante da JUCEB: {$contagem['com_solicitante']}");
        $this->info("Sem solicitante no envelope (requerente = integração): {$contagem['sem_solicitante']}");

        return self::SUCCESS;
    }
}
