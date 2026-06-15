<?php

namespace Tests\Feature\Relatorios;

use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\XlsxExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Driver XLSX do contrato de exportação (HU-131): openspout v4 por streaming de
 * baixa memória (Builder->cursor(), nunca all()). O arquivo gerado é legível ao
 * reabrir com o Reader do openspout e reflete EXATAMENTE o conjunto filtrado
 * (RN-005) — a linha fora do filtro não entra na planilha.
 */
class XlsxExporterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Definition de teste sobre ViabilityRequest com 2 colunas (Bairro, Status) e
     * o builder FILTRADO a 'Pituba' — prova RN-005: só o conjunto filtrado é
     * exportado.
     */
    private function definition(): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Processos do período',
            colunas: [
                ['key' => 'bairro', 'label' => 'Bairro'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            builder: fn (): Builder => ViabilityRequest::query()
                ->where('address_neighborhood', 'Pituba')
                ->orderBy('id'),
            mapRow: fn (ViabilityRequest $r): array => [$r->address_neighborhood, $r->status->value],
            arquivoBase: 'processos',
        );
    }

    /**
     * Reabre o XLSX com o Reader do openspout e devolve as linhas da primeira aba
     * como arrays de valores de célula (prova de legibilidade real do arquivo).
     *
     * @return list<array<int, mixed>>
     */
    private function lerXlsx(string $caminho): array
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
    public function write_gera_xlsx_legivel_com_cabecalho_e_apenas_o_conjunto_filtrado(): void
    {
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Pituba']);
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Itapua']);

        $caminho = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        app(XlsxExporter::class)->write($this->definition(), $caminho);

        $linhas = $this->lerXlsx($caminho);
        @unlink($caminho);

        // 1ª linha = rótulos das colunas (cabeçalho).
        $this->assertSame(['Bairro', 'Status'], $linhas[0]);
        // RN-005: cabeçalho + 1 linha de dados (Pituba); Itapua, fora do filtro,
        // NÃO entra na planilha.
        $this->assertCount(2, $linhas);
        $this->assertSame('Pituba', $linhas[1][0]);
    }
}
