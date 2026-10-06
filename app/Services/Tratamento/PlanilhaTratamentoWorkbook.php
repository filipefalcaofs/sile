<?php

namespace App\Services\Tratamento;

use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

/**
 * Lê a planilha de regras no layout 20.08.26 (abas CNAES, perguntas, regras,
 * condicionantes, análise CNLU e textos gerais) e devolve o conteúdo já
 * normalizado para a carga versionada.
 */
class PlanilhaTratamentoWorkbook
{
    /**
     * @return array{
     *     perguntas: list<array<string, mixed>>,
     *     regras: list<array<string, mixed>>,
     *     enquadramentos: list<array<string, mixed>>,
     *     bindings: list<array<string, mixed>>,
     *     condicionantes: list<array<string, mixed>>,
     *     cnlu: list<array<string, mixed>>,
     *     notas: list<array<string, mixed>>,
     *     ramos: list<array<string, mixed>>,
     *     pendencias: list<array<string, mixed>>
     * }
     */
    public function ler(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        $abas = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $chave = $this->chaveAba($sheet->getName());

            if ($chave === null) {
                continue;
            }

            $linhas = [];

            foreach ($sheet->getRowIterator() as $row) {
                $linhas[] = $this->valores($row->getCells());
            }

            $abas[$chave] = $linhas;
        }

        $reader->close();

        foreach (['cnaes', 'perguntas', 'regras', 'condicionantes'] as $obrigatoria) {
            if (! isset($abas[$obrigatoria])) {
                throw new RuntimeException('A planilha não tem a aba esperada deste layout.');
            }
        }

        $regras = $this->regras($abas['regras']);
        $extraido = $this->ramos($regras);
        $cnaes = $this->enquadramentos($abas['cnaes']);
        $cnlu = (new PlanilhaCnluParser)->interpretar($abas['cnlu'] ?? []);

        return [
            'perguntas' => $this->perguntas($abas['perguntas']),
            'regras' => $regras,
            'enquadramentos' => $cnaes['linhas'],
            'bindings' => $cnaes['bindings'],
            'condicionantes' => $this->condicionantes($abas['condicionantes']),
            'cnlu' => $cnlu['itens'],
            'notas' => $this->notas($abas['geral'] ?? [], $abas['regra_geral'] ?? [], $cnlu['aguardando']),
            'ramos' => $extraido['ramos'],
            'pendencias' => $extraido['pendencias'],
        ];
    }

    /**
     * @param  list<array<int, string>>  $linhas
     * @return array{linhas: list<array<string, mixed>>, bindings: list<array<string, mixed>>}
     */
    private function enquadramentos(array $linhas): array
    {
        $headerIndex = null;
        $colunas = [];

        foreach ($linhas as $i => $linha) {
            foreach ($linha as $idx => $valor) {
                if (mb_strtolower(trim($valor)) === 'código cnae') {
                    $headerIndex = $i;
                    $colunas = $linha;
                    break 2;
                }
            }
        }

        if ($headerIndex === null) {
            throw new RuntimeException('A aba CNAES não tem o cabeçalho Código CNAE.');
        }

        /** @var array<string, list<int>> $mapa */
        $mapa = [];

        foreach ($colunas as $idx => $nome) {
            $titulo = trim($nome);

            if ($titulo === '') {
                continue;
            }

            $mapa[$titulo][] = $idx;

            $colapsado = (string) preg_replace('/\s+/u', ' ', $titulo);

            if ($colapsado !== $titulo) {
                $mapa[$colapsado][] = $idx;
            }
        }

        $out = [];
        $bindings = [];

        foreach (array_slice($linhas, $headerIndex + 1) as $linha) {
            $cnae = $this->celula($linha, $mapa, 'Código CNAE');

            if ($cnae === '') {
                continue;
            }

            $sub = $this->celula($linha, $mapa, 'Enquadramento1');

            if ($sub === '' || $sub === '-') {
                $sub = $this->celula($linha, $mapa, 'Enquadramento  (m² de ACC) e Subcategorias de Uso');
            }

            $perguntas = [];
            $regras = [];
            $conds = [];

            for ($n = 1; $n <= 32; $n++) {
                if ($this->marcado($this->celula($linha, $mapa, "P {$n}"))) {
                    $perguntas[] = $n;
                }
            }

            for ($n = 1; $n <= 60; $n++) {
                if ($this->marcado($this->celula($linha, $mapa, "R{$n}"))) {
                    $regras[] = $n;
                }
            }

            for ($n = 1; $n <= 32; $n++) {
                if ($this->marcado($this->celula($linha, $mapa, "COND.{$n}"))) {
                    $conds[] = $n;
                }
            }

            $codigo = $this->celula($linha, $mapa, 'Código LOUOS');

            $out[] = [
                'cnae' => $cnae,
                'denominacao' => $this->nulo($this->celula($linha, $mapa, 'Denominação CNAE')),
                'risco' => $this->celula($linha, $mapa, 'RISCO'),
                'regra' => $regras[0] ?? null,
                'codigo_louos' => $codigo,
                'denominacao_louos' => $this->nulo($this->celula($linha, $mapa, 'Denominação LOUOS')),
                'subcategoria' => $sub,
                'ate_m2' => $this->area($this->celula($linha, $mapa, 'Até m²')),
                'enquadramento2' => $this->nulo($this->celula($linha, $mapa, 'Enquadramento2')),
                'ate_m2_2' => $this->area($this->celula($linha, $mapa, 'Até m²', 1)),
                'enquadramento3' => $this->nulo($this->celula($linha, $mapa, 'Enquadramento3')),
                'acima_m2' => $this->area($this->celula($linha, $mapa, 'Acima m²')),
                'codigo_tll' => $this->nulo($this->celula($linha, $mapa, 'Código TLL')),
                'especificacao_tll' => $this->nulo($this->celula($linha, $mapa, 'Especificação TLL')),
                'classificacao' => $this->nulo($this->celula($linha, $mapa, 'Classificação')),
                'vagas' => $this->vagas($linha, $mapa),
                'metadados' => [
                    'subdivisao' => $this->nulo($this->celula($linha, $mapa, 'Subdivisão CNAE')),
                    'atualizado_em' => $this->nulo($this->celula($linha, $mapa, 'Atualizado em')),
                    'atualizacao_recente' => $this->nulo($this->celula($linha, $mapa, 'Atualização recentes')),
                ],
            ];

            foreach ($regras as $regra) {
                $bindings[] = [
                    'cnae' => $cnae,
                    'regra' => $regra,
                    'codigo_louos' => $codigo,
                    'perguntas' => $perguntas,
                    'condicionantes' => $conds,
                ];
            }
        }

        return ['linhas' => $out, 'bindings' => $bindings];
    }

    /**
     * Exigências de vagas da linha: as três colunas-resumo e as variantes 1 a 3
     * de cada uma (Aplica, análise e quantidade), como a planilha as traz.
     *
     * @param  array<int, string>  $linha
     * @param  array<string, list<int>>  $mapa
     * @return array<string, string|null>
     */
    private function vagas(array $linha, array $mapa): array
    {
        $vagas = [
            'carga' => $this->nulo($this->celula($linha, $mapa, 'Carga e Descarga')),
            'patio' => $this->nulo($this->celula($linha, $mapa, 'Pátio para carga e descarga (5% da ACC)')),
            'embarque' => $this->nulo($this->celula($linha, $mapa, 'Área para embarque e desembarque')),
        ];

        $grupos = [
            'carga' => ['Carga e Descarga', ['aplica', 'analise', 'quantidade']],
            'patio' => ['Pátio para carga e descarga (5% da ACC) ', ['aplica', 'analise', 'quantidade']],
            'embarque' => ['Área para embarque e desembarque', ['aplica', 'analise']],
        ];

        foreach ($grupos as $chave => [$rotulo, $campos]) {
            foreach ([1, 2, 3] as $n) {
                foreach ($campos as $campo) {
                    $titulo = $campo === 'aplica' ? 'Aplica' : $campo;
                    $vagas["{$chave}_{$n}_{$campo}"] = $this->nulo(
                        $this->celula($linha, $mapa, "{$rotulo}{$n} {$titulo}"),
                    );
                }
            }
        }

        return $vagas;
    }

    /**
     * @param  array<int, string>  $linha
     * @param  array<string, list<int>>  $mapa
     */
    private function celula(array $linha, array $mapa, string $nome, int $ocorrencia = 0): string
    {
        $idx = $mapa[$nome][$ocorrencia] ?? null;

        return $idx === null ? '' : trim($linha[$idx] ?? '');
    }

    /**
     * @param  list<array<int, string>>  $linhas
     * @return list<array<string, mixed>>
     */
    private function perguntas(array $linhas): array
    {
        $out = [];

        foreach ($linhas as $linha) {
            if (preg_match('/Pergunta\s+(\d+)/u', $linha[0] ?? '', $m) !== 1) {
                continue;
            }

            $out[] = [
                'numero' => (int) $m[1],
                'texto' => trim($linha[1] ?? ''),
                'referencia' => $this->nulo(trim($linha[2] ?? '')),
                'observacao' => $this->nulo(trim($linha[3] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<int, string>>  $linhas
     * @return list<array<string, mixed>>
     */
    private function regras(array $linhas): array
    {
        $out = [];

        foreach ($linhas as $linha) {
            if (preg_match('/Regra\s+(\d+)/u', $linha[0] ?? '', $m) !== 1) {
                continue;
            }

            $out[] = [
                'numero' => (int) $m[1],
                'tratamento' => trim($linha[1] ?? ''),
                'referencia' => $this->nulo(trim($linha[2] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<int, string>>  $linhas
     * @return list<array<string, mixed>>
     */
    private function condicionantes(array $linhas): array
    {
        $out = [];

        foreach ($linhas as $linha) {
            $numero = trim($linha[0] ?? '');

            if (! is_numeric(str_replace(',', '.', $numero))) {
                continue;
            }

            $out[] = [
                'numero' => (int) $numero,
                'texto' => trim($linha[1] ?? ''),
                'observacao' => $this->nulo(trim($linha[2] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<int, string>>  $geral
     * @param  list<array<int, string>>  $regraGeral
     * @param  list<string>  $cnluAguardando
     * @return list<array{chave: string, texto: string}>
     */
    private function notas(array $geral, array $regraGeral, array $cnluAguardando = []): array
    {
        $notas = [];
        $textoGeral = trim(implode("\n", array_map(
            fn (array $linha): string => trim(implode(' ', $linha)),
            $geral,
        )));

        if ($textoGeral !== '') {
            $notas[] = ['chave' => 'condicionante_geral', 'texto' => $textoGeral];
        }

        $textoRegra = trim(implode("\n", array_map(
            fn (array $linha): string => trim(implode(' ', $linha)),
            $regraGeral,
        )));

        if ($textoRegra !== '') {
            $notas[] = ['chave' => 'pergunta_regra_geral', 'texto' => $textoRegra];
        }

        if ($cnluAguardando !== []) {
            $notas[] = [
                'chave' => 'cnlu_aguardando_sedur',
                'texto' => 'Itens da aba Análise CNLU com ressalva "(subgrupo 2)", fora da lista do motor até a SEDUR confirmar o alcance: '
                    .implode(', ', $cnluAguardando).'.',
            ];
        }

        return $notas;
    }

    /**
     * @param  list<array<string, mixed>>  $regras
     * @return array{ramos: list<array<string, mixed>>, pendencias: list<array<string, mixed>>}
     */
    private function ramos(array $regras): array
    {
        $ramos = [];
        $pendencias = [];

        foreach ($regras as $regra) {
            $extraidos = $this->ramosDaRegra((int) $regra['numero'], (string) $regra['tratamento']);

            if ($extraidos === []) {
                $pendencias[] = [
                    'regra' => (int) $regra['numero'],
                    'motivo' => 'O texto não tem ramo explícito de sim ou não, faixa e enquadramento. O motor usa a heurística desta versão.',
                ];

                continue;
            }

            array_push($ramos, ...$extraidos);
        }

        return ['ramos' => $ramos, 'pendencias' => $pendencias];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ramosDaRegra(int $numero, string $texto): array
    {
        $fluxo = null;
        $out = [];

        foreach (preg_split('/\R/u', $texto) ?: [] as $bruta) {
            $linha = trim($bruta);

            if ($linha === '') {
                continue;
            }

            if (preg_match('/fluxo\s+semi/iu', $linha) === 1) {
                $fluxo = 'semiexpresso';
            } elseif (preg_match('/fluxo\s+expresso/iu', $linha) === 1) {
                $fluxo = 'expresso';
            }

            if ($fluxo === null) {
                continue;
            }

            $bloqueia = preg_match('/n[aã]o permitir o prosseguimento/iu', $linha) === 1;
            $temPergunta = preg_match('/pergunta\s+(\d+)/iu', $linha, $pergunta) === 1;
            $temEnquadramento = preg_match('/\d{2}\.\d{2}\.\d{2}/', $linha) === 1;

            if (! $temPergunta && ! $bloqueia && ! $temEnquadramento) {
                continue;
            }

            $resposta = null;

            if (preg_match('/\bN[AÃ]O\b/u', $linha) === 1 && preg_match('/\bSIM\b/u', $linha) !== 1) {
                $resposta = false;
            } elseif (preg_match('/\bSIM\b/u', $linha) === 1 && preg_match('/\bN[AÃ]O\b/u', $linha) !== 1) {
                $resposta = true;
            }

            $faixa = null;

            if (preg_match('/1[\.\s]?250/u', $linha) === 1) {
                $faixa = preg_match('/acima|maior|superior/iu', $linha) === 1 ? 'acima_1250' : 'ate_1250';
            }

            $tipo = preg_match('/galp[aã]o|container|edifica[cç][aã]o residencial/iu', $linha) === 1 ? true : null;
            $codigo = null;

            if (preg_match('/(\d{2}\.\d{2}\.\d{2})/', $linha, $codigoMatch) === 1) {
                $codigo = $codigoMatch[1];
            }

            $out[] = [
                'regra' => $numero,
                'pergunta' => $temPergunta ? (int) $pergunta[1] : null,
                'resposta' => $resposta,
                'faixa' => $faixa,
                'tipo_dirige' => $bloqueia ? true : $tipo,
                'codigo_louos' => $codigo,
                'fluxo' => $fluxo,
                'bloqueia' => $bloqueia,
            ];
        }

        return $out;
    }

    private function chaveAba(string $nome): ?string
    {
        $nome = mb_strtolower(trim($nome));

        return match (true) {
            $nome === 'cnaes' => 'cnaes',
            str_contains($nome, 'perguntas_ atualizadas') || str_contains($nome, 'perguntas_atualizadas') => 'perguntas',
            str_contains($nome, 'regras de tratamento') => 'regras',
            str_starts_with($nome, 'condicionantes') && ! str_contains($nome, 'gerais') => 'condicionantes',
            str_contains($nome, 'cnlu') => 'cnlu',
            str_contains($nome, 'regra geral') => 'regra_geral',
            str_contains($nome, 'condicionantes gerais') => 'geral',
            default => null,
        };
    }

    /**
     * @param  array<int, Cell>  $cells
     * @return array<int, string>
     */
    private function valores(array $cells): array
    {
        $out = [];

        foreach ($cells as $index => $cell) {
            $valor = $cell->getValue();
            $out[$index] = $valor instanceof DateTimeInterface
                ? $valor->format('Y-m-d')
                : trim((string) $valor);
        }

        return $out;
    }

    private function marcado(string $valor): bool
    {
        $valor = mb_strtolower(trim($valor));

        return in_array($valor, ['x', 'sim', '1', '1.0'], true);
    }

    private function nulo(string $valor): ?string
    {
        $valor = trim($valor);

        return $valor === '' || $valor === '-' ? null : $valor;
    }

    private function area(string $raw): ?string
    {
        $raw = str_replace(' ', '', trim($raw));

        if ($raw === '' || $raw === '-' || strcasecmp($raw, 'Q') === 0 || ! preg_match('/\d/', $raw)) {
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
}
