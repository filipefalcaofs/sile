<?php

namespace App\Console\Commands;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\CommunicationType;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\Communication;
use App\Models\ViabilityRequest;
use App\Notifications\PrazoVencendoNotification;
use App\Services\Comunicacao\NotificationDispatcher;
use App\Support\Settings;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Console\Command;

/**
 * HU-093: alerta ANTECIPADO de prazos próximos do vencimento. Varre, dentro da
 * antecedência parametrizável (notificacoes.vencimento.antecedencia_dias):
 *
 * - processos em_analise cujo analysis_due_at (FONTE ÚNICA da Fase 10) ainda não
 *   venceu mas cai na janela → alerta o ANALISTA responsável (assigned_user_id);
 * - pendências abertas cujo due_at cai na janela → alerta o REQUERENTE.
 *
 * SÓ notifica (sem transição/timeline) — a fonte de prazo é única para não brigar
 * com a HU-129 (sem dupla contagem). As notificações percorrem o
 * NotificationDispatcher (multicanal + ledger). Vencido é tratamento da HU-147
 * (notificacoes:escalonar-sla), não daqui.
 *
 * IDEMPOTÊNCIA (RN-004) SEM schema novo: antes de alertar checa o ledger
 * communications por uma comunicação prazo_vencendo já registrada para o
 * processo/destinatário dentro da janela atual — duas execuções não duplicam.
 * Agendado com withoutOverlapping/onOneServer (espelha ExpressoIndeferirSemBap).
 */
class AlertarVencimentosCommand extends Command
{
    protected $signature = 'notificacoes:alertar-vencimentos';

    protected $description = 'HU-093: alerta o analista (prazo de análise) e o requerente (prazo de pendência) sobre vencimentos próximos, de forma idempotente';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $antecedenciaDias = (int) Settings::get(
            'notificacoes.vencimento.antecedencia_dias',
            config('sile.notificacoes.vencimento.antecedencia_dias', 3),
        );

        $agora = now();
        $limite = $agora->copy()->addDays($antecedenciaDias);

        $alertados = $this->alertarProcessos($dispatcher, $agora, $limite)
            + $this->alertarPendencias($dispatcher, $agora, $limite, $antecedenciaDias);

        if ($alertados === 0) {
            $this->info('Nenhum prazo dentro da antecedência para alertar.');

            return self::SUCCESS;
        }

        $this->info("Alertado(s) {$alertados} prazo(s) próximo(s) do vencimento.");

        return self::SUCCESS;
    }

    /**
     * Processos em_analise vencendo na janela → alerta o analista responsável.
     */
    private function alertarProcessos(NotificationDispatcher $dispatcher, CarbonInterface $agora, CarbonInterface $limite): int
    {
        $alertados = 0;

        $processos = ViabilityRequest::query()
            ->where('status', ViabilityRequestStatus::EmAnalise)
            ->whereNotNull('assigned_user_id')
            ->whereNotNull('analysis_due_at')
            ->where('analysis_due_at', '>', $agora)   // ainda não venceu (vencido → HU-147)
            ->where('analysis_due_at', '<=', $limite) // dentro da antecedência
            ->with('assignedTo')
            ->orderBy('id')
            ->get();

        foreach ($processos as $processo) {
            $analista = $processo->assignedTo;

            if ($analista === null
                || $this->jaAlertado($processo->id, $analista->id, $processo->analysis_stage_started_at)) {
                continue;
            }

            $dispatcher->deliver($analista, new PrazoVencendoNotification(
                viabilityRequestId: $processo->id,
                protocolNumber: (string) $processo->protocol_number,
                assunto: "Prazo de análise próximo do vencimento — {$processo->protocol_number}",
                detalhe: "O prazo de análise da solicitação {$processo->protocol_number} vence em {$processo->analysis_due_at->format('d/m/Y')}.",
                url: route('gestao.processos.show', $processo->id),
            ));

            $alertados++;
        }

        return $alertados;
    }

    /**
     * Pendências abertas vencendo na janela → alerta o requerente.
     */
    private function alertarPendencias(NotificationDispatcher $dispatcher, CarbonInterface $agora, CarbonInterface $limite, int $antecedenciaDias): int
    {
        $alertados = 0;

        $pendencias = AnalysisPendency::query()
            ->where('status', AnalysisPendencyStatus::Aberta)
            ->whereNotNull('due_at')
            ->where('due_at', '>', $agora)
            ->where('due_at', '<=', $limite)
            ->with('viabilityRequest.requester')
            ->orderBy('id')
            ->get();

        foreach ($pendencias as $pendencia) {
            $processo = $pendencia->viabilityRequest;
            $requerente = $processo?->requester;

            if ($processo === null || $requerente === null) {
                continue;
            }

            // Janela de idempotência da pendência: o início da antecedência atual
            // (due_at − antecedência). Qualquer alerta criado a partir daí é o aviso
            // desta janela — a 2ª passada o encontra e não duplica (RN-004).
            $inicioJanela = $pendencia->due_at->copy()->subDays($antecedenciaDias);

            if ($this->jaAlertado($processo->id, $requerente->id, $inicioJanela)) {
                continue;
            }

            $dispatcher->deliver($requerente, new PrazoVencendoNotification(
                viabilityRequestId: $processo->id,
                protocolNumber: (string) $processo->protocol_number,
                assunto: "Convite próximo do vencimento — {$processo->protocol_number}",
                detalhe: "O convite da sua solicitação {$processo->protocol_number} vence em {$pendencia->due_at->format('d/m/Y')}. Responda pelo portal SILE.",
                url: route('portal.solicitacoes.show', $processo->id),
            ));

            $alertados++;
        }

        return $alertados;
    }

    /**
     * Idempotência via ledger: já existe uma comunicação prazo_vencendo para este
     * processo e destinatário a partir do início da janela informada?
     */
    private function jaAlertado(int $viabilityRequestId, int $recipientUserId, ?DateTimeInterface $desde): bool
    {
        $query = Communication::query()
            ->where('viability_request_id', $viabilityRequestId)
            ->where('type', CommunicationType::PrazoVencendo)
            ->where('recipient_user_id', $recipientUserId);

        if ($desde !== null) {
            $query->where('created_at', '>=', $desde);
        }

        return $query->exists();
    }
}
