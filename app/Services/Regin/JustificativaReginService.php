<?php

namespace App\Services\Regin;

use App\Events\ResultadoEmitido;
use App\Listeners\ComunicarResultadoRegin;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use DomainException;

/**
 * Grava no processo a justificativa que o parecer leva à JUCEB. A decisão é
 * imutável, então o texto fica no processo e o envio lê dele. Opcionalmente reenvia
 * o parecer na hora, pelo mesmo caminho do envio normal (log de integração e
 * auditoria regin-parecer).
 */
class JustificativaReginService
{
    public function __construct(
        private AuditService $audit,
        private ComunicarResultadoRegin $regin,
    ) {}

    /**
     * @throws DomainException quando o processo não tem decisão ou não veio da JUCEB
     */
    public function salvar(ViabilityRequest $processo, string $justificativa, bool $reenviar): void
    {
        $decisao = $processo->decision;

        if ($decisao === null) {
            throw new DomainException('O processo ainda não tem decisão: conclua a análise antes de editar a justificativa enviada à JUCEB.');
        }

        if (blank($processo->external_reference)) {
            throw new DomainException('Este processo não veio da JUCEB, então não há parecer a enviar.');
        }

        $texto = trim($justificativa);

        $processo->forceFill(['regin_justificativa' => $texto])->save();

        $this->audit->log(
            logName: 'analise',
            event: 'justificativa-regin',
            description: "Justificativa do parecer à JUCEB editada no processo #{$processo->id}",
            properties: [
                'viability_request_id' => $processo->id,
                'protocolo' => $processo->external_reference,
                'tamanho' => mb_strlen($texto),
                'reenviar' => $reenviar,
            ],
            subject: $processo,
        );

        if ($reenviar) {
            $this->regin->handle(new ResultadoEmitido($processo->fresh(), $decisao));
        }
    }
}
