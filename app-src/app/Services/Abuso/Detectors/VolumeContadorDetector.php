<?php

namespace App\Services\Abuso\Detectors;

use App\Enums\AbuseSeverity;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Abuso\AbuseFinding;
use App\Services\Abuso\Contracts\AbuseDetector;
use App\Services\Abuso\DetectionWindow;
use App\Support\Settings;

/**
 * HU-149: volume atípico de solicitações criadas pelo MESMO ator (created_by — o
 * "contador"/representante que abre "em nome de", HU-150) na janela. Determinístico
 * sobre dado real — agrupa viability_requests por created_by_user_id no período,
 * SEM IA. Emite um finding por criador cujo total na janela supera
 * `abuso.volume_contador.limite` (HU-014, default 20). fingerprint estável por
 * (regra, criador) — idempotência estrutural da 12-03. Apenas ALERTA (RN-001).
 */
class VolumeContadorDetector implements AbuseDetector
{
    public function key(): string
    {
        return 'volume_contador';
    }

    /**
     * @return iterable<AbuseFinding>
     */
    public function detect(DetectionWindow $window): iterable
    {
        $limite = (int) Settings::get('abuso.volume_contador.limite', 20);

        $grupos = ViabilityRequest::query()
            ->selectRaw('created_by_user_id, count(*) as total')
            ->whereNotNull('created_by_user_id')
            ->whereBetween('created_at', [$window->start, $window->end])
            ->groupBy('created_by_user_id')
            ->havingRaw('count(*) > ?', [$limite])
            ->orderBy('created_by_user_id')
            ->get();

        foreach ($grupos as $grupo) {
            $total = (int) $grupo->total;

            $ids = ViabilityRequest::query()
                ->where('created_by_user_id', $grupo->created_by_user_id)
                ->whereBetween('created_at', [$window->start, $window->end])
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $contador = User::query()->find($grupo->created_by_user_id);

            yield new AbuseFinding(
                ruleKey: $this->key(),
                severity: $total > $limite * 2 ? AbuseSeverity::Alta : AbuseSeverity::Media,
                fingerprint: hash('sha256', $this->key().'|'.$grupo->created_by_user_id),
                evidence: [
                    'created_by_user_id' => (int) $grupo->created_by_user_id,
                    'total' => $total,
                    'limite' => $limite,
                    'ids' => $ids,
                ],
                viabilityRequestId: $ids === [] ? null : (int) max($ids),
                subject: $contador,
                windowStart: $window->start,
                windowEnd: $window->end,
            );
        }
    }
}
