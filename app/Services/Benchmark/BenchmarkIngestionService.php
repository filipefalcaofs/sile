<?php

namespace App\Services\Benchmark;

use App\Models\BenchmarkActivityMetric;
use App\Models\BenchmarkIngestion;
use App\Models\BenchmarkMunicipalityMetric;
use App\Support\Audit\AuditService;
use App\Support\Capitais;
use App\Support\Metricas\Percentis;
use Illuminate\Support\Facades\DB;

/**
 * Ingestão de uma competência do benchmark federal.
 *
 * Baixa, agrega em memória e grava SÓ o resultado: os microdados são
 * descartados. Guardar o cru de 27 UFs por quase cem competências seria
 * inviável, e o agregado responde a tudo que as telas perguntam.
 *
 * Idempotente por (UF, competência): reprocessar substitui, nunca duplica.
 * Falha no download registra a competência como falha e não grava agregado
 * parcial — a tela precisa poder mostrar a lacuna.
 */
class BenchmarkIngestionService
{
    public function __construct(
        private readonly RedesimClient $client,
        private readonly RedesimSolicitacaoParser $parser,
        private readonly AuditService $audit,
    ) {}

    public function ingerir(string $uf, int $ano, int $mes): BenchmarkIngestion
    {
        $uf = strtoupper(trim($uf));

        $download = $this->client->baixar($uf, $ano, $mes);

        if (! $download->sucesso) {
            return $this->registrarFalha($uf, $ano, $mes, $download->url, (string) $download->motivo);
        }

        /** @var array<string, list<float>> $porMunicipio */
        $porMunicipio = [];
        /** @var array<string, list<float>> $cnaeSalvador */
        $cnaeSalvador = [];
        /** @var array<string, list<float>> $cnaeBrasil */
        $cnaeBrasil = [];

        $lidas = 0;

        foreach ($this->parser->linhas((string) $download->conteudo) as $linha) {
            $lidas++;

            $chave = $linha['uf'].'|'.$linha['municipio'];
            $porMunicipio[$chave][] = $linha['horas'];

            if ($linha['cnae'] === '') {
                continue;
            }

            $cnaeBrasil[$linha['cnae']][] = $linha['horas'];

            if ($this->ehSalvador($linha['uf'], $linha['municipio'])) {
                $cnaeSalvador[$linha['cnae']][] = $linha['horas'];
            }
        }

        $descartadas = $this->parser->descartadas();

        // Zero linhas lidas nunca é sucesso: arquivo ilegível ou sem estrutura
        // reconhecida gravado como sucesso fecha a competência sem dado e,
        // por ser "já importada", ninguém mais a refaz.
        if ($lidas === 0) {
            return $this->registrarFalha(
                $uf,
                $ano,
                $mes,
                $download->url,
                $this->parser->erro() ?? 'A planilha não trouxe nenhuma linha aproveitável.',
            );
        }

        return DB::transaction(function () use (
            $uf, $ano, $mes, $download, $porMunicipio, $cnaeSalvador, $cnaeBrasil, $lidas, $descartadas
        ): BenchmarkIngestion {
            $this->gravarMunicipios($porMunicipio, $ano, $mes);
            $this->gravarAtividades($cnaeSalvador, BenchmarkActivityMetric::ESCOPO_SALVADOR, $ano, $mes);
            $this->gravarAtividades($cnaeBrasil, BenchmarkActivityMetric::ESCOPO_BRASIL, $ano, $mes);

            $ingestao = BenchmarkIngestion::updateOrCreate(
                ['uf' => $uf, 'ano' => $ano, 'mes' => $mes],
                [
                    'status' => BenchmarkIngestion::STATUS_SUCESSO,
                    'url' => $download->url,
                    'bytes' => $download->bytes,
                    'hash' => $download->hash,
                    'linhas_lidas' => $lidas,
                    'linhas_descartadas' => $descartadas,
                    'motivo' => null,
                ],
            );

            $this->auditar($ingestao, 'sucesso');

            return $ingestao;
        });
    }

    private function ehSalvador(string $uf, string $municipio): bool
    {
        return $uf === Capitais::UF_SALVADOR && $municipio === Capitais::MUNICIPIO_SALVADOR;
    }

    /**
     * @param  array<string, list<float>>  $porMunicipio  chave "UF|MUNICIPIO"
     */
    private function gravarMunicipios(array $porMunicipio, int $ano, int $mes): void
    {
        foreach ($porMunicipio as $chave => $horas) {
            [$uf, $municipio] = explode('|', $chave, 2);

            $percentis = Percentis::de($horas);

            BenchmarkMunicipalityMetric::updateOrCreate(
                ['uf' => $uf, 'municipio' => $municipio, 'ano' => $ano, 'mes' => $mes],
                $percentis->toArray(),
            );
        }
    }

    /**
     * @param  array<string, list<float>>  $porCnae
     */
    private function gravarAtividades(array $porCnae, string $escopo, int $ano, int $mes): void
    {
        foreach ($porCnae as $cnae => $horas) {
            $percentis = Percentis::de($horas);

            BenchmarkActivityMetric::updateOrCreate(
                ['escopo' => $escopo, 'cnae' => $cnae, 'ano' => $ano, 'mes' => $mes],
                [
                    'n' => $percentis->n,
                    'media' => $percentis->media,
                    'p50' => $percentis->p50,
                    'p90' => $percentis->p90,
                ],
            );
        }
    }

    private function registrarFalha(string $uf, int $ano, int $mes, string $url, string $motivo): BenchmarkIngestion
    {
        $ingestao = BenchmarkIngestion::updateOrCreate(
            ['uf' => $uf, 'ano' => $ano, 'mes' => $mes],
            [
                'status' => BenchmarkIngestion::STATUS_FALHA,
                'url' => $url,
                'bytes' => 0,
                'hash' => null,
                'linhas_lidas' => 0,
                'linhas_descartadas' => 0,
                'motivo' => $motivo,
            ],
        );

        $this->auditar($ingestao, 'falha');

        return $ingestao;
    }

    private function auditar(BenchmarkIngestion $ingestao, string $resultado): void
    {
        $this->audit->log(
            logName: 'benchmark',
            event: 'ingestao',
            description: sprintf(
                'Importação do benchmark da REDESIM — %s %02d/%d',
                $ingestao->uf,
                $ingestao->mes,
                $ingestao->ano,
            ),
            properties: [
                'uf' => $ingestao->uf,
                'ano' => $ingestao->ano,
                'mes' => $ingestao->mes,
                'url' => $ingestao->url,
                'bytes' => $ingestao->bytes,
                'hash' => $ingestao->hash,
                'linhas_lidas' => $ingestao->linhas_lidas,
                'linhas_descartadas' => $ingestao->linhas_descartadas,
                'motivo' => $ingestao->motivo,
            ],
            subject: $ingestao,
            result: $resultado,
        );
    }
}
