<?php

namespace App\Notifications\Contracts;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationType;

/**
 * Contrato das Notifications de PROCESSO do EP11 (pendência, prazo,
 * escalonamento, resultado) — a forma comum que destrava o congelamento de
 * canais no disparo e a alimentação do ledger communications.
 *
 * Papel no pipeline (espelha VerifyEmailQueued::freezeUrlFor — resolve no
 * DISPARO, lê no envio enfileirado):
 *
 * 1. O NotificationDispatcher (11-04) resolve os canais de forma SÍNCRONA no
 *    disparo (toggles de feature + notificacoes.mapa_canais + gancho de
 *    preferência), cria as linhas em `communications` e então CONGELA os canais
 *    resolvidos na Notification via {@see freezeChannels()}.
 * 2. A Notification é enfileirada; no worker, o seu via() LÊ {@see channels()}
 *    (sem reresolver toggles, que poderiam ter mudado) e traduz cada
 *    CommunicationChannel para o canal nativo do Laravel (Email => 'mail',
 *    InApp => 'database', Whatsapp => canal customizado — Wave 2).
 * 3. Os métodos viabilityRequestId() e communicationType() identificam a linha
 *    do ledger que cada envio confirma/marca (idempotência + histórico HU-096).
 *
 * As Notifications de processo passam a implementar esta interface em 11-05/06
 * (junto da migração dos testes). NÃO é implementada aqui.
 */
interface ProcessNotification
{
    /**
     * Processo alvo da comunicação. Nullable: nem toda comunicação de processo
     * está atrelada a um viability_request (ex.: avisos gerais).
     */
    public function viabilityRequestId(): ?int;

    /**
     * Tipo que vira a linha do ledger communications.
     */
    public function communicationType(): CommunicationType;

    /**
     * Congela, no DISPARO, os canais resolvidos pelo dispatcher. Lido depois
     * pelo via() — não reresolver toggles no worker.
     *
     * @param  array<int, CommunicationChannel>  $channels
     */
    public function freezeChannels(array $channels): void;

    /**
     * Canais congelados no disparo, lidos pelo via() no envio.
     *
     * @return array<int, CommunicationChannel>
     */
    public function channels(): array;
}
