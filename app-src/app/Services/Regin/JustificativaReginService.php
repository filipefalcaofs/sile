<?php

namespace App\Services\Regin;

use App\Models\Activity;
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
     * @throws DomainException quando o processo não tem decisão, não veio da JUCEB ou o parecer já foi recebido por ela
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

        if ($this->parecerJaEnviado($processo)) {
            throw new DomainException('O parecer já foi enviado à JUCEB e ela confirmou o recebimento: a justificativa não pode mais ser alterada.');
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

    /**
     * Parecer com recebimento confirmado pela JUCEB (RECEBIDO_SUCESSO), o mesmo
     * registro que o regin:reenviar-pareceres usa para não reenviar.
     */
    public function parecerJaEnviado(ViabilityRequest $processo): bool
    {
        return Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', 'regin-parecer')
            ->where('result', 'sucesso')
            ->where('subject_type', $processo->getMorphClass())
            ->where('subject_id', $processo->getKey())
            ->exists();
    }
}
