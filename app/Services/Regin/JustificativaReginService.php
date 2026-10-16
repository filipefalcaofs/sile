<?php

namespace App\Services\Regin;

use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use DomainException;

/**
 * Grava no processo a justificativa que o parecer leva à JUCEB. A decisão é
 * imutável, então o texto fica no processo e o envio automático lê dele.
 */
class JustificativaReginService
{
    public function __construct(private AuditService $audit) {}

    /**
     * @throws DomainException quando o processo não tem decisão ou não veio da JUCEB
     */
    public function salvar(ViabilityRequest $processo, string $justificativa): void
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
            ],
            subject: $processo,
        );
    }
}
