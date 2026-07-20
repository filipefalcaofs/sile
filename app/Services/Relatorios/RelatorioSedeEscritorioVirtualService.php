<?php

namespace App\Services\Relatorios;

use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Relatório sede × abrigados de escritório virtual (Plano R1 — Task 1). Para uma
 * inscrição imobiliária travada por uma sede ativa (RN-EV-03), agrupa a SEDE (o
 * alvo do lock ativo — sua decisão carrega o nº TVL da sede) e os ABRIGADOS
 * daquela inscrição (decisões com is_virtual_office_tenant=true — RN-EV-05).
 *
 * A consulta aceita o nº do produto TVL da sede OU a inscrição; sem filtro,
 * varre todas as inscrições com lock ativo. O vencimento fica FORA do escopo
 * (depende de validade do produto, ainda não modelada).
 */
class RelatorioSedeEscritorioVirtualService
{
    /**
     * Linhas da sede + abrigados das inscrições-alvo, paginadas. Filtros:
     * `sede` (nº TVL da sede) → inscrições dos locks ativos cuja sede tem esse
     * TVL; senão `inscricao` → aquela inscrição; senão → todas as travadas.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, ViabilityRequest>
     */
    public function consultar(array $filtros, int $perPage = 15): LengthAwarePaginator
    {
        $inscricoes = $this->resolverInscricoes($filtros);

        $sedeIds = $this->sedeIds($inscricoes);

        return ViabilityRequest::query()
            ->with(['company', 'decision'])
            ->whereIn('property_registration', $inscricoes->all())
            ->where(function (Builder $q) use ($sedeIds): void {
                $q->whereIn('id', $sedeIds->all())
                    ->orWhereHas('decision', fn (Builder $d) => $d->where('is_virtual_office_tenant', true));
            })
            ->orderBy('property_registration')
            // Sede antes dos abrigados dentro de cada inscrição.
            ->when(
                $sedeIds->isNotEmpty(),
                fn (Builder $q) => $q->orderByRaw('case when id in ('.$sedeIds->implode(',').') then 0 else 1 end'),
            )
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * Mapeia uma solicitação para uma linha do relatório. `tipo` = 'sede' quando
     * a solicitação é o alvo de um lock ativo, senão 'abrigado'. Vencimento fica
     * FORA (depende de validade, ainda não modelada).
     *
     * @return array{tipo: string, tvl: string|null, razao_social: string|null, data_emissao: string|null, inscricao: string|null, protocolo: string|null}
     */
    public function linha(ViabilityRequest $r): array
    {
        return [
            'tipo' => $this->ehSede($r) ? 'sede' : 'abrigado',
            'tvl' => $r->decision?->tvl_product_number,
            'razao_social' => $r->company?->trade_name ?: $r->company?->legal_name,
            'data_emissao' => $r->decision?->decided_at?->toIso8601String(),
            'inscricao' => $r->property_registration,
            'protocolo' => $r->protocol_number,
        ];
    }

    /**
     * Inscrições-alvo a partir dos filtros (ver {@see consultar()}).
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, string>
     */
    private function resolverInscricoes(array $filtros): Collection
    {
        if (! empty($filtros['sede'])) {
            return VirtualOfficeInscriptionLock::query()
                ->where('active', true)
                ->whereHas('sede.decision', fn (Builder $d) => $d->where('tvl_product_number', $filtros['sede']))
                ->pluck('property_registration')
                ->unique()
                ->values();
        }

        if (! empty($filtros['inscricao'])) {
            return collect([(string) $filtros['inscricao']]);
        }

        return VirtualOfficeInscriptionLock::query()
            ->where('active', true)
            ->pluck('property_registration')
            ->unique()
            ->values();
    }

    /**
     * IDs das solicitações-sede (alvos de locks ativos) nas inscrições-alvo.
     *
     * @param  Collection<int, string>  $inscricoes
     * @return Collection<int, int>
     */
    private function sedeIds(Collection $inscricoes): Collection
    {
        return VirtualOfficeInscriptionLock::query()
            ->where('active', true)
            ->whereIn('property_registration', $inscricoes->all())
            ->pluck('sede_viability_request_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function ehSede(ViabilityRequest $r): bool
    {
        return VirtualOfficeInscriptionLock::query()
            ->where('active', true)
            ->where('sede_viability_request_id', $r->id)
            ->exists();
    }
}
