<?php

namespace App\Services\Geo\Edificacoes;

use App\Enums\FonteEdificacao;
use App\Enums\PoligonoFonte;
use App\Enums\StatusResolucaoPoligono;
use App\Models\LotPolygonResolution;
use App\Models\ViabilityRequest;
use App\Support\Settings;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Polígono do lote a partir das edificações complementares, quando as fontes
 * da Prefeitura não acharam o lote: Overture, depois Microsoft, depois um
 * polígono já gerado por IA. Escolhe o prédio que contém o ponto do imóvel
 * (o de menor área, se houver sobreposição) e grava no mesmo campo do lote.
 * Nunca sobrescreve polígono já gravado. Cada tentativa fica em
 * lot_polygon_resolutions.
 */
class PoligonoComplementarService
{
    public function __construct(private BuildingFootprintRepository $base) {}

    public function resolver(ViabilityRequest $request): ResultadoComplementar
    {
        if ($this->temPoligono($request)) {
            return new ResultadoComplementar(StatusResolucaoPoligono::Encontrado, $request->address_polygon_source);
        }

        $ligadas = array_filter(FonteEdificacao::cases(), fn (FonteEdificacao $fonte): bool => $this->ligada($fonte));

        if ($ligadas === []) {
            return new ResultadoComplementar(StatusResolucaoPoligono::Desligado);
        }

        if ($request->pontoDoImovel() === null) {
            return new ResultadoComplementar(StatusResolucaoPoligono::NaoEncontrado);
        }

        if (! $request->pontoElegivelParaEdificacao()) {
            foreach ($ligadas as $fonte) {
                $this->registrar($request, $fonte->paraPoligonoFonte(), StatusResolucaoPoligono::NaoEncontrado, ['reason' => 'ponto_inelegivel']);
            }

            return new ResultadoComplementar(StatusResolucaoPoligono::NaoEncontrado);
        }

        foreach (FonteEdificacao::cases() as $fonte) {
            if (! $this->ligada($fonte)) {
                $this->registrar($request, $fonte->paraPoligonoFonte(), StatusResolucaoPoligono::Desligado);

                continue;
            }

            $inicio = hrtime(true);

            try {
                $candidata = $this->base->contendoPonto($fonte, ...$this->ponto($request));
            } catch (Throwable $e) {
                $this->registrar($request, $fonte->paraPoligonoFonte(), StatusResolucaoPoligono::Indisponivel, [
                    'reason' => mb_substr($e->getMessage(), 0, 255),
                    'latency_ms' => $this->desde($inicio),
                ]);

                continue;
            }

            if ($candidata !== null) {
                $this->gravar($request, $candidata, $this->desde($inicio));

                return new ResultadoComplementar(StatusResolucaoPoligono::Encontrado, $fonte->paraPoligonoFonte());
            }

            if ($fonte === FonteEdificacao::Ia) {
                $this->registrar($request, PoligonoFonte::Ia, StatusResolucaoPoligono::Pendente, [
                    'reason' => 'aguardando_inferencia',
                    'latency_ms' => $this->desde($inicio),
                ]);

                return new ResultadoComplementar(StatusResolucaoPoligono::Pendente, PoligonoFonte::Ia, precisaIa: true);
            }

            $this->registrar($request, $fonte->paraPoligonoFonte(), StatusResolucaoPoligono::NaoEncontrado, [
                'reason' => 'ponto_fora_de_edificacao',
                'latency_ms' => $this->desde($inicio),
            ]);
        }

        return new ResultadoComplementar(StatusResolucaoPoligono::NaoEncontrado);
    }

    /**
     * Grava a edificação como polígono do lote, sob a trava do processo. Devolve
     * falso quando outro caminho gravou um polígono antes.
     */
    public function gravar(ViabilityRequest $request, EdificacaoCandidata $candidata, ?int $latenciaMs = null): bool
    {
        try {
            return Cache::lock("poligono-ponto:{$request->id}", 90)->block(
                (int) config('viabiliza.integrations.geoserver.enderecamento.espera_trava_ponto', 60),
                function () use ($request, $candidata, $latenciaMs): bool {
                    $request->refresh();

                    if ($this->temPoligono($request)) {
                        return false;
                    }

                    $fonte = $candidata->fonte->paraPoligonoFonte();

                    $request->forceFill([
                        'address_polygon_geojson' => $candidata->geojson,
                        'address_polygon_source' => $fonte,
                        'address_polygon_consulted_at' => now(),
                    ])->save();

                    $this->registrar($request, $fonte, StatusResolucaoPoligono::Encontrado, [
                        'building_footprint_id' => $candidata->id,
                        'source_id' => $candidata->sourceId,
                        'release' => $candidata->release,
                        'method' => $candidata->metodo,
                        'area_m2' => $candidata->areaM2,
                        'confidence' => $candidata->confidence,
                        'latency_ms' => $latenciaMs,
                    ]);

                    return true;
                },
            );
        } catch (LockTimeoutException) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function registrar(ViabilityRequest $request, PoligonoFonte $fonte, StatusResolucaoPoligono $status, array $extra = []): LotPolygonResolution
    {
        $ponto = $request->pontoDoImovel();

        return LotPolygonResolution::query()->create([
            'viability_request_id' => $request->id,
            'source' => $fonte,
            'status' => $status,
            'official' => $fonte->oficial(),
            'point_lat' => $ponto['lat'] ?? null,
            'point_lng' => $ponto['lng'] ?? null,
            'point_origin' => $this->origemDoPonto($request),
            ...$extra,
        ]);
    }

    private function temPoligono(ViabilityRequest $request): bool
    {
        return is_array($request->address_polygon_geojson) && $request->address_polygon_geojson !== [];
    }

    private function ligada(FonteEdificacao $fonte): bool
    {
        return Settings::enabled("edificacoes_{$fonte->value}");
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function ponto(ViabilityRequest $request): array
    {
        $ponto = (array) $request->pontoDoImovel();

        return [(float) $ponto['lat'], (float) $ponto['lng']];
    }

    private function origemDoPonto(ViabilityRequest $request): ?string
    {
        if (is_array($request->property_polygon_geojson) && $request->property_polygon_geojson !== []) {
            return 'poligono';
        }

        return $request->property_location_source?->value;
    }

    private function desde(int $inicio): int
    {
        return (int) round((hrtime(true) - $inicio) / 1_000_000);
    }
}
