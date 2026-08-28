<?php

namespace App\Services\Sefaz;

use App\Models\SefazNotification;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;

/**
 * Contrato de envio à SEFAZ municipal. Dois canais distintos, um provider só:
 * sendViabilidade transmite o DEFERIMENTO de uma viabilidade (HU-110, quem
 * chama — listener 09-09 — decide); sendEventoEscritorioVirtual comunica uma
 * mudança de condição cadastral de escritório virtual já deferida (RN-EV-09).
 * O provider concreto é substituível por binding — a Fase 13 liga a SEFAZ
 * conveniada sem tocar call sites (mesmo padrão de PropertyRegistryLookup).
 */
interface SefazViabilidadeGateway
{
    /**
     * Envia os dados de viabilidade do deferimento à SEFAZ municipal. A forma
     * fina do payload é definida na Fase 13 (contrato/homologação).
     *
     * @throws SefazUnavailableException quando a SEFAZ está indisponível/pendente (Fase 13)
     */
    public function sendViabilidade(ViabilityRequest $request, ViabilityDecision $decision): void;

    /**
     * Comunica a SEFAZ um evento que muda a condicao cadastral de escritorio
     * virtual — encerramento da sede, mudanca de endereco, perda da condicao
     * (RN-EV-09). Canal distinto do sendViabilidade: aquele comunica o
     * DEFERIMENTO de uma viabilidade, este comunica a alteracao de uma condicao
     * ja deferida, e o payload nao e o mesmo.
     *
     * @throws SefazUnavailableException quando a SEFAZ esta indisponivel/pendente
     */
    public function sendEventoEscritorioVirtual(SefazNotification $notification): void;
}
