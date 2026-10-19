<?php

namespace App\Console\Commands;

use App\Enums\FonteEdificacao;
use App\Models\BuildingFootprintImport;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use App\Services\Geo\Edificacoes\ImportadorEdificacoes;
use Generator;
use Illuminate\Console\Command;
use JsonException;
use SplFileObject;

class ImportarEdificacoesOvertureCommand extends Command
{
    protected $signature = 'geo:edificacoes:importar-overture
        {arquivo : GeoJSONSeq gerado por scripts/overture-salvador.sh}
        {--release= : Release da Overture (ex.: 2026-09-23.1)}';

    protected $description = 'Importa as edificações da Overture recortadas pelo limite de Salvador, idempotente por release e checksum';

    public function handle(ImportadorEdificacoes $importador, BuildingFootprintRepository $repositorio): int
    {
        $arquivo = (string) $this->argument('arquivo');
        $release = trim((string) $this->option('release'));

        if ($release === '') {
            $this->error('Informe a release da Overture via --release.');

            return self::FAILURE;
        }

        if (! is_file($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");

            return self::FAILURE;
        }

        if (! $repositorio->limiteDisponivel()) {
            $this->error('Importe o limite do município antes: geo:importar-wfs limite_municipio limite_salvador:VM_LIMITE_SALVADOR --versao=<versão> --ordenar=MUNICIPIOS');

            return self::FAILURE;
        }

        $importacao = $importador->importar(
            FonteEdificacao::Overture,
            $release,
            (string) hash_file('sha256', $arquivo),
            $this->linhas($arquivo),
            $this->mapear(...),
        );

        return $this->relatar($importacao);
    }

    /**
     * Uma feição por linha (GeoJSONSeq, com ou sem o separador RS do RFC 8142).
     *
     * @return Generator<array<string, mixed>>
     *
     * @throws JsonException
     */
    private function linhas(string $arquivo): Generator
    {
        $leitor = new SplFileObject($arquivo);

        while (! $leitor->eof()) {
            $linha = trim(ltrim((string) $leitor->fgets(), "\x1e"));

            if ($linha === '') {
                continue;
            }

            yield (array) json_decode($linha, true, 512, JSON_THROW_ON_ERROR);
        }
    }

    /**
     * @param  array<string, mixed>  $feicao
     * @return array{source_id: string, geojson: array<string, mixed>, confidence: float|null, source_datasets: list<array<string, mixed>>|null, properties: array<string, mixed>}|null
     */
    private function mapear(array $feicao): ?array
    {
        $propriedades = is_array($feicao['properties'] ?? null) ? $feicao['properties'] : [];
        $id = (string) ($propriedades['id'] ?? $feicao['id'] ?? '');
        $geometria = $feicao['geometry'] ?? null;

        if ($id === '' || ! is_array($geometria)) {
            return null;
        }

        $fontes = is_array($propriedades['sources'] ?? null) ? array_values($propriedades['sources']) : null;
        $confiancas = array_filter(
            array_map(fn (mixed $fonte): mixed => is_array($fonte) ? ($fonte['confidence'] ?? null) : null, $fontes ?? []),
            fn (mixed $valor): bool => is_numeric($valor),
        );

        return [
            'source_id' => $id,
            'geojson' => $geometria,
            'confidence' => $confiancas === [] ? null : (float) max($confiancas),
            'source_datasets' => $fontes,
            'properties' => array_filter([
                'height' => $propriedades['height'] ?? null,
                'num_floors' => $propriedades['num_floors'] ?? null,
                'class' => $propriedades['class'] ?? null,
                'subtype' => $propriedades['subtype'] ?? null,
            ], fn (mixed $valor): bool => $valor !== null),
        ];
    }

    private function relatar(BuildingFootprintImport $importacao): int
    {
        if ($importacao->status === BuildingFootprintImport::IGNORADA) {
            $this->info("Release {$importacao->release} já importada com o mesmo arquivo. Nada a fazer.");

            return self::SUCCESS;
        }

        if ($importacao->status === BuildingFootprintImport::FALHOU) {
            $this->error("Importação falhou e foi desfeita: {$importacao->error}");

            return self::FAILURE;
        }

        foreach ($importacao->counters as $nome => $valor) {
            $this->line(str_pad($nome, 16).": {$valor}");
        }

        return self::SUCCESS;
    }
}
