<?php

namespace App\Services\Abuso\Detectors;

use App\Enums\AbuseSeverity;
use App\Models\Cidadao;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Abuso\AbuseFinding;
use App\Services\Abuso\Contracts\AbuseDetector;
use App\Services\Abuso\DetectionWindow;
use App\Support\Settings;

/**
 * HU-149: volume atípico de solicitações criadas pelo MESMO ator (created_by — o
 * "contador"/representante que abre "em nome de", HU-150) na janela. Determinístico
 * sobre dado real — agrupa viability_requests por quem criou (tipo + id) no período,
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
            ->selectRaw('created_by_type, created_by_id, count(*) as total')
            ->whereNotNull('created_by_id')
            ->whereBetween('created_at', [$window->start, $window->end])
            ->groupBy('created_by_type', 'created_by_id')
            ->havingRaw('count(*) > ?', [$limite])
            ->orderBy('created_by_type')
            ->orderBy('created_by_id')
            ->get();

        foreach ($grupos as $grupo) {
            $total = (int) $grupo->total;

            $ids = ViabilityRequest::query()
                ->where('created_by_type', $grupo->created_by_type)
                ->where('created_by_id', $grupo->created_by_id)
                ->whereBetween('created_at', [$window->start, $window->end])
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $modelo = $grupo->created_by_type;
            $contador = in_array($modelo, [User::class, Cidadao::class], true)
                ? $modelo::query()->find($grupo->created_by_id)
                : null;

            yield new AbuseFinding(
                ruleKey: $this->key(),
                severity: $total > $limite * 2 ? AbuseSeverity::Alta : AbuseSeverity::Media,
                fingerprint: hash('sha256', $this->key().'|'.$grupo->created_by_type.'|'.$grupo->created_by_id),
                evidence: [
                    'created_by_type' => $grupo->created_by_type,
                    'created_by_id' => (int) $grupo->created_by_id,
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
