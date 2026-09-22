<?php

namespace App\Services\EscritorioVirtual;

use App\Enums\SefazNotificationEvent;
use App\Enums\SefazNotificationStatus;
use App\Models\SefazNotification;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Notifications\AbrigadoDesvinculadoNotification;
use App\Services\Comunicacao\NotificationDispatcher;
use App\Services\Sefaz\SefazUnavailableException;
use App\Services\Sefaz\SefazViabilidadeGateway;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Desvinculação da inscrição imobiliária da SEDE de escritório virtual
 * (RN-EV-06). SERVIÇO COMPARTILHADO: será chamado (a) na mudança de endereço da
 * sede (revisão — quando o REDESIM existir) e (b) nos desfechos
 * indef/cassado/revogado/desativado (desfecho spec-2), além do gatilho manual de
 * gestão. Ao desvincular: desativa o lock (a inscrição volta a ficar livre),
 * DESVINCULA e NOTIFICA cada abrigado da inscrição (SEM cassação automática —
 * SEDUR 2026-07-16), audita (RN-002), e registra a comunicação devida à SEFAZ
 * como SefazNotification — registro reprocessável, não texto solto na auditoria
 * (`Alteração de Endereço` §4.3.2/§4.3.3, RN-EV-09/EV-10).
 */
class DesvincularInscricaoService
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private AuditService $audit,
        private SefazViabilidadeGateway $sefaz,
    ) {}

    /**
     * @return array{abrigados_notificados: int, sefaz_notification_id: int}
     */
    public function desvincular(
        VirtualOfficeInscriptionLock $lock,
        string $motivo,
        ?User $actor = null,
        SefazNotificationEvent $evento = SefazNotificationEvent::SedeEncerrada,
    ): array {
        $inscricao = (string) $lock->property_registration;
        $sede = $lock->sede;

        $abrigados = ViabilityRequest::query()
            ->where('property_registration', $inscricao)
            ->whereHas('decision', fn ($query) => $query->where('is_virtual_office_tenant', true))
            ->with('requester')
            ->get();

        $notification = DB::transaction(function () use ($lock, $sede, $inscricao, $motivo, $actor, $evento, $abrigados): SefazNotification {
            // Libera a inscrição: o vínculo dos abrigados é DERIVADO do lock ativo,
            // então desativá-lo já os desvincula (a decisão do abrigado é imutável,
            // nada a mutar nela).
            $lock->forceFill(['active' => false, 'released_at' => now()])->save();

            // O evento default (SedeEncerrada) cobre o gatilho manual da
            // retaguarda; mudança de endereço e desfechos da análise passam o
            // evento correspondente (RN-EV-09) para o registro não nascer
            // contraditório com o motivo.
            $notification = SefazNotification::create([
                'viability_request_id' => $sede->id,
                'event' => $evento,
                'cnpj' => $sede->company?->cnpj,
                'property_registration_anterior' => $inscricao,
                'property_registration_nova' => null,
                'endereco_anterior' => $this->enderecoDe($sede),
                'endereco_novo' => null,
                'status' => SefazNotificationStatus::Pendente,
            ]);

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
                    // O texto solto some; fica a referência ao registro reprocessável.
                    'sefaz_notification_id' => $notification->id,
                ],
                subject: $lock->sede,
            );

            return $notification;
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

        // Também FORA da transação, pelo mesmo motivo: uma falha de rede na
        // comunicação à SEFAZ não pode arrastar a desvinculação (já efetivada e
        // commitada) num rollback (§4.3.3).
        $this->reprocessarComunicacaoSefaz($notification);

        return [
            'abrigados_notificados' => $abrigados->count(),
            'sefaz_notification_id' => $notification->id,
        ];
    }

    /**
     * Tenta (novamente) enviar a comunicação à SEFAZ. Sucesso → Enviada,
     * enviada_em preenchido. Falha → Falha, com o erro registrado. Em ambos os
     * casos incrementa tentativas — nunca propaga a exceção: a comunicação com
     * a SEFAZ é auxiliar, o efeito de negócio já está efetivado (§4.3.3).
     */
    public function reprocessarComunicacaoSefaz(SefazNotification $notification): void
    {
        try {
            $this->sefaz->sendEventoEscritorioVirtual($notification);

            $notification->forceFill([
                'status' => SefazNotificationStatus::Enviada,
                'enviada_em' => now(),
                'tentativas' => $notification->tentativas + 1,
            ])->save();
        } catch (SefazUnavailableException $e) {
            $notification->forceFill([
                'status' => SefazNotificationStatus::Falha,
                'erro' => $e->getMessage(),
                'tentativas' => $notification->tentativas + 1,
            ])->save();
        }
    }

    private function enderecoDe(ViabilityRequest $request): string
    {
        return collect([$request->address_street, $request->address_number, $request->address_neighborhood])
            ->filter()
            ->implode(', ');
    }
}
