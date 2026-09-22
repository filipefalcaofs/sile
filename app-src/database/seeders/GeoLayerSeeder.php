<?php

namespace Database\Seeders;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Services\Geo\GeoJsonLayerImporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Carga de desenvolvimento das camadas geográficas (HU-034/HU-035/HU-032 +
 * HU-036). Driver-aware: em PostgreSQL carrega os snapshots OFICIAIS REAIS do
 * GeoSalvador commitados em database/data/geo/ (bairro/via/restrição); em
 * SQLite (suíte) só registra as camadas pendentes (PostGIS não existe — a
 * carga real é exercida pelos testes @group postgis).
 *
 * Zona urbanística (Quadro 10 LOUOS) e lote cadastral por inscrição NÃO têm
 * fonte vetorial pública confirmada: ficam pendente_fonte (feature_count 0),
 * comunicando o bloqueio na UI sem inventar polígono (regra de entrega
 * funcional). A carga lê o arquivo COMMITADO, nunca a rede (reprodutível/CI).
 */
class GeoLayerSeeder extends Seeder
{
    /**
     * Camadas com fonte pública real: arquivo, versão e origem do snapshot.
     *
     * @var list<array{type: GeoLayerType, file: string, version: string, source: string}>
     */
    private const REAL_LAYERS = [
        [
            'type' => GeoLayerType::Bairro,
            'file' => 'bairros.geojson',
            'version' => 'geosalvador-bairros-dec38776-2024',
            'source' => 'https://geo.salvador.ba.gov.br/arcgis/rest/services/Bairros/MapServer/0',
        ],
        [
            'type' => GeoLayerType::Restricao,
            'file' => 'restricoes-ambientais.geojson',
            'version' => 'geosalvador-pddu2016-zeis',
            'source' => 'https://geo.salvador.ba.gov.br/arcgis/rest/services/PDDU_2016/MapServer/76',
        ],
        [
            'type' => GeoLayerType::Via,
            'file' => 'vias.geojson',
            'version' => 'geosalvador-logradouros-centro',
            'source' => 'https://geo.salvador.ba.gov.br/arcgis/rest/services/Logradouros/MapServer/0',
        ],
    ];

    public function run(): void
    {
        // Zona/lote (sem fonte pública) são sempre comunicadas como pendentes —
        // não dependem de PostGIS (sem geometria), valem em qualquer driver.
        $this->seedPendingBlockedLayers();

        if (DB::getDriverName() !== 'pgsql') {
            // SQLite: PostGIS não existe; a carga real roda nos testes @group postgis.
            return;
        }

        $importer = app(GeoJsonLayerImporter::class);

        foreach (self::REAL_LAYERS as $layer) {
            $path = database_path('data/geo/'.$layer['file']);

            if (! is_file($path)) {
                // Snapshot ausente: degradação comunicada (camada pendente_fonte).
                // Bairro é requisito rígido — o teste @group postgis falha sem ele.
                $this->seedPendingLayer($layer['type'], 'snapshot-ausente');

                continue;
            }

            try {
                /** @var array<string, mixed> $featureCollection */
                $featureCollection = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->seedPendingLayer($layer['type'], 'snapshot-invalido');

                continue;
            }

            $importer->import($layer['type'], $layer['version'], $layer['source'], $featureCollection);
        }
    }

    /**
     * Camadas sem fonte pública (zona/lote): registradas e comunicadas, sem
     * features — nunca simuladas (HU-031/HU-033).
     */
    private function seedPendingBlockedLayers(): void
    {
        foreach (GeoLayerType::cases() as $type) {
            if ($type->isBlockedSource()) {
                $this->seedPendingLayer($type, 'pendente-sedur');
            }
        }
    }

    private function seedPendingLayer(GeoLayerType $type, string $version): void
    {
        GeoLayer::query()->firstOrCreate(
            ['type' => $type, 'version' => $version],
            [
                'status' => GeoLayerStatus::PendenteFonte,
                'valid_from' => null,
                'valid_to' => null,
                'source' => 'pendente-sedur',
                'rules_version' => $version,
                'feature_count' => 0,
            ],
        );
    }
}
