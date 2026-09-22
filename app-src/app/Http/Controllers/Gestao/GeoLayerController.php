<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ImportGeoLayerRequest;
use App\Models\GeoLayer;
use App\Services\Geo\GeoJsonLayerImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use JsonException;

/**
 * Gestão das camadas geográficas versionadas pela interface (HU-036,
 * parametrização 3.1): o admin importa um GeoJSON oficial pela retaguarda —
 * o MESMO GeoJsonLayerImporter do `geo:importar` abre a nova versão vigente
 * (a anterior é fechada, nunca apagada) e audita o diff da carga (RN-002/005).
 *
 * Guarda de driver honesta (anti-fachada): o importador é PostGIS-only
 * (ST_GeomFromGeoJSON/ST_MakeValid). Em conexão não-pgsql o store recusa com
 * flash.error claro e NÃO cria nada — nunca uma importação fingida.
 */
class GeoLayerController extends Controller
{
    /**
     * Camadas agrupadas pelos 5 tipos do enum: a vigente destacada e o
     * histórico completo de versões (feature_count/source/vigência). Leitura
     * pura de geo_layers — sem função espacial, roda em qualquer driver.
     */
    public function index(): Response
    {
        $camadasPorTipo = GeoLayer::query()
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (GeoLayer $camada): string => $camada->type->value);

        $grupos = collect(GeoLayerType::cases())->map(function (GeoLayerType $tipo) use ($camadasPorTipo): array {
            $versoes = $camadasPorTipo->get($tipo->value, collect());

            $mapVersao = fn (GeoLayer $camada): array => [
                'id' => $camada->id,
                'version' => $camada->version,
                'status' => $camada->status->value,
                'status_label' => $camada->status->label(),
                'source' => $camada->source,
                'valid_from' => $camada->valid_from?->toDateString(),
                'valid_to' => $camada->valid_to?->toDateString(),
                'feature_count' => $camada->feature_count,
            ];

            $vigente = $versoes->first(
                fn (GeoLayer $camada): bool => $camada->status === GeoLayerStatus::Vigente,
            );

            return [
                'tipo' => $tipo->value,
                'label' => $tipo->label(),
                'fonte_bloqueada' => $tipo->isBlockedSource(),
                'vigente' => $vigente !== null ? $mapVersao($vigente) : null,
                'versoes' => $versoes->map($mapVersao)->values()->all(),
            ];
        });

        return Inertia::render('gestao/territorio/camadas', [
            'grupos' => $grupos->values()->all(),
            'tipos' => collect(GeoLayerType::cases())
                ->map(fn (GeoLayerType $tipo): array => ['value' => $tipo->value, 'label' => $tipo->label()])
                ->all(),
            'uploadMaxMb' => (int) config('sile.geo.upload_max_mb', 20),
        ]);
    }

    public function store(ImportGeoLayerRequest $request, GeoJsonLayerImporter $importer): RedirectResponse
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return back()->with('error', 'A importação de camadas exige o banco PostGIS (produção/homologação).');
        }

        $validated = $request->validated();

        try {
            /** @var array<string, mixed> $featureCollection */
            $featureCollection = json_decode(
                (string) $validated['arquivo']->get(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            // Sem o path do temporário na mensagem — detalhe interno, não do admin.
            return back()->with('error', 'O arquivo enviado não é um JSON válido. Verifique o conteúdo e tente novamente.');
        }

        $tipo = GeoLayerType::from($validated['tipo']);
        $origem = $validated['origem'] !== '' ? $validated['origem'] : 'importacao-pela-gestao';

        try {
            $relatorio = $importer->import($tipo, $validated['versao'], $origem, $featureCollection);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', 'Falha na importação: '.$e->getMessage());
        }

        $mensagem = "Camada {$tipo->label()} importada: versão {$relatorio['version']}, "
            ."{$relatorio['lidas']} feições lidas, {$relatorio['inseridas']} inseridas.";

        if ($relatorio['invalidas'] !== []) {
            $motivos = implode('; ', array_slice($relatorio['invalidas'], 0, 5));
            $mensagem .= ' '.count($relatorio['invalidas'])." inválidas (não inseridas): {$motivos}.";
        }

        return back()->with('status', $mensagem);
    }
}
