<?php

namespace App\Services\Geo;

use App\Enums\PoligonoFonte;
use App\Enums\StatusResolucaoPoligono;
use App\Models\LotPolygonResolution;
use App\Models\ViabilityRequest;

/**
 * Fonte do polígono do lote para a tela: legenda do contorno, atribuição
 * exigida pela licença e, para as fontes complementares, a procedência
 * (id original, versão, data e área).
 */
class ProcedenciaPoligono
{
    /**
     * @return array{value: string, legenda: string, atribuicao: string|null, oficial: bool, procedencia: array{source_id: string|null, release: string|null, obtido_em: string|null, area_m2: float|null}|null}|null
     */
    public function paraTela(ViabilityRequest $request): ?array
    {
        if (! is_array($request->address_polygon_geojson) || $request->address_polygon_geojson === []) {
            return null;
        }

        // Polígono gravado antes da coluna de fonte veio do endereçamento por chave.
        $fonte = $request->address_polygon_source ?? PoligonoFonte::Chave;

        return [
            'value' => $fonte->value,
            'legenda' => $fonte->legenda(),
            'atribuicao' => $fonte->atribuicao(),
            'oficial' => $fonte->oficial(),
            'procedencia' => $fonte->oficial() ? null : $this->procedencia($request, $fonte),
        ];
    }

    /**
     * @return array{source_id: string|null, release: string|null, obtido_em: string|null, area_m2: float|null}|null
     */
    private function procedencia(ViabilityRequest $request, PoligonoFonte $fonte): ?array
    {
        $resolucao = LotPolygonResolution::query()
            ->where('viability_request_id', $request->id)
            ->where('source', $fonte)
            ->where('status', StatusResolucaoPoligono::Encontrado)
            ->latest('id')
            ->first();

        if ($resolucao === null) {
            return null;
        }

        return [
            'source_id' => $resolucao->source_id,
            'release' => $resolucao->release,
            'obtido_em' => $resolucao->created_at?->toIso8601String(),
            'area_m2' => $resolucao->area_m2,
        ];
    }
}
