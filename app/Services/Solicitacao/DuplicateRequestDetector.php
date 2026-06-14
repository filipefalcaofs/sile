<?php

namespace App\Services\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\ViabilityRequest;

/**
 * Detecção de duplicidade/reincidência por CNPJ (HU-061 RN-007). Aponta o
 * processo anterior mais recente da MESMA empresa (recente OU ativo) para que
 * o requerente receba um ALERTA com link ao processo — NUNCA bloqueia a nova
 * solicitação (direito de petição).
 *
 * Escopo honesto desta fase: a detecção é por CNPJ (via company_id). A
 * detecção por inscrição imobiliária degrada (lote/Cadastro Multifinalitário
 * BLOQUEADO pendente SEDUR — Fase 13) e fica fora daqui. A janela de recência
 * é uma constante de config (`sile.solicitacao.duplicidade.janela_dias`): a
 * definição oficial de "reincidência" é pendência SEDUR — default honesto.
 */
class DuplicateRequestDetector
{
    /**
     * Janela padrão (em dias) caso a config esteja ausente — default inline.
     */
    private const DEFAULT_WINDOW_DAYS = 180;

    /**
     * Procura o processo anterior da empresa que caracteriza reincidência:
     * mesma empresa, não cancelado e (criado dentro da janela OU ainda ativo —
     * protocolada). Retorna o mais recente como alerta, ou null se não houver.
     *
     * @return array{request_id: int, protocol_number: string|null, status: string, created_at: string|null}|null
     */
    public function detect(Company $company, ?int $excludeRequestId = null): ?array
    {
        $windowDays = (int) config('sile.solicitacao.duplicidade.janela_dias', self::DEFAULT_WINDOW_DAYS);
        $windowStart = now()->subDays($windowDays);

        $previous = ViabilityRequest::query()
            ->where('company_id', $company->id)
            ->where('status', '!=', ViabilityRequestStatus::Cancelada->value)
            ->when($excludeRequestId !== null, fn ($query) => $query->whereKeyNot($excludeRequestId))
            ->where(function ($query) use ($windowStart) {
                $query->where('created_at', '>=', $windowStart)
                    ->orWhere('status', ViabilityRequestStatus::Protocolada->value);
            })
            ->latest()
            ->first();

        if ($previous === null) {
            return null;
        }

        return $this->toArray($previous);
    }

    /**
     * Duplicidade por REFERÊNCIA EXTERNA (BAP/Regin) — HU-148 RN-004: o mesmo
     * identificador externo mapeia para UM único processo, logo a vinculação
     * posterior não pode duplicar. Diferente da reincidência por CNPJ (alerta,
     * direito de petição), aqui o vínculo repetido é bloqueado pelo chamador.
     * Retorna o processo não cancelado que já carrega a referência, ou null.
     *
     * @return array{request_id: int, protocol_number: string|null, status: string, created_at: string|null}|null
     */
    public function detectByExternalReference(string $externalReference, ?int $excludeRequestId = null): ?array
    {
        $reference = trim($externalReference);

        if ($reference === '') {
            return null;
        }

        $existing = ViabilityRequest::query()
            ->where('external_reference', $reference)
            ->where('status', '!=', ViabilityRequestStatus::Cancelada->value)
            ->when($excludeRequestId !== null, fn ($query) => $query->whereKeyNot($excludeRequestId))
            ->latest()
            ->first();

        return $existing === null ? null : $this->toArray($existing);
    }

    /**
     * @return array{request_id: int, protocol_number: string|null, status: string, created_at: string|null}
     */
    private function toArray(ViabilityRequest $request): array
    {
        return [
            'request_id' => $request->id,
            'protocol_number' => $request->protocol_number,
            'status' => $request->status->value,
            'created_at' => $request->created_at?->toISOString(),
        ];
    }
}
