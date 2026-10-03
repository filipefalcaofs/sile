<?php

namespace App\Services\Geo;

use App\Enums\LotePoligonoFonte;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\PropertyGeometryWriter;
use App\Support\Audit\AuditService;
use Throwable;

/**
 * Guarda a geometria de cada fonte no processo e mantém `lote_poligono_fonte`
 * apontando para a que vence a precedência. Fonte sem geometria também é
 * gravada: a pendência fica visível, nunca silenciosa.
 */
class PoligonoLoteService
{
    public function __construct(
        private AuditService $audit,
        private PropertyGeometryWriter $geometria,
    ) {}

    public function registrar(ViabilityRequest $request, PoligonoLoteHit $hit): PoligonoLoteHit
    {
        $poligonos = is_array($request->lote_poligonos) ? $request->lote_poligonos : [];

        $poligonos[$hit->fonte->value] = [
            ...$hit->toArray(),
            'consultado_em' => now()->toIso8601String(),
        ];

        $request->forceFill([
            'lote_poligonos' => $poligonos,
            'lote_poligono_fonte' => $this->vigente($poligonos)?->value,
        ])->save();

        // Mantém a geometry derivada (PostGIS) na mesma geometria que governa
        // o processo — os precedentes espaciais leem essa coluna.
        $this->geometria->write($request);

        $this->auditar($request, $hit);

        return $hit;
    }

    /**
     * Fonte vigente pela precedência do enum: a primeira com geometria.
     *
     * @param  array<string, mixed>  $poligonos
     */
    private function vigente(array $poligonos): ?LotePoligonoFonte
    {
        foreach (LotePoligonoFonte::precedencia() as $fonte) {
            $registro = $poligonos[$fonte->value] ?? null;

            if (is_array($registro) && is_array($registro['geojson'] ?? null)) {
                return $fonte;
            }
        }

        return null;
    }

    /**
     * A trilha nunca derruba a gravação da geometria.
     */
    private function auditar(ViabilityRequest $request, PoligonoLoteHit $hit): void
    {
        try {
            $this->audit->log(
                logName: 'geo',
                event: 'poligono-lote',
                description: "Geometria do lote consultada em {$hit->fonte->label()} na solicitação #{$request->id}",
                properties: [
                    'viability_request_id' => $request->id,
                    'fonte' => $hit->fonte->value,
                    'status' => $hit->status,
                    'candidatas' => $hit->candidatas,
                    'motivo' => $hit->motivo,
                    'fonte_vigente' => $request->lote_poligono_fonte?->value,
                ],
                subject: $request,
                result: $hit->status,
                rulesVersion: 'lei-9148-2016-art18',
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
