<?php

namespace Tests\Feature\Relatorios;

use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\CsvExporter;
use App\Services\Relatorios\Export\PdfExporter;
use App\Services\Relatorios\Export\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Drivers síncronos do contrato de exportação (HU-131): o CSV preserva a
 * integridade tabular (cabeçalho + linhas, sem total no corpo — RN-010) e o PDF
 * é renderizado DE VERDADE pelo dompdf (começa com '%PDF') sobre o Blade
 * genérico, que traz o rodapé "Total de registros: N" + data/hora + filtros
 * (CA-07/RN-010). Cada driver gera o conteúdo REAL do conjunto filtrado da
 * definition (RN-005), sem fachada.
 */
class ExportDriversTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Definition de teste sobre ViabilityRequest: 2 linhas (Bairro, Status),
     * ordenadas por id. mapRow devolve escalares.
     */
    private function definition(): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Processos do período',
            colunas: [
                ['key' => 'bairro', 'label' => 'Bairro'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            builder: fn (): Builder => ViabilityRequest::query()->orderBy('id'),
            mapRow: fn (ViabilityRequest $r): array => [$r->address_neighborhood, $r->status->value],
            filtrosAplicados: ['grupo' => 'em_andamento'],
            arquivoBase: 'processos',
        );
    }

    private function capturar(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    #[Test]
    public function csv_stream_comeca_com_o_cabecalho_e_traz_as_linhas_do_builder(): void
    {
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Pituba']);
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Itapua']);

        $conteudo = $this->capturar(app(CsvExporter::class)->stream($this->definition()));

        $this->assertStringStartsWith('Bairro,Status', $conteudo);
        $this->assertStringContainsString('Pituba', $conteudo);
        $this->assertStringContainsString('Itapua', $conteudo);
        // RN-010: o CSV não carrega linha de total no corpo (integridade tabular).
        $this->assertStringNotContainsString('Total de registros', $conteudo);
    }

    #[Test]
    public function csv_write_grava_o_mesmo_conteudo_num_arquivo_real(): void
    {
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Pituba']);

        $caminho = tempnam(sys_get_temp_dir(), 'export').'.csv';
        app(CsvExporter::class)->write($this->definition(), $caminho);
        $conteudo = (string) file_get_contents($caminho);
        @unlink($caminho);

        $this->assertStringStartsWith('Bairro,Status', $conteudo);
        $this->assertStringContainsString('Pituba', $conteudo);
    }

    #[Test]
    public function pdf_stream_renderiza_pdf_real_comecando_com_assinatura_pdf(): void
    {
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Pituba']);

        $bytes = app(PdfExporter::class)->stream($this->definition())->getContent();

        $this->assertStringStartsWith('%PDF', (string) $bytes);
    }

    #[Test]
    public function blade_do_pdf_traz_total_de_registros_coerente_e_filtros(): void
    {
        // O conteúdo textual do PDF é provado pelo Blade (o dompdf comprime os
        // streams do PDF). O total bate com as linhas do builder (CA-07/RN-010).
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Pituba']);
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Itapua']);

        $exporter = app(PdfExporter::class);
        $html = view('relatorios.relatorio', $exporter->dados($this->definition()))->render();

        $this->assertStringContainsString('Total de registros: 2', $html);
        $this->assertStringContainsString('Processos do período', $html);
        $this->assertStringContainsString('Pituba', $html);
        $this->assertStringContainsString('Itapua', $html);
    }
}
