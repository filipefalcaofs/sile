<?php

namespace App\Console\Commands;

use App\Enums\GeoLayerType;
use App\Services\Geo\GeoJsonLayerImporter;
use App\Services\Geo\GeoServerWfsPaginador;
use App\Services\Geo\WfsIndisponivelException;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Importa uma FeatureType inteira do GeoServer SEDUR como nova versão de uma
 * camada local (ex.: logradouros de Salvador em `logradouro:VM_LOGRADOURO`).
 * Roda no host com rota ao GeoServer; falha em qualquer página não publica
 * versão parcial.
 */
class ImportarCamadaWfsCommand extends Command
{
    protected $signature = 'geo:importar-wfs
        {type : Tipo da camada local (bairro, zona, via, lote, restricao)}
        {typeName : FeatureType do GeoServer SEDUR (ex.: logradouro:VM_LOGRADOURO)}
        {--versao= : Versão da camada (ex.: sedur-logradouros-2026-09)}
        {--ordenar=CODLOG : Atributo estável para ordenar a paginação}
        {--por-pagina=2000 : Feições por página}';

    protected $description = 'Importa uma camada do GeoServer SEDUR (WFS paginado) como nova versão local, auditada';

    public function handle(GeoServerWfsPaginador $paginador, GeoJsonLayerImporter $importer): int
    {
        $type = GeoLayerType::tryFrom((string) $this->argument('type'));

        if ($type === null) {
            $this->error('Tipo de camada inválido: '.$this->argument('type'));

            return self::FAILURE;
        }

        $version = trim((string) $this->option('versao'));

        if ($version === '') {
            $this->error('Informe a versão da camada via --versao.');

            return self::FAILURE;
        }

        $typeName = (string) $this->argument('typeName');

        try {
            $colecao = $paginador->baixar($typeName, (string) $this->option('ordenar'), max(1, (int) $this->option('por-pagina')));
        } catch (WfsIndisponivelException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Feições baixadas: '.count($colecao['features']));

        try {
            $report = $importer->import($type, $version, "geoserver-sedur:{$typeName}", $colecao);
        } catch (InvalidArgumentException $e) {
            $this->error('Falha na importação: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Importação concluída.');
        $this->line("Versão: {$report['version']}");
        $this->line("Inseridas: {$report['inseridas']}");

        if ($report['invalidas'] !== []) {
            $this->warn('Inválidas (não inseridas): '.count($report['invalidas']));
        }

        return self::SUCCESS;
    }
}
