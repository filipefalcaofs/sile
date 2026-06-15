<?php

namespace Tests\Feature\Relatorios;

use App\Jobs\GerarExportacaoJob;
use App\Models\Activity;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\XlsxExporter;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Relatorios\Stubs\ProcessoBairroSource;
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

    #[Test]
    public function export_xlsx_abaixo_do_limiar_streama_e_audita(): void
    {
        Queue::fake();
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Pituba']);
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Itapua']);

        $response = app(ReportExporter::class)->export(
            new ProcessoBairroSource,
            ReportFilters::fromArray([]),
            'xlsx',
            User::factory()->create(),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type'),
        );
        Queue::assertNothingPushed();

        $activity = Activity::query()
            ->where('log_name', 'relatorios')
            ->where('event', 'exporta-processos-xlsx')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'Toda exportação é auditada (RN-008).');
        $this->assertSame('xlsx', $activity->properties['formato']);
        $this->assertSame(2, $activity->properties['volume']);
    }

    #[Test]
    public function export_xlsx_acima_do_limiar_despacha_o_job_com_o_formato_xlsx(): void
    {
        Queue::fake();
        config(['sile.relatorios.export.assincrono_limiar_linhas' => 1]);
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Pituba']);
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Itapua']);

        $user = User::factory()->create();

        $response = app(ReportExporter::class)->export(
            new ProcessoBairroSource,
            ReportFilters::fromArray([]),
            'xlsx',
            $user,
        );

        $this->assertSame(202, $response->getStatusCode());

        Queue::assertPushed(GerarExportacaoJob::class, function (GerarExportacaoJob $job) use ($user): bool {
            return $job->sourceClass === ProcessoBairroSource::class
                && $job->formato === 'xlsx'
                && $job->userId === $user->id;
        });
    }
}
