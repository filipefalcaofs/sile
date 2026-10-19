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
 * (a chamada mais recente do protocolo que trouxe cpfSolicitante). O requerente
 * é um registro de balcão em cidadaos (Cidadao::criarRegistroDeBalcao). Só toca
 * processo sem requerente (a separação das contas anulou o servidor que figurava
 * como requerente do REGIN antigo) ou criado pela conta da integração ou por conta
 * de sistema (integração, e-mail .invalid, desenvolvedor/suporte). Sem solicitante
 * recuperável, mantém o requerente atual.
 */
class ReginCorrigirRequerentesCommand extends Command
{
    protected $signature = 'regin:corrigir-requerentes {--dry-run : Mostra as contagens, sem gravar}';

    protected $description = 'Refaz criador e requerente dos processos REGIN a partir do envelope da JUCEB';

    public function handle(ReginProcessoProtocolador $protocolador): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $integracao = $protocolador->integracao();
        $suspeitos = User::query()->whereNotIn('id', User::query()->semContasDeSistema()->select('id'))->pluck('id')->push($integracao->id)->unique()->all();

        $contagem = ['com_solicitante' => 0, 'sem_solicitante' => 0];
        $doLog = $this->solicitantesDoLog();

        ViabilityRequest::query()
            ->where('origin', ViabilityRequestOrigin::Regin)
            ->where(fn ($q) => $q
                ->whereNull('requester_cidadao_id')
                ->orWhere(fn ($criador) => $criador
                    ->where('created_by_type', (new User)->getMorphClass())
                    ->whereIn('created_by_id', $suspeitos)))
            ->orderBy('id')
            ->each(function (ViabilityRequest $processo) use ($protocolador, $integracao, $dryRun, $doLog, &$contagem): void {
                $declarada = is_array($processo->origem_declarada) ? RecebimentoProcesso::fromStored($processo->origem_declarada) : null;

                if ($declarada?->cpfSolicitante === null && isset($doLog[(string) $processo->external_reference])) {
                    $declarada = $doLog[(string) $processo->external_reference];
                }

                $requerente = $declarada !== null
                    ? $protocolador->requerenteDaDeclaracao($declarada, ! $dryRun)
                    : null;

                $contagem[$requerente !== null ? 'com_solicitante' : 'sem_solicitante']++;

                if ($dryRun) {
                    return;
                }

                $processo->forceFill([
                    'requester_cidadao_id' => $requerente?->id ?? $processo->requester_cidadao_id,
                    'created_by_type' => $integracao->getMorphClass(),
                    'created_by_id' => $integracao->id,
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
