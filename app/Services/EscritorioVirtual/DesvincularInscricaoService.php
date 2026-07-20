<?php

namespace App\Services\EscritorioVirtual;

use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Notifications\AbrigadoDesvinculadoNotification;
use App\Services\Comunicacao\NotificationDispatcher;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Desvinculação da inscrição imobiliária da SEDE de escritório virtual
 * (RN-EV-06). SERVIÇO COMPARTILHADO: será chamado (a) na mudança de endereço da
 * sede (revisão — quando o REDESIM existir) e (b) nos desfechos
 * indef/cassado/revogado/desativado (desfecho spec-2), além do gatilho manual de
 * gestão. Ao desvincular: desativa o lock (a inscrição volta a ficar livre),
 * DESVINCULA e NOTIFICA cada abrigado da inscrição (SEM cassação automática —
 * SEDUR 2026-07-16), audita (RN-002), e registra a pendência SEFAZ (bloqueio
 * honesto — Fase 13; o gateway não tem método de desvinculação, nada de fachada).
 */
class DesvincularInscricaoService
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private AuditService $audit,
    ) {}

    /**
     * @return array{abrigados_notificados: int}
     */
    public function desvincular(VirtualOfficeInscriptionLock $lock, string $motivo, ?User $actor = null): array
    {
        $inscricao = (string) $lock->property_registration;

        $abrigados = ViabilityRequest::query()
            ->where('property_registration', $inscricao)
            ->whereHas('decision', fn ($query) => $query->where('is_virtual_office_tenant', true))
            ->with('requester')
            ->get();

        DB::transaction(function () use ($lock, $inscricao, $motivo, $actor, $abrigados): void {
            // Libera a inscrição: o vínculo dos abrigados é DERIVADO do lock ativo,
            // então desativá-lo já os desvincula (a decisão do abrigado é imutável,
            // nada a mutar nela).
            $lock->forceFill(['active' => false, 'released_at' => now()])->save();

            $this->audit->log(
                logName: 'escritorio-virtual',
                event: 'ev-desvinculacao',
                description: "Inscrição {$inscricao} desvinculada da sede #{$lock->sede_viability_request_id}: {$motivo}",
                properties: [
                    'property_registration' => $inscricao,
                    'sede_viability_request_id' => $lock->sede_viability_request_id,
                    'motivo' => $motivo,
                    'abrigados' => $abrigados->pluck('id')->all(),
                    'actor_user_id' => $actor?->id,
                    // SEFAZ: sem método de desvinculação no gateway (Fase 13) — a
                    // comunicação fica pendente, registrada honestamente, nunca fingida.
                    'sefaz' => 'pendente (integração bloqueada — Fase 13)',
                ],
                subject: $lock->sede,
            );
        });

        // APÓS o commit: notifica cada abrigado (requerente) que perdeu o vínculo
        // com a sede — efeito só de uma desvinculação efetivada.
        foreach ($abrigados as $abrigado) {
            $requester = $abrigado->requester;

            if ($requester !== null) {
                $this->dispatcher->deliver($requester, new AbrigadoDesvinculadoNotification(
                    viabilityRequestId: (int) $abrigado->id,
                    protocolNumber: (string) $abrigado->protocol_number,
                    propertyRegistration: $inscricao,
                    url: route('portal.solicitacoes.show', $abrigado->id),
                ));
            }
        }

        return ['abrigados_notificados' => $abrigados->count()];
    }
}
