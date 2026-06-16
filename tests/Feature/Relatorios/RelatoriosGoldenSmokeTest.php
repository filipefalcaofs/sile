<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\PdfExporter;
use App\Services\Relatorios\Export\Sources\SolicitacoesReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Golden/smoke do comando de EVIDÊNCIA do export (HU-131 / 15-15):
 * `relatorios:exportar` grava UM arquivo REAL de cada formato (CSV/XLSX/PDF) a
 * partir do dado semeado, provando a lógica de export ponta a ponta (entrega-
 * funcional, sem fachada). A prova é dupla: o arquivo é legível no formato real
 * (fgetcsv / Reader do openspout / cabeçalho %PDF) e o número de linhas de dado
 * é EXATAMENTE o `count()` do conjunto filtrado (RN-005) — nunca um número
 * inventado. O caso vazio gera arquivo honesto só com o cabeçalho (0 linhas de
 * dado), jamais um total fabricado (CA-03 anti-fachada).
 */
class RelatoriosGoldenSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Semeia uma massa conhecida pelo FLUXO REAL (solicitações em estados variados
     * + decisões deferida/indeferida) e devolve a contagem do conjunto filtrado
     * (a fonte única da verdade do que deve sair no arquivo — RN-005).
     */
    private function seedDataset(): int
    {
        $tvl = 0;
        ViabilityRequest::factory()->count(2)->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocoled_at' => now()->subDays(3),
        ])->each(function (ViabilityRequest $request) use (&$tvl): void {
            $tvl++;
            ViabilityDecision::factory()->create([
                'viability_request_id' => $request->id,
                'outcome' => DecisionOutcome::Deferida,
                'tvl_product_number' => sprintf('TVL-%d-%06d', now()->year, $tvl),
                'decided_at' => now()->subDays(2),
            ]);
        });

        $indeferida = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Indeferida,
            'protocoled_at' => now()->subDays(3),
        ]);
        ViabilityDecision::factory()->indeferida()->create([
            'viability_request_id' => $indeferida->id,
            'decided_at' => now()->subDays(2),
        ]);

        ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocoled_at' => now()->subDay(),
        ]);

        return app(SolicitacoesReportSource::class)
            ->definition(ReportFilters::fromArray([]))
            ->builder()->count();
    }

    /**
     * Caminho absoluto do arquivo de evidência gerado com a extensão informada.
     */
    private function arquivoGerado(string $ext): string
    {
        $arquivo = collect(Storage::disk('local')->files('relatorios/evidencias'))
            ->first(fn (string $f): bool => str_ends_with($f, '.'.$ext));

        $this->assertNotNull($arquivo, "O comando deve gerar um arquivo .{$ext} de evidência.");

        return Storage::disk('local')->path($arquivo);
    }

    /**
     * Lê o CSV com fgetcsv (trata aspas/quebras embutidas) e devolve as linhas.
     *
     * @return list<array<int, string|null>>
     */
    private function linhasCsv(string $caminho): array
    {
        $linhas = [];
        $handle = fopen($caminho, 'r');

        while (($linha = fgetcsv($handle, escape: '')) !== false) {
            $linhas[] = $linha;
        }

        fclose($handle);

        return $linhas;
    }

    /**
     * Reabre o XLSX com o Reader do openspout (prova de legibilidade real).
     *
     * @return list<array<int, mixed>>
     */
    private function linhasXlsx(string $caminho): array
    {
        $reader = new Reader;
        $reader->open($caminho);

        $linhas = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $linhas[] = $row->toArray();
            }
            break;
        }

        $reader->close();

        return $linhas;
    }

    #[Test]
    public function exportar_csv_gera_arquivo_real_com_cabecalho_e_uma_linha_por_registro(): void
    {
        Storage::fake('local');
        $esperado = $this->seedDataset();
        $this->assertGreaterThan(0, $esperado);

        $this->artisan('relatorios:exportar', ['source' => 'solicitacoes', '--formato' => 'csv'])
            ->assertExitCode(0);

        $linhas = $this->linhasCsv($this->arquivoGerado('csv'));

        // Cabeçalho + exatamente N linhas de dado (== conjunto filtrado, RN-005).
        $this->assertCount($esperado + 1, $linhas);
        $this->assertSame('Processo', $linhas[0][0]);
    }

    #[Test]
    public function exportar_xlsx_gera_planilha_legivel_pelo_reader_openspout(): void
    {
        Storage::fake('local');
        $esperado = $this->seedDataset();

        $this->artisan('relatorios:exportar', ['source' => 'solicitacoes', '--formato' => 'xlsx'])
            ->assertExitCode(0);

        $linhas = $this->linhasXlsx($this->arquivoGerado('xlsx'));

        // Cabeçalho + N linhas de dado, reabrindo o arquivo real pelo openspout.
        $this->assertCount($esperado + 1, $linhas);
        $this->assertSame('Processo', $linhas[0][0]);
    }

    #[Test]
    public function exportar_pdf_gera_pdf_real_com_total_de_registros_coerente(): void
    {
        Storage::fake('local');
        $esperado = $this->seedDataset();

        $this->artisan('relatorios:exportar', ['source' => 'solicitacoes', '--formato' => 'pdf'])
            ->assertExitCode(0);

        $conteudo = (string) file_get_contents($this->arquivoGerado('pdf'));
        $this->assertStringStartsWith('%PDF', $conteudo);

        // O dompdf comprime os streams; o total é provado pelo Blade renderizado
        // sobre o MESMO conjunto (mesmo contrato do ReportExporterTest).
        $definition = app(SolicitacoesReportSource::class)->definition(ReportFilters::fromArray([]));
        $html = view('relatorios.relatorio', app(PdfExporter::class)->dados($definition))->render();
        $this->assertStringContainsString("Total de registros: {$esperado}", $html);
    }

    #[Test]
    public function exportar_conjunto_vazio_gera_arquivo_honesto_sem_linha_de_dado(): void
    {
        Storage::fake('local');

        // Anti-fachada (CA-03): sem dado semeado o conjunto é vazio — o arquivo sai
        // só com o cabeçalho (0 linhas de dado), nunca um número inventado.
        $this->artisan('relatorios:exportar', ['source' => 'solicitacoes', '--formato' => 'csv'])
            ->assertExitCode(0);

        $linhas = $this->linhasCsv($this->arquivoGerado('csv'));

        $this->assertCount(1, $linhas);
        $this->assertSame('Processo', $linhas[0][0]);
    }

    #[Test]
    public function sem_formato_gera_os_tres_formatos_de_uma_vez(): void
    {
        Storage::fake('local');
        $this->seedDataset();

        $this->artisan('relatorios:exportar')->assertExitCode(0);

        // source default = solicitacoes; sem --formato gera os três do contrato.
        $this->arquivoGerado('csv');
        $this->arquivoGerado('xlsx');
        $this->arquivoGerado('pdf');
    }

    #[Test]
    public function source_desconhecido_falha_honestamente(): void
    {
        Storage::fake('local');

        // Sem fachada: uma fonte inexistente é recusada (exit 1), não gera arquivo
        // vazio fingindo sucesso.
        $this->artisan('relatorios:exportar', ['source' => 'inexistente', '--formato' => 'csv'])
            ->assertExitCode(1);

        $this->assertEmpty(Storage::disk('local')->files('relatorios/evidencias'));
    }
}
