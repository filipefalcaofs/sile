<?php

namespace App\Services\Benchmark;

use Generator;
use Illuminate\Support\Str;
use OpenSpout\Reader\RowIteratorInterface;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Microdados da REDESIM, linha a linha.
 *
 * Lê em streaming porque uma única competência de São Paulo passa de 16 MB, e
 * o backfill percorre 27 UFs por quase cem competências — carregar a planilha
 * inteira em memória não é opção.
 *
 * Estrutura do arquivo, medida em 2026-10-03: aba `dados`, linha 1 com o
 * agrupamento e linha 2 com os nomes dos campos. As colunas são casadas pelo
 * cabeçalho NORMALIZADO, nunca por posição — a ordem muda entre competências,
 * e dois nomes trazem espaço duplo (`QTDE.  HH VIABILIDADE END` e
 * `ATIVIDADE  ECONÔMICA PRINCIPAL`).
 *
 * A coluna de interesse é a de horas úteis da viabilidade de ENDEREÇO: é a
 * parcela que cabe ao município e a única comparável ao que o VIABILIZA mede.
 */
class RedesimSolicitacaoParser
{
    private const COLUNA_MUNICIPIO = 'MUNICIPIO';

    private const COLUNA_UF = 'UF';

    // Nomes já normalizados: sem acento, sem pontuação e com espaços
    // colapsados. No arquivo original são `QTDE.  HH VIABILIDADE END` e
    // `ATIVIDADE  ECONÔMICA PRINCIPAL`, ambos com espaço duplo.
    private const COLUNA_HORAS = 'QTDE HH VIABILIDADE END';

    private const COLUNA_CNAE = 'ATIVIDADE ECONOMICA PRINCIPAL';

    private int $descartadas = 0;

    private ?string $erro = null;

    /**
     * Linhas aproveitáveis da competência. Linha sem valor numérico de
     * viabilidade é descartada e contabilizada — o arquivo oficial traz muitas
     * delas, e omiti-las em silêncio distorceria a média.
     *
     * @return Generator<int, array{municipio: string, uf: string, horas: float, cnae: string}>
     */
    public function linhas(string $conteudo): Generator
    {
        $this->descartadas = 0;
        $this->erro = null;

        $arquivo = tempnam(sys_get_temp_dir(), 'redesim_');

        if ($arquivo === false) {
            $this->erro = 'Não foi possível criar o arquivo temporário em '.sys_get_temp_dir().'.';

            return;
        }

        file_put_contents($arquivo, $conteudo);

        $reader = new Reader;

        try {
            $reader->open($arquivo);

            foreach ($reader->getSheetIterator() as $sheet) {
                if ($this->normalizar($sheet->getName()) !== 'DADOS') {
                    continue;
                }

                yield from $this->linhasDaAba($sheet);

                break;
            }
        } catch (Throwable $e) {
            // Arquivo ilegível devolve zero linhas e guarda o motivo; a
            // ingestão registra a competência como falha em vez de gravar
            // agregado parcial.
            $this->erro = 'Planilha ilegível: '.$e->getMessage();

            return;
        } finally {
            $reader->close();
            @unlink($arquivo);
        }
    }

    public function descartadas(): int
    {
        return $this->descartadas;
    }

    /**
     * Motivo pelo qual a última leitura não pôde ser feita, quando houve.
     */
    public function erro(): ?string
    {
        return $this->erro;
    }

    /**
     * @param  SheetInterface<RowIteratorInterface>  $sheet
     * @return Generator<int, array{municipio: string, uf: string, horas: float, cnae: string}>
     */
    private function linhasDaAba($sheet): Generator
    {
        $colunas = null;

        foreach ($sheet->getRowIterator() as $numero => $row) {
            $celulas = $row->toArray();

            // Linha 1 é agrupamento; o cabeçalho útil está na linha 2.
            if ($numero === 1) {
                continue;
            }

            if ($colunas === null) {
                $colunas = $this->mapearColunas($celulas);

                if ($colunas === []) {
                    return;
                }

                continue;
            }

            $linha = $this->linhaDe($celulas, $colunas);

            if ($linha === null) {
                $this->descartadas++;

                continue;
            }

            yield $linha;
        }
    }

    /**
     * @param  list<mixed>  $cabecalho
     * @return array<string, int>
     */
    private function mapearColunas(array $cabecalho): array
    {
        $mapa = [];

        foreach ($cabecalho as $indice => $nome) {
            if ($nome === null || $nome === '') {
                continue;
            }

            $mapa[$this->normalizar((string) $nome)] = $indice;
        }

        $obrigatorias = [self::COLUNA_MUNICIPIO, self::COLUNA_UF, self::COLUNA_HORAS];

        foreach ($obrigatorias as $coluna) {
            if (! array_key_exists($coluna, $mapa)) {
                return [];
            }
        }

        return $mapa;
    }

    /**
     * @param  list<mixed>  $celulas
     * @param  array<string, int>  $colunas
     * @return array{municipio: string, uf: string, horas: float, cnae: string}|null
     */
    private function linhaDe(array $celulas, array $colunas): ?array
    {
        $horas = $celulas[$colunas[self::COLUNA_HORAS]] ?? null;
        $municipio = $celulas[$colunas[self::COLUNA_MUNICIPIO]] ?? null;

        if (! is_numeric($horas) || $municipio === null || trim((string) $municipio) === '') {
            return null;
        }

        $cnaeIndice = $colunas[self::COLUNA_CNAE] ?? null;

        return [
            'municipio' => $this->normalizar((string) $municipio),
            'uf' => strtoupper(trim((string) ($celulas[$colunas[self::COLUNA_UF]] ?? ''))),
            'horas' => (float) $horas,
            'cnae' => $cnaeIndice === null ? '' : trim((string) ($celulas[$cnaeIndice] ?? '')),
        ];
    }

    /**
     * Maiúsculas, sem acento, sem pontuação e com espaços colapsados. É o que
     * torna o casamento de colunas imune à ordem, ao espaço duplo e à
     * variação de acentuação entre competências.
     */
    private function normalizar(string $valor): string
    {
        // Str::ascii em vez de iconv//TRANSLIT: o TRANSLIT depende da libc e,
        // no macOS, transforma "Í" em "'I" — o que quebrava o casamento da
        // coluna MUNICÍPIO.
        $semAcento = Str::ascii($valor);

        $limpo = preg_replace('/[^A-Za-z0-9 ]/', ' ', $semAcento) ?? $semAcento;
        $colapsado = preg_replace('/\s+/', ' ', $limpo) ?? $limpo;

        return strtoupper(trim($colapsado));
    }
}
