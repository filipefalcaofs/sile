<?php

namespace App\Services\Painel;

use App\Enums\AnalysisPendencyStatus;
use App\Models\AnalysisPendency;
use App\Models\Cidadao;
use Illuminate\Database\Eloquent\Builder;

/**
 * Convites que a pessoa pode responder agora: abertos, dentro do prazo, de
 * processo em que ela é requerente (ou solicitante declarado do REGIN).
 */
class ConvitesDoCidadao
{
    /**
     * @return list<array{id: int, solicitacao_id: int, protocol_number: ?string, empresa: ?string, descricao: string, due_at: ?string}>
     */
    public function abertos(Cidadao $efetivo): array
    {
        return $this->consulta($efetivo)
            ->with('viabilityRequest:id,protocol_number,company_id', 'viabilityRequest.company:id,legal_name')
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderBy('id')
            ->get()
            ->map(fn (AnalysisPendency $convite): array => [
                'id' => $convite->id,
                'solicitacao_id' => $convite->viability_request_id,
                'protocol_number' => $convite->viabilityRequest?->protocol_number,
                'empresa' => $convite->viabilityRequest?->company?->legal_name,
                'descricao' => $convite->description,
                'due_at' => $convite->due_at?->toDateTimeString(),
            ])
            ->all();
    }

    public function total(Cidadao $efetivo): int
    {
        return $this->consulta($efetivo)->count();
    }

    /**
     * @return Builder<AnalysisPendency>
     */
    private function consulta(Cidadao $efetivo): Builder
    {
        return AnalysisPendency::query()
            ->where('status', AnalysisPendencyStatus::Aberta)
            ->where(fn (Builder $prazo) => $prazo->whereNull('due_at')->orWhere('due_at', '>', now()))
            ->whereHas('viabilityRequest', fn (Builder $processo) => $processo->respondivelPor($efetivo->id, $efetivo->cpf));
    }
}
