<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdateSolicitacaoImovelRequest;
use App\Models\ViabilityRequest;
use App\Services\Geo\TerritoryService;
use App\Services\Solicitacao\PropertyGeometryWriter;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Instrução do imóvel (HU-062) e da área (HU-063) do rascunho. Grava o polígono
 * (GeoJSON é a FONTE), complemento (texto livre — HU-139 adiado), ponto de
 * referência e indicadores; identifica o território reusando o TerritoryService
 * da Fase 4 (degrada honesto sem zona — NUNCA inventa); deriva a geometry
 * driver-aware (PropertyGeometryWriter, só pgsql); valida a área declarada
 * contra a do polígono com tolerância parametrizável (ALERTA, não bloqueia —
 * RN-004); e zera a simulação anterior (markSimulationStale — RN-005). Só o
 * dono edita, e apenas em rascunho (ViabilityRequestPolicy::update, CA-04).
 */
class SolicitacaoImovelController extends Controller
{
    public function __construct(
        private TerritoryService $territory,
        private PropertyGeometryWriter $geometryWriter,
        private AuditService $audit,
    ) {}

    public function update(UpdateSolicitacaoImovelRequest $request, ViabilityRequest $solicitacao): RedirectResponse
    {
        Gate::authorize('update', $solicitacao);

        /** @var array<string, mixed> $geojson */
        $geojson = $request->validated('property_polygon_geojson');
        $declaredArea = (float) $request->validated('used_area_m2');

        // Território pelo centroide do polígono — reusa a Fase 4. Degrada honesto
        // (zona pendente SEDUR → "indisponível", nunca um valor falso). NÃO
        // recomputa o veredito: aqui só identifica/registra para exibição.
        [$lat, $lng] = $this->centroid($geojson);
        $territoryResult = $this->territory->identify($lat, $lng);

        // Validação área×polígono (RN-004): a declarada não pode exceder a área do
        // polígono além da tolerância parametrizável. Contra o LOTE oficial a
        // validação degrada (lote pendente SEDUR), então a base é o próprio
        // polígono desenhado — alerta orientativo, nunca bloqueio.
        $polygonArea = $this->geometryWriter->polygonAreaSquareMeters($geojson);
        $areaAlert = $this->buildAreaAlert($declaredArea, $polygonArea);

        DB::transaction(function () use ($request, $solicitacao, $geojson, $areaAlert): void {
            $solicitacao->update([
                'property_polygon_geojson' => $geojson,
                'used_area_m2' => $request->validated('used_area_m2'),
                'address_street' => $request->validated('address_street'),
                'address_number' => $request->validated('address_number'),
                'address_complement' => $request->validated('address_complement'),
                'address_neighborhood' => $request->validated('address_neighborhood'),
                'address_zip' => $request->validated('address_zip'),
                'address_reference' => $request->validated('address_reference'),
                'property_registration' => $request->validated('property_registration'),
                'is_virtual_office' => $request->boolean('is_virtual_office'),
                'is_public_area' => $request->boolean('is_public_area'),
                'has_independent_access' => $request->boolean('has_independent_access'),
            ]);

            // Geometria derivada (property_polygon) — só no pgsql; em SQLite a
            // fonte é o jsonb (no-op).
            $this->geometryWriter->write($solicitacao);

            // Mudança de imóvel/área invalida a simulação orientativa (RN-005).
            $solicitacao->markSimulationStale();

            // A inconsistência mantida (declarada ≫ polígono) fica REGISTRADA para
            // o analista (auditada) — não bloqueia (orienta correção).
            if ($areaAlert !== null) {
                $this->audit->log(
                    logName: 'solicitacoes',
                    event: 'imovel-area-inconsistente',
                    description: 'Área declarada diverge da área do polígono acima da tolerância',
                    properties: $areaAlert,
                    subject: $solicitacao,
                    result: 'alerta',
                );
            }
        });

        $redirect = back()
            ->with('status', 'Imóvel e área da solicitação atualizados com sucesso.')
            ->with('territorio', $territoryResult->toArray());

        if ($areaAlert !== null) {
            $redirect->with('areaAlert', $areaAlert);
        }

        return $redirect;
    }

    /**
     * Centroide (lat, lng) pela média dos vértices únicos do anel exterior —
     * cálculo portável em PHP (roda igual em SQLite e Postgres), suficiente para
     * a identificação territorial por ponto. A geometria espacial REAL fica no
     * pgsql (PropertyGeometryWriter).
     *
     * @param  array<string, mixed>  $geojson
     * @return array{0: float, 1: float}
     */
    private function centroid(array $geojson): array
    {
        /** @var array<int, array<int, float|int>> $ring */
        $ring = $geojson['coordinates'][0];

        // Remove o vértice de fechamento (igual ao primeiro) para não enviesar.
        if (count($ring) > 1 && $ring[0] === $ring[count($ring) - 1]) {
            array_pop($ring);
        }

        $lngs = array_map(static fn (array $point): float => (float) $point[0], $ring);
        $lats = array_map(static fn (array $point): float => (float) $point[1], $ring);

        return [
            array_sum($lats) / count($lats),
            array_sum($lngs) / count($lngs),
        ];
    }

    /**
     * Alerta de inconsistência área×polígono (RN-004): apenas quando a declarada
     * excede a do polígono além da tolerância parametrizável
     * (solicitacao.area_poligono.tolerancia_percentual). Usar menos área que o
     * imóvel é legítimo (não alerta). Retorna null sem polígono medível ou dentro
     * da tolerância.
     *
     * @return array<string, mixed>|null
     */
    private function buildAreaAlert(float $declaredArea, ?float $polygonArea): ?array
    {
        if ($polygonArea === null || $polygonArea <= 0.0) {
            return null;
        }

        $tolerancia = (float) Settings::get(
            'solicitacao.area_poligono.tolerancia_percentual',
            config('sile.solicitacao.area_poligono.tolerancia_percentual', 10),
        );

        $divergencia = ($declaredArea - $polygonArea) / $polygonArea * 100;

        if ($divergencia <= $tolerancia) {
            return null;
        }

        return [
            'area_declarada_m2' => round($declaredArea, 2),
            'area_poligono_m2' => round($polygonArea, 2),
            'tolerancia_percentual' => $tolerancia,
            'divergencia_percentual' => round($divergencia, 2),
        ];
    }
}
