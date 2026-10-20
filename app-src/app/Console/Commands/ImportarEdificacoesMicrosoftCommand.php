<?php

namespace App\Console\Commands;

use App\Enums\FonteEdificacao;
use App\Models\BuildingFootprintImport;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use App\Services\Geo\Edificacoes\ImportadorEdificacoes;
use App\Services\Geo\Edificacoes\Quadkey;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class ImportarEdificacoesMicrosoftCommand extends Command
{
    protected $signature = 'geo:edificacoes:importar-microsoft
        {--arquivo=* : Arquivos .csv.gz locais (sem acesso à internet)}
        {--release= : Data do refresh da Microsoft (padrão: hoje)}';

    protected $description = 'Importa as edificações da Microsoft de Salvador, descartando as que a Overture já tem';

    public function handle(ImportadorEdificacoes $importador, BuildingFootprintRepository $repositorio): int
    {
        if (! $repositorio->limiteDisponivel()) {
            $this->error('Importe o limite do município antes: geo:importar-wfs limite_municipio limite_salvador:VM_LIMITE_SALVADOR --versao=<versão> --ordenar=MUNICIPIOS');

            return self::FAILURE;
        }

        $release = trim((string) $this->option('release')) ?: now()->toDateString();
        $locais = array_values(array_filter((array) $this->option('arquivo')));

        try {
            [$fontes, $checksum] = $locais === [] ? $this->tilesRemotos() : $this->arquivosLocais($locais);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Arquivos: '.count($fontes));

        $importacao = $importador->importar(
            FonteEdificacao::Microsoft,
            $release,
            $checksum,
            $this->feicoes($fontes),
            $this->mapear(...),
        );

        if ($importacao->status === BuildingFootprintImport::IGNORADA) {
            $this->info("Release {$release} já importada com os mesmos arquivos. Nada a fazer.");

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

    /**
     * Tiles do Brasil cujo quadkey cruza o retângulo de Salvador.
     *
     * @return array{0: list<string>, 1: string}
     */
    private function tilesRemotos(): array
    {
        $resposta = Http::timeout(60)->get((string) config('viabiliza.edificacoes.microsoft.dataset_links_url'));

        if (! $resposta->successful()) {
            throw new RuntimeException('Não foi possível baixar a lista de arquivos da Microsoft (HTTP '.$resposta->status().').');
        }

        [$oeste, $sul, $leste, $norte] = config('viabiliza.edificacoes.bbox');
        $quadkeys = array_flip(Quadkey::cobrindo($oeste, $sul, $leste, $norte));
        $urls = [];
        $assinatura = [];

        foreach (preg_split('/\R/', trim($resposta->body())) ?: [] as $indice => $linha) {
            $colunas = str_getcsv($linha);

            if ($indice === 0 || count($colunas) < 4 || $colunas[0] !== 'Brazil' || ! isset($quadkeys[$colunas[1]])) {
                continue;
            }

            $urls[] = $colunas[2];
            $assinatura[] = $colunas[2].'|'.$colunas[3];
        }

        if ($urls === []) {
            throw new RuntimeException('Nenhum arquivo da Microsoft cobre Salvador na lista publicada.');
        }

        $baixados = array_map(fn (string $url): string => $this->baixar($url), $urls);

        return [$baixados, hash('sha256', implode("\n", $assinatura))];
    }

    private function baixar(string $url): string
    {
        $destino = (string) tempnam(sys_get_temp_dir(), 'ms-buildings');
        $resposta = Http::timeout(600)->sink($destino)->get($url);

        if (! $resposta->successful()) {
            throw new RuntimeException("Falha ao baixar {$url} (HTTP {$resposta->status()}).");
        }

        return $destino;
    }

    /**
     * @param  list<string>  $arquivos
     * @return array{0: list<string>, 1: string}
     */
    private function arquivosLocais(array $arquivos): array
    {
        foreach ($arquivos as $arquivo) {
            if (! is_file($arquivo)) {
                throw new RuntimeException("Arquivo não encontrado: {$arquivo}");
            }
        }

        return [$arquivos, hash('sha256', implode("\n", array_map(fn (string $arquivo): string => (string) hash_file('sha256', $arquivo), $arquivos)))];
    }

    /**
     * @param  list<string>  $arquivos
     * @return Generator<array<string, mixed>>
     *
     * @throws JsonException
     */
    private function feicoes(array $arquivos): Generator
    {
        foreach ($arquivos as $arquivo) {
            $leitor = gzopen($arquivo, 'rb');

            if ($leitor === false) {
                throw new RuntimeException("Não foi possível abrir {$arquivo}.");
            }

            try {
                while (($linha = gzgets($leitor)) !== false) {
                    $linha = trim($linha);

                    if ($linha !== '') {
                        yield (array) json_decode($linha, true, 512, JSON_THROW_ON_ERROR);
                    }
                }
            } finally {
                gzclose($leitor);
            }
        }
    }

    /**
     * Id estável pela geometria arredondada: o mesmo prédio em dois tiles ou
     * em dois refreshes vira a mesma linha.
     *
     * @param  array<string, mixed>  $feicao
     * @return array{source_id: string, geojson: array<string, mixed>, confidence: float|null, source_datasets: null, properties: array<string, mixed>}|null
     */
    private function mapear(array $feicao): ?array
    {
        $geometria = $feicao['geometry'] ?? null;

        if (! is_array($geometria) || ! is_array($geometria['coordinates'] ?? null)) {
            return null;
        }

        $propriedades = is_array($feicao['properties'] ?? null) ? $feicao['properties'] : [];
        $confianca = is_numeric($propriedades['confidence'] ?? null) ? (float) $propriedades['confidence'] : -1.0;
        $altura = is_numeric($propriedades['height'] ?? null) ? (float) $propriedades['height'] : -1.0;

        return [
            'source_id' => sha1((string) json_encode($this->arredondar($geometria['coordinates']))),
            'geojson' => $geometria,
            'confidence' => $confianca < 0 ? null : $confianca,
            'source_datasets' => null,
            'properties' => $altura < 0 ? [] : ['height' => $altura],
        ];
    }

    private function arredondar(mixed $coordenadas): mixed
    {
        return is_array($coordenadas)
            ? array_map(fn (mixed $item): mixed => $this->arredondar($item), $coordenadas)
            : round((float) $coordenadas, 7);
    }
}
