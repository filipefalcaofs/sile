<?php

namespace App\Services\Abuso\Detectors;

use App\Enums\AbuseSeverity;
use App\Models\Company;
use App\Models\ViabilityRequest;
use App\Services\Abuso\AbuseFinding;
use App\Services\Abuso\Contracts\AbuseDetector;
use App\Services\Abuso\DetectionWindow;
use App\Support\Settings;

/**
 * HU-149: volume atípico de solicitações pelo MESMO CNPJ na janela. Determinístico
 * sobre dado real — agrupa viability_requests por company_id no período, SEM IA.
 * Emite um finding por empresa cujo total criado na janela supera
 * `abuso.volume_cnpj.limite` (HU-014, default 5). fingerprint estável por
 * (regra, cnpj) → a mesma ocorrência não duplica o alerta aberto (idempotência
 * estrutural da 12-03). Apenas ALERTA — nunca pune (RN-001).
 */
class VolumeCnpjDetector implements AbuseDetector
{
    public function key(): string
    {
        return 'volume_cnpj';
    }

    /**
     * @return iterable<AbuseFinding>
     */
    public function detect(DetectionWindow $window): iterable
    {
        $limite = (int) Settings::get('abuso.volume_cnpj.limite', 5);

        $grupos = ViabilityRequest::query()
            ->selectRaw('company_id, count(*) as total')
            ->whereNotNull('company_id')
            ->whereBetween('created_at', [$window->start, $window->end])
            ->groupBy('company_id')
            ->havingRaw('count(*) > ?', [$limite])
            ->orderBy('company_id')
            ->get();

        foreach ($grupos as $grupo) {
            $total = (int) $grupo->total;

            $ids = ViabilityRequest::query()
                ->where('company_id', $grupo->company_id)
                ->whereBetween('created_at', [$window->start, $window->end])
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $company = Company::query()->find($grupo->company_id);
            $cnpj = $company?->cnpj ?? (string) $grupo->company_id;

            yield new AbuseFinding(
                ruleKey: $this->key(),
                severity: $total > $limite * 2 ? AbuseSeverity::Alta : AbuseSeverity::Media,
                fingerprint: hash('sha256', $this->key().'|'.$cnpj),
                evidence: [
                    'cnpj' => $cnpj,
                    'total' => $total,
                    'limite' => $limite,
                    'ids' => $ids,
                ],
                viabilityRequestId: $ids === [] ? null : (int) max($ids),
                subject: $company,
                windowStart: $window->start,
                windowEnd: $window->end,
            );
        }
    }
}
