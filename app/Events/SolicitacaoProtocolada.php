<?php

namespace App\Events;

use App\Models\ViabilityRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * PRIMEIRO evento de domínio do SILE: a solicitação foi protocolada (HU-068).
 *
 * Disparado pelo ProtocolarSolicitacaoService APÓS o commit da transação de
 * protocolo — só protocolos efetivados geram efeitos. Implementa
 * ShouldDispatchAfterCommit como contrato/defesa: se algum dia for despachado
 * de dentro de uma transação, só será observado após o commit (nunca em
 * rollback).
 *
 * A auditoria do protocolo NÃO depende deste evento: a transição síncrona
 * rascunho→protocolada (ViabilityRequestStateMachine) já grava a trilha
 * (RN-002) dentro da própria transação, garantida mesmo se um listener falhar.
 *
 * Listener real hoje: RegistrarTrilhaProtocolo (marco amigável da timeline +
 * auditoria de alto nível). Os ganchos FUTUROS pendurarão listeners no MESMO
 * evento, sem tocar o protocolo (NÃO implementar aqui):
 *  - notificação ao cidadão (EP11);
 *  - avaliação de elegibilidade do fluxo expresso (EP09);
 *  - resposta ao integrador Regin (EP13).
 */
class SolicitacaoProtocolada implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public ViabilityRequest $request) {}
}
