<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestOrigin;
use App\Models\IntegrationCall;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Regin\RecebimentoProcesso;
use App\Services\Regin\ReginProcessoProtocolador;
use Illuminate\Console\Command;

/**
 * Refaz o vínculo dos processos REGIN com pessoas: o criador é sempre a conta da
 * integração JUCEB e o requerente é o solicitante que veio no envelope (ou nulo
 * quando o envelope não trouxe solicitante). O envelope vem de
 * origem_declarada; sem ele, do JSON de entrada guardado no log de integração
 * (a chamada mais recente do protocolo que trouxe cpfSolicitante). Só toca
 * processo hoje ligado à conta da integração ou a conta de desenvolvedor/suporte.
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
        $doLog = $this->solicitantesDoLog();

        ViabilityRequest::query()
            ->where('origin', ViabilityRequestOrigin::Regin)
            ->where(fn ($q) => $q->whereIn('requester_user_id', $suspeitos)->orWhereIn('created_by_user_id', $suspeitos))
            ->orderBy('id')
            ->each(function (ViabilityRequest $processo) use ($protocolador, $integracao, $dryRun, $doLog, &$contagem): void {
                $declarada = is_array($processo->origem_declarada) ? RecebimentoProcesso::fromStored($processo->origem_declarada) : null;

                if ($declarada?->cpfSolicitante === null && isset($doLog[(string) $processo->external_reference])) {
                    $declarada = $doLog[(string) $processo->external_reference];
                }

                $requerente = $declarada !== null
                    ? $protocolador->requerenteDaDeclaracao($declarada, ! $dryRun)
                    : null;

                $contagem[$requerente !== null && $requerente->id !== $integracao->id ? 'com_solicitante' : 'sem_solicitante']++;

                if ($dryRun) {
                    return;
                }

                $processo->forceFill([
                    'requester_user_id' => $requerente?->id,
                    'created_by_user_id' => $integracao->id,
                ])->save();
            });

        $this->info("Com solicitante da JUCEB: {$contagem['com_solicitante']}");
        $this->info("Sem solicitante no envelope (requerente nulo): {$contagem['sem_solicitante']}");

        return self::SUCCESS;
    }

    /**
     * Solicitante por protocolo, lido do JSON que a JUCEB enviou (a última
     * chamada de entrada do protocolo que trouxe os dados do solicitante).
     *
     * @return array<string, RecebimentoProcesso>
     */
    private function solicitantesDoLog(): array
    {
        $mapa = [];

        IntegrationCall::query()
            ->where('direction', 'entrada')
            ->where('integration', 'regin')
            ->where('method', 'POST')
            ->where('request_body', 'like', '%cpfSolicitante%')
            ->orderBy('id')
            ->select(['id', 'request_body'])
            ->cursor()
            ->each(function (IntegrationCall $chamada) use (&$mapa): void {
                $envelope = json_decode((string) $chamada->request_body, true);

                if (! is_array($envelope) || blank($envelope['protocolo'] ?? null)) {
                    return;
                }

                $dados = RecebimentoProcesso::fromEnvelope([
                    'protocolo' => $envelope['protocolo'],
                    'cpfSolicitante' => $envelope['cpfSolicitante'] ?? null,
                    'nomeSolicitante' => $envelope['nomeSolicitante'] ?? null,
                    'emailSolicitante' => $envelope['emailSolicitante'] ?? null,
                ]);

                if ($dados->cpfSolicitante !== null) {
                    $mapa[$dados->protocolo] = $dados;
                }
            });

        return $mapa;
    }
}
