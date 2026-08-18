<?php

namespace App\Console\Commands;

use App\Enums\GeoLayerType;
use App\Services\Geo\GeoJsonLayerImporter;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

/**
 * Importa um GeoJSON oficial (FeatureCollection) para uma camada versionada
 * (HU-036). Idempotente por (type, version) via GeoJsonLayerImporter; audita o
 * diff da carga. Caminho operacional de reimportação — a carga de dev é feita
 * pelo GeoLayerSeeder a partir dos snapshots em database/data/geo/.
 */
class ImportGeoLayerCommand extends Command
{
    protected $signature = 'geo:importar {type : Tipo da camada (bairro, zona, via, lote, restricao)} {arquivo : Caminho do GeoJSON (FeatureCollection em SRID 4326)} {--versao= : Versão da camada (ex.: geosalvador-2024)} {--source= : Origem/URL da carga}';

    protected $description = 'Importa um GeoJSON oficial para uma camada geográfica versionada (HU-036), idempotente e auditado';

    public function handle(GeoJsonLayerImporter $importer): int
    {
        $typeArg = (string) $this->argument('type');
        $type = GeoLayerType::tryFrom($typeArg);

        if ($type === null) {
            $tipos = implode(', ', array_map(fn (GeoLayerType $t): string => $t->value, GeoLayerType::cases()));
            $this->error("Tipo de camada inválido: {$typeArg}. Use um de: {$tipos}.");

            return self::FAILURE;
        }

        $path = (string) $this->argument('arquivo');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $version = trim((string) $this->option('versao'));

        if ($version === '') {
            $this->error('Informe a versão da camada via --versao.');

            return self::FAILURE;
        }

        try {
            /** @var array<string, mixed> $featureCollection */
            $featureCollection = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error("JSON inválido em {$path}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $source = trim((string) $this->option('source')) ?: 'importacao-manual';

        try {
            $report = $importer->import($type, $version, $source, $featureCollection);
        } catch (InvalidArgumentException $e) {
            $this->error('Falha na importação: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Importação de camada concluída.');
        $this->line("Tipo: {$type->label()} ({$type->value})");
        $this->line("Versão: {$report['version']}");
        $this->line("Lidas: {$report['lidas']}");
        $this->line("Inseridas: {$report['inseridas']}");

        if ($report['invalidas'] !== []) {
            $this->newLine();
            $this->warn('Inválidas (não inseridas):');
            foreach ($report['invalidas'] as $motivo) {
                $this->line("  - {$motivo}");
            }
        }

        return self::SUCCESS;
    }
}
