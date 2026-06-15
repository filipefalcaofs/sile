<?php

namespace App\Console\Commands;

use App\Enums\CommunicationType;
use App\Enums\ViabilityRequestStatus;
use App\Models\Communication;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\ProcessoEscalonadoNotification;
use App\Services\Analise\AnalysisSlaService;
use App\Services\Analise\SlaStatus;
use App\Services\Comunicacao\NotificationDispatcher;
use App\Support\Settings;
use DateTimeInterface;
use Illuminate\Console\Command;

/**
 * HU-147: escalonamento por SLA. Varre os processos em_analise e lê o semáforo da
 * FONTE ÚNICA de prazo (analysis_due_at + analysis_stage_started_at) pelo
 * AnalysisSlaService — a MESMA origem da fila/badge da Fase 10, sem recalcular
 * prazo à parte (CA-02, RN-002):
 *
 * - SlaStatus::Amarelo (limiar) → alerta o ANALISTA responsável;
 * - SlaStatus::Vermelho (vencido) → escala ao(s) GESTOR(es) — usuários com a role
 *   parametrizável notificacoes.escalonamento.gestor_role (default 'gestor').
 *
 * O que fazer por faixa vem de notificacoes.escalonamento.tratamento (default só
 * notificar). SÓ notifica — NUNCA decide/transiciona (RN-003); a fonte de prazo é
 * única para não brigar com a HU-129 (sem dupla contagem). Idempotente pelo ledger
 * communications (RN-004). Agendado com withoutOverlapping/onOneServer.
 *
 * Pendência SEDUR: o destinatário "gestor DO SETOR" não existe no schema; o
 * default escala a TODOS os usuários da role gestor (roteamento ao setor depende
 * da SEDUR — não inventado aqui).
 */
class EscalonarSlaCommand extends Command
{
    protected $signature = 'notificacoes:escalonar-sla';

    protected $description = 'HU-147: escala por SLA (amarelo→analista, vencido→gestor) sobre a fonte única analysis_due_at, de forma idempotente';

    public function handle(NotificationDispatcher $dispatcher, AnalysisSlaService $sla): int
    {
        $tratamento = (array) Settings::get(
            'notificacoes.escalonamento.tratamento',
            config('sile.notificacoes.escalonamento.tratamento', []),
        );
        $gestorRole = (string) Settings::get(
            'notificacoes.escalonamento.gestor_role',
            config('sile.notificacoes.escalonamento.gestor_role', 'gestor'),
        );

        $tratamentoAmarelo = (string) ($tratamento['amarelo'] ?? 'notificar_analista');
        $tratamentoVencido = (string) ($tratamento['vencido'] ?? 'notificar_gestor');

        $processos = ViabilityRequest::query()
            ->where('status', ViabilityRequestStatus::EmAnalise)
            ->whereNotNull('analysis_due_at')
            ->whereNotNull('analysis_stage_started_at')
            ->with('assignedTo')
            ->orderBy('id')
            ->get();

        $escalonados = 0;

        foreach ($processos as $processo) {
            $status = $sla->statusFor($processo->analysis_due_at, $processo->analysis_stage_started_at)['status'];

            [$tratamentoFaixa, $faixaLabel] = match ($status) {
                SlaStatus::Amarelo => [$tratamentoAmarelo, 'em alerta'],
                SlaStatus::Vermelho => [$tratamentoVencido, 'vencido'],
                default => [null, null], // Verde: nada a escalonar
            };

            if ($tratamentoFaixa === null) {
                continue;
            }

            foreach ($this->destinatarios($tratamentoFaixa, $processo, $gestorRole) as $destinatario) {
                if ($this->jaEscalonado($processo->id, $destinatario->id, $processo->analysis_stage_started_at)) {
                    continue;
                }

                $dispatcher->deliver($destinatario, new ProcessoEscalonadoNotification(
                    viabilityRequestId: $processo->id,
                    protocolNumber: (string) $processo->protocol_number,
                    assunto: "Processo {$faixaLabel} por SLA — {$processo->protocol_number}",
                    detalhe: "A solicitação {$processo->protocol_number} está {$faixaLabel} (prazo-limite em {$processo->analysis_due_at->format('d/m/Y')}). Avalie a atuação na fila de análise.",
                    url: route('gestao.processos.show', $processo->id),
                ));

                $escalonados++;
            }
        }

        if ($escalonados === 0) {
            $this->info('Nenhum processo a escalonar por SLA.');

            return self::SUCCESS;
        }

        $this->info("Escalonado(s) {$escalonados} alerta(s) de SLA.");

        return self::SUCCESS;
    }

    /**
     * Destinatários por tratamento parametrizado (HU-014). Só os tratamentos de
     * NOTIFICAÇÃO são tratados aqui (RN-003 — nunca decide). Tratamentos de ação
     * (ex.: redistribuir) são pendência SEDUR → no-op honesto (sem fingir ação).
     *
     * @return array<int, User>
     */
    private function destinatarios(string $tratamento, ViabilityRequest $processo, string $gestorRole): array
    {
        return match ($tratamento) {
            'notificar_analista' => array_values(array_filter([$processo->assignedTo])),
            'notificar_gestor' => User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', $gestorRole))
                ->orderBy('id')
                ->get()
                ->all(),
            default => [],
        };
    }

    /**
     * Idempotência via ledger: já existe um escalonamento_sla para este processo e
     * destinatário a partir do início da etapa atual (analysis_stage_started_at)?
     */
    private function jaEscalonado(int $viabilityRequestId, int $recipientUserId, ?DateTimeInterface $desde): bool
    {
        $query = Communication::query()
            ->where('viability_request_id', $viabilityRequestId)
            ->where('type', CommunicationType::EscalonamentoSla)
            ->where('recipient_user_id', $recipientUserId);

        if ($desde !== null) {
            $query->where('created_at', '>=', $desde);
        }

        return $query->exists();
    }
}
