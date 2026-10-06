<?php

namespace App\Services\Relatorios\Export;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\HeaderFooter;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pasta de trabalho no padrão do Report Design System: CAPA (identificação e
 * índice), DADOS (linha a linha, AutoFilter, painéis congelados, totais com
 * SUBTOTAL), DICIONÁRIO (uma linha por coluna) e PARÂMETROS (filtros e
 * metadados da geração).
 *
 * Driver XLSX do contrato de exportação (HU-131) — openspout v4 por STREAMING de
 * baixa memória: itera o Builder filtrado da tela com ->cursor() (RN-005, nunca
 * all() em memória) e escreve linha a linha.
 *
 * Pitfall 4: o XLSX é um ZIP e exige um arquivo de filesystem gravável (não um
 * php://output puro). Por isso o assíncrono grava direto no caminho real do Job
 * (openToFile) e o síncrono gera o XLSX num arquivo temporário e o streama ao
 * cliente com fpassthru — evitando o openToBrowser do openspout, que chama
 * header()/ob_end_clean() por fora e conflita com o ciclo da StreamedResponse do
 * Symfony (e impossibilitaria asserir o Content-Type na resposta).
 */
final class XlsxExporter implements ReportFormatExporter
{
    private const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function stream(ReportDefinition $definition): Response
    {
        $temporario = tempnam(sys_get_temp_dir(), 'xlsx');
        $this->escrever($definition, $temporario);

        return response()->streamDownload(function () use ($temporario): void {
            $handle = fopen($temporario, 'rb');
            fpassthru($handle);
            fclose($handle);
            @unlink($temporario);
        }, $definition->fileName('xlsx'), ['Content-Type' => self::CONTENT_TYPE]);
    }

    public function write(ReportDefinition $definition, string $absolutePath): void
    {
        $this->escrever($definition, $absolutePath);
    }

    private const BRAND = '1351B4';

    private const INK = '1D2939';

    private const MUTED = '667085';

    private const HEAD_BG = 'F2F4F7';

    private const HAIRLINE = 'E4E7EC';

    /**
     * Streaming (RN-005, baixa memória via cursor()) — o mesmo loop serve ao
     * síncrono e ao assíncrono. A CAPA precisa do total antes dos dados; o
     * DICIONÁRIO precisa do tipo inferido durante o streaming, por isso nasce
     * depois da aba DADOS.
     */
    private function escrever(ReportDefinition $definition, string $absolutePath): void
    {
        $colunas = $definition->columnLabels();
        $n = count($colunas);
        $gerado = now();
        $max = $definition->maxRows;
        $total = $definition->builder()->count();
        $registros = $max !== null ? min($total, $max) : $total;

        $options = new Options;
        $options->SHOULD_USE_INLINE_STRINGS = true;
        $options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, 0, 1));
        $options->setHeaderFooter(new HeaderFooter(
            oddHeader: $this->cabecalhoRodape('&LVIABILIZA · SEDUR&C'.$this->limpa($definition->titulo).'&RPágina &P de &N'),
            oddFooter: $this->cabecalhoRodape('&LGerado em '.$gerado->format('d/m/Y H:i').'&R'.$this->limpa($definition->titulo)),
        ));

        $writer = new Writer($options);
        $writer->openToFile($absolutePath);

        $this->capa($writer, $definition, $gerado, $registros);

        $dados = $writer->addNewSheetAndMakeItCurrent();
        $dados->setName('DADOS');
        foreach (range(1, $n) as $coluna) {
            $dados->setColumnWidth((float) max(14, min(38, mb_strlen($colunas[$coluna - 1]) + 6)), $coluna);
        }
        $dados->setSheetView((new SheetView)->setFreezeRow(6)->setFreezeColumn('B'));
        $dados->setPrintTitleRows('$5:$5');

        $this->faixaTitulo($writer, $definition->titulo.' · Dados', $definition, $gerado, $n);
        $writer->addRow(Row::fromValues(
            ["{$registros} registros · filtros no cabeçalho · linha 5 e coluna A congeladas"],
            (new Style)->setFontSize(10)->setFontColor(self::MUTED),
        ));

        $cabecalho = $this->estiloCabecalho();
        $writer->addRow(Row::fromValues($colunas, $cabecalho));

        $texto = $this->estiloCorpo(0);
        $decimal = $this->estiloCorpo(0)->setFormat('#,##0.00');
        $tipos = array_fill(0, $n, 'vazio');
        $emitidas = 0;

        foreach ($definition->builder()->cursor() as $model) {
            if ($max !== null && $emitidas >= $max) {
                break;
            }

            $linha = array_values($definition->mapRow($model));
            foreach ($linha as $i => $valor) {
                $tipos[$i] = $this->tipo($valor, $tipos[$i] ?? 'vazio');
            }

            $writer->addRow(new Row(array_map(
                fn (mixed $valor): Cell => Cell::fromValue($valor, is_float($valor) ? $decimal : $texto),
                $linha,
            )));
            $emitidas++;
        }

        if ($emitidas > 0) {
            $ultima = 5 + $emitidas;
            $writer->addRow(new Row([
                new FormulaCell('="Total (filtrado): "&SUBTOTAL(103,A6:A'.$ultima.')', (new Style)->setFontBold()->setBorder(new Border(new BorderPart(Border::TOP, self::INK, Border::WIDTH_MEDIUM))), "Total (filtrado): {$emitidas}"),
            ]));
        }

        $dados->setAutoFilter(new AutoFilter(0, 5, max(0, $n - 1), 5 + max(1, $emitidas)));

        $this->dicionario($writer, $definition, $gerado, $tipos);
        $this->parametros($writer, $definition, $gerado, $emitidas);

        $writer->close();
    }

    private function capa(Writer $writer, ReportDefinition $definition, mixed $gerado, int $registros): void
    {
        $capa = $writer->getCurrentSheet();
        $capa->setName('CAPA');
        $capa->setColumnWidth(3, 1);
        $capa->setColumnWidth(26, 2);
        $capa->setColumnWidth(60, 3);
        $capa->setSheetView((new SheetView)->setShowGridLines(false));

        $writer->addRow(Row::fromValues([''], (new Style)->setBackgroundColor(self::BRAND)));
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(['', 'VIABILIZA'], (new Style)->setFontBold()->setFontSize(22)->setFontColor('0C326F')));
        $writer->addRow(Row::fromValues(['', 'A viabilidade certa, no lugar certo · SEDUR · Prefeitura Municipal de Salvador'], (new Style)->setFontSize(10)->setFontColor(self::MUTED)));
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(['', 'RELATÓRIO OPERACIONAL'], (new Style)->setFontBold()->setFontSize(9)->setFontColor(self::BRAND)));
        $writer->addRow(Row::fromValues(['', $definition->titulo], (new Style)->setFontBold()->setFontSize(22)->setFontColor('101828')));
        $writer->addRow(Row::fromValues(['']));

        $rotulo = (new Style)->setFontBold()->setFontSize(9)->setFontColor(self::MUTED);
        $valor = (new Style)->setFontBold()->setFontSize(11)->setFontColor(self::INK);
        $responsavel = auth()->user()?->name ?? '—';

        foreach ([
            ['GERADO EM', $gerado->format('d/m/Y H:i')],
            ['RESPONSÁVEL', $responsavel],
            ['REGISTROS (DADOS)', $registros],
        ] as [$k, $v]) {
            $writer->addRow(new Row([
                Cell::fromValue(''),
                Cell::fromValue($k, $rotulo),
                Cell::fromValue($v, $valor->setCellAlignment(CellAlignment::LEFT)),
            ]));
        }

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(['', 'CONTEÚDO'], $this->estiloCabecalho()));
        foreach ([
            ['DADOS', 'Registros linha a linha, com filtros'],
            ['DICIONÁRIO', 'Significado e formato de cada coluna'],
            ['PARÂMETROS', 'Filtros e metadados da geração'],
        ] as [$aba, $descricao]) {
            $writer->addRow(Row::fromValues(['', $aba, $descricao], (new Style)->setFontSize(11)->setFontColor(self::INK)));
        }

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(
            ['', $definition->personalData
                ? 'Conjunto exportado = conjunto filtrado na tela (RN-005). Pode conter dados pessoais (LGPD): uso interno.'
                : 'Conjunto exportado = conjunto filtrado na tela (RN-005).'],
            (new Style)->setFontSize(9)->setFontColor(self::MUTED),
        ));
    }

    /**
     * @param  list<string>  $tipos
     */
    private function dicionario(Writer $writer, ReportDefinition $definition, mixed $gerado, array $tipos): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('DICIONÁRIO');
        foreach ([6, 28, 28, 14, 20] as $i => $largura) {
            $sheet->setColumnWidth((float) $largura, $i + 1);
        }
        $sheet->setSheetView((new SheetView)->setFreezeRow(6));

        $this->faixaTitulo($writer, 'Dicionário de dados', $definition, $gerado, 5);
        $writer->addRow(Row::fromValues(['Col.', 'Coluna', 'Campo técnico', 'Tipo', 'Formato'], $this->estiloCabecalho()));

        foreach ($definition->colunas as $i => $coluna) {
            $tipo = $tipos[$i] ?? 'vazio';
            $writer->addRow(Row::fromValues([
                $this->letra($i),
                $coluna['label'],
                $coluna['key'],
                $this->rotuloTipo($tipo),
                $this->formato($tipo),
            ], $this->estiloCorpo($i)));
        }
    }

    private function parametros(Writer $writer, ReportDefinition $definition, mixed $gerado, int $registros): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('PARÂMETROS');
        foreach ([26, 44, 36] as $i => $largura) {
            $sheet->setColumnWidth((float) $largura, $i + 1);
        }

        $this->faixaTitulo($writer, 'Parâmetros da geração', $definition, $gerado, 3);
        $writer->addRow(Row::fromValues(['Parâmetro', 'Valor', 'Observação'], $this->estiloCabecalho()));

        $linhas = [];
        foreach ($definition->filtrosAplicados as $chave => $valor) {
            $linhas[] = [(string) $chave, (string) $valor, 'Filtro da tela'];
        }
        if ($definition->filtrosAplicados === []) {
            $linhas[] = ['Filtros', 'Todos (sem filtro)', 'Filtro da tela'];
        }
        $linhas[] = ['Usuário', auth()->user()?->name ?? '—', 'Quem gerou'];
        $linhas[] = ['Gerado em', $gerado->format('d/m/Y H:i:s'), (string) config('app.timezone')];
        $linhas[] = ['Relatório', $definition->titulo, 'Contrato único de exportação'];
        $linhas[] = ['Registros', (string) $registros, 'Total do recorte exportado'];

        foreach ($linhas as $i => $linha) {
            $writer->addRow(Row::fromValues($linha, $this->estiloCorpo($i)));
        }
    }

    /**
     * Linhas 1–4: faixa brand-500, título, metadados e linha em branco.
     */
    private function faixaTitulo(Writer $writer, string $titulo, ReportDefinition $definition, mixed $gerado, int $n): void
    {
        $writer->addRow(new Row(array_fill(0, max(1, $n), Cell::fromValue('', (new Style)->setBackgroundColor(self::BRAND)))));
        $writer->addRow(Row::fromValues([$titulo], (new Style)->setFontBold()->setFontSize(16)->setFontColor('101828')));
        $writer->addRow(Row::fromValues(
            ['VIABILIZA · SEDUR · Gerado em '.$gerado->format('d/m/Y H:i').' · '.$definition->titulo],
            (new Style)->setFontSize(10)->setFontColor('475467'),
        ));
        $writer->addRow(Row::fromValues(['']));
    }

    private function estiloCabecalho(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontColor('344054')
            ->setBackgroundColor(self::HEAD_BG)
            ->setBorder(new Border(new BorderPart(Border::BOTTOM, self::BRAND, Border::WIDTH_MEDIUM)));
    }

    private function estiloCorpo(int $indice): Style
    {
        return (new Style)
            ->setFontColor(self::INK)
            ->setBorder(new Border(new BorderPart(Border::BOTTOM, self::HAIRLINE, Border::WIDTH_THIN)));
    }

    private function tipo(mixed $valor, string $atual): string
    {
        if ($valor === null || $valor === '') {
            return $atual;
        }

        $novo = match (true) {
            is_int($valor) => 'inteiro',
            is_float($valor) => 'decimal',
            is_bool($valor) => 'logico',
            default => 'texto',
        };

        if ($atual === 'vazio' || $atual === $novo) {
            return $novo;
        }

        return in_array($atual.$novo, ['inteirodecimal', 'decimalinteiro'], true) ? 'decimal' : 'texto';
    }

    private function rotuloTipo(string $tipo): string
    {
        return match ($tipo) {
            'inteiro' => 'Inteiro',
            'decimal' => 'Número',
            'logico' => 'Lógico',
            default => 'Texto',
        };
    }

    private function formato(string $tipo): string
    {
        return match ($tipo) {
            'inteiro' => '0',
            'decimal' => 'Geral',
            default => '@',
        };
    }

    private function letra(int $indice): string
    {
        $letra = '';
        for ($i = $indice + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $letra = chr(65 + (($i - 1) % 26)).$letra;
        }

        return $letra;
    }

    /**
     * O openspout injeta o texto cru no XML: os códigos &L/&C/&R/&P precisam de escape.
     */
    private function cabecalhoRodape(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES);
    }

    private function limpa(string $texto): string
    {
        return str_replace('&', '&&', $texto);
    }
}
