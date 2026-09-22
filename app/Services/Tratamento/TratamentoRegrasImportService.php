<?php

namespace App\Services\Tratamento;

use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoPergunta;
use App\Models\TratamentoRegra;
use App\Models\TratamentoRegraRamo;
use RuntimeException;
use SplFileObject;

class TratamentoRegrasImportService
{
    /**
     * @return array{lidos: int, importados: int, atualizados: int, rejeitados: array<int, string>}
     */
    public function import(RuleVersion $version, string $dir): array
    {
        $rejected = [];
        $lidos = 0;
        $importados = 0;
        $atualizados = 0;

        $perguntas = $this->lerCsv($dir.'/perguntas.csv', ['numero', 'texto', 'referencia', 'observacao']);
        $lidos += $perguntas['lidos'];
        $rejected = [...$rejected, ...$perguntas['rejeitados']];
        $this->contarUpsert(
            TratamentoPergunta::class,
            $version,
            $this->mapPerguntas($version, $perguntas['rows']),
            ['rule_version_id', 'numero'],
            ['texto', 'referencia', 'observacao'],
            $importados,
            $atualizados,
        );

        $regras = $this->lerCsv($dir.'/regras.csv', ['numero', 'tratamento', 'referencia']);
        $lidos += $regras['lidos'];
        $rejected = [...$rejected, ...$regras['rejeitados']];
        $this->contarUpsert(
            TratamentoRegra::class,
            $version,
            $this->mapRegras($version, $regras['rows']),
            ['rule_version_id', 'numero'],
            ['tratamento', 'referencia'],
            $importados,
            $atualizados,
        );

        $enqs = $this->lerCsv($dir.'/cnae-enquadramentos.csv', [
            'cnae', 'denominacao', 'risco', 'regra_atualizacao', 'codigo_louos',
            'denominacao_louos', 'enquadramento_texto', 'enquadramento1', 'ate_m2_1',
            'enquadramento2', 'ate_m2_2', 'enquadramento3', 'acima_m2',
            'codigo_tll', 'especificacao_tll', 'classificacao',
        ]);
        $lidos += $enqs['lidos'];
        $rejected = [...$rejected, ...$enqs['rejeitados']];
        $this->contarUpsert(
            TratamentoEnquadramento::class,
            $version,
            $this->mapEnquadramentos($version, $enqs['rows']),
            ['rule_version_id', 'cnae', 'codigo_louos', 'subcategoria'],
            ['denominacao', 'risco', 'regra', 'denominacao_louos', 'grupo', 'ate_m2', 'enquadramento2', 'ate_m2_2', 'enquadramento3', 'acima_m2', 'codigo_tll', 'especificacao_tll', 'classificacao'],
            $importados,
            $atualizados,
        );

        $binds = $this->lerCsv($dir.'/cnae-perguntas-regras.csv', [
            'cnae', 'risco', 'codigo_louos', 'enquadramento1', 'perguntas', 'regras', 'condicionantes',
        ]);
        $lidos += $binds['lidos'];
        $rejected = [...$rejected, ...$binds['rejeitados']];
        $this->contarUpsert(
            TratamentoCnaeBinding::class,
            $version,
            $this->mapBindings($version, $binds['rows']),
            ['rule_version_id', 'cnae', 'regra', 'codigo_louos'],
            ['perguntas', 'condicionantes'],
            $importados,
            $atualizados,
        );

        // Ramos curados de fluxo (relatório SEDUR 21/09, itens 18/20/21/25):
        // o fluxo expresso/semiexpresso do ramo é dado extraído dos textos
        // oficiais das regras — arquivo opcional; sem ele, a versão fica sem
        // ramos e o resolver cai na heurística (degradação honesta).
        $fluxoPath = $dir.'/regras-fluxo.csv';

        if (is_file($fluxoPath)) {
            $ramos = $this->lerCsv($fluxoPath, ['regra', 'pergunta', 'resposta', 'faixa', 'tipo_dirige', 'codigo_louos', 'fluxo']);
            $lidos += $ramos['lidos'];
            $rejected = [...$rejected, ...$ramos['rejeitados']];
            $this->contarUpsert(
                TratamentoRegraRamo::class,
                $version,
                $this->mapRamos($version, $ramos['rows']),
                ['rule_version_id', 'chave'],
                ['fluxo'],
                $importados,
                $atualizados,
            );
        }

        return [
            'lidos' => $lidos,
            'importados' => $importados,
            'atualizados' => $atualizados,
            'rejeitados' => $rejected,
        ];
    }

    /**
     * @param  list<string>  $esperado
     * @return array{lidos: int, rows: list<array<string, string>>, rejeitados: list<string>}
     */
    private function lerCsv(string $path, array $esperado): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Arquivo ausente: {$path}");
        }

        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        $header = null;
        $rows = [];
        $rejected = [];
        $lidos = 0;

        foreach ($file as $line) {
            if ($line === false || $line === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($v) => trim((string) $v), $line);

                if ($header !== $esperado) {
                    throw new RuntimeException("Cabeçalho inesperado em {$path}");
                }

                continue;
            }

            $lidos++;

            if (count($line) < count($esperado)) {
                $rejected[] = sprintf('%s linha %d: colunas insuficientes', basename($path), $lidos);

                continue;
            }

            $line = array_slice($line, 0, count($esperado));
            $rows[] = array_combine(
                $esperado,
                array_map(fn ($v) => trim((string) $v), $line),
            );
        }

        return ['lidos' => $lidos, 'rows' => $rows, 'rejeitados' => $rejected];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapPerguntas(RuleVersion $version, array $rows): array
    {
        return array_map(fn (array $row): array => [
            'rule_version_id' => $version->getKey(),
            'numero' => (int) $row['numero'],
            'texto' => $row['texto'],
            'referencia' => $row['referencia'] !== '' ? $row['referencia'] : null,
            'observacao' => $row['observacao'] !== '' ? $row['observacao'] : null,
        ], $rows);
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapRegras(RuleVersion $version, array $rows): array
    {
        return array_map(fn (array $row): array => [
            'rule_version_id' => $version->getKey(),
            'numero' => (int) $row['numero'],
            'tratamento' => $row['tratamento'],
            'referencia' => $row['referencia'] !== '' ? $row['referencia'] : null,
        ], $rows);
    }

    /**
     * Ramos curados de fluxo: resposta SIM/NÃO → bool, faixa/tipo/código vazios
     * → null; a chave normalizada (NULLs como '-') faz o upsert idempotente.
     *
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapRamos(RuleVersion $version, array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $resposta = match (mb_strtoupper($row['resposta'])) {
                'SIM' => true,
                'NÃO', 'NAO' => false,
                default => null,
            };
            $pergunta = $row['pergunta'] !== '' ? (int) $row['pergunta'] : null;
            $faixa = $row['faixa'] !== '' ? $row['faixa'] : null;
            $tipoDirige = $row['tipo_dirige'] === '1' ? true : null;
            $codigo = $row['codigo_louos'] !== '' ? $row['codigo_louos'] : null;

            $chave = implode('|', [
                $row['regra'],
                $pergunta !== null ? (string) $pergunta : '-',
                $resposta !== null ? (string) (int) $resposta : '-',
                $faixa ?? '-',
                $tipoDirige !== null ? '1' : '-',
                $codigo ?? '-',
            ]);

            $out[] = [
                'rule_version_id' => $version->getKey(),
                'regra' => (int) $row['regra'],
                'pergunta' => $pergunta,
                'resposta' => $resposta,
                'faixa' => $faixa,
                'tipo_dirige' => $tipoDirige,
                'codigo_louos' => $codigo,
                'fluxo' => $row['fluxo'],
                'chave' => $chave,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapEnquadramentos(RuleVersion $version, array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $sub = $row['enquadramento1'] !== '' && $row['enquadramento1'] !== '-'
                ? $row['enquadramento1']
                : $row['enquadramento_texto'];

            $out[] = [
                'rule_version_id' => $version->getKey(),
                'cnae' => $row['cnae'],
                'denominacao' => $row['denominacao'] !== '' ? $row['denominacao'] : null,
                'risco' => $row['risco'],
                'regra' => $this->numeroRegra($row['regra_atualizacao']),
                'codigo_louos' => $row['codigo_louos'],
                'denominacao_louos' => $row['denominacao_louos'] !== '' ? $row['denominacao_louos'] : null,
                'subcategoria' => $sub,
                'grupo' => $this->grupoDe($sub),
                'ate_m2' => $this->area($row['ate_m2_1']),
                'enquadramento2' => $this->vazioParaNulo($row['enquadramento2']),
                'ate_m2_2' => $this->area($row['ate_m2_2']),
                'enquadramento3' => $this->vazioParaNulo($row['enquadramento3']),
                'acima_m2' => $this->area($row['acima_m2']),
                'codigo_tll' => $this->vazioParaNulo($row['codigo_tll']),
                'especificacao_tll' => $this->vazioParaNulo($row['especificacao_tll']),
                'classificacao' => $this->vazioParaNulo($row['classificacao']),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    /**
     * Uma linha de binding por (cnae, regra, codigo_louos) — a coluna `regras`
     * da planilha é pipe-separada ("26|27") e CADA regra vale (a resposta
     * decide qual): truncar com (int) perdia a segunda regra e o ramo curado
     * não casava (regra 27 do 4789-0/04, relatório SEDUR 21/09).
     *
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapBindings(RuleVersion $version, array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            foreach ($this->listaInt($row['regras']) as $regra) {
                $out[] = [
                    'rule_version_id' => $version->getKey(),
                    'cnae' => $row['cnae'],
                    'regra' => $regra,
                    'codigo_louos' => $row['codigo_louos'],
                    'perguntas' => json_encode($this->listaInt($row['perguntas']), JSON_UNESCAPED_UNICODE),
                    'condicionantes' => json_encode($this->listaInt($row['condicionantes']), JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return $out;
    }

    /**
     * @param  class-string  $model
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $unique
     * @param  list<string>  $update
     */
    private function contarUpsert(
        string $model,
        RuleVersion $version,
        array $rows,
        array $unique,
        array $update,
        int &$importados,
        int &$atualizados,
    ): void {
        if ($rows === []) {
            return;
        }

        $existentes = $model::query()
            ->where('rule_version_id', $version->getKey())
            ->count();

        foreach (array_chunk($rows, 400) as $chunk) {
            $model::query()->upsert($chunk, $unique, $update);
        }

        $total = $model::query()->where('rule_version_id', $version->getKey())->count();
        $novos = max(0, $total - $existentes);
        $importados += $novos;
        $atualizados += count($rows) - $novos;
    }

    private function numeroRegra(string $raw): ?int
    {
        if (preg_match('/(\d+)/', $raw, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    private function grupoDe(string $subcategoria): string
    {
        $pos = strrpos($subcategoria, '-');

        return $pos === false ? $subcategoria : substr($subcategoria, 0, $pos);
    }

    private function area(string $raw): ?string
    {
        $raw = str_replace(' ', '', trim($raw));

        if ($raw === '' || $raw === '-' || strcasecmp($raw, 'Q') === 0) {
            return null;
        }

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
        }

        $raw = str_replace(',', '.', $raw);

        if (! is_numeric($raw)) {
            return null;
        }

        return number_format((float) $raw, 2, '.', '');
    }

    private function vazioParaNulo(string $raw): ?string
    {
        $raw = trim($raw);

        return $raw === '' || $raw === '-' ? null : $raw;
    }

    /**
     * @return list<int>
     */
    private function listaInt(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $out = [];

        foreach (preg_split('/[|,;]/', $raw) ?: [] as $part) {
            $part = trim($part);

            if ($part !== '' && is_numeric($part)) {
                $out[] = (int) $part;
            }
        }

        return $out;
    }
}
