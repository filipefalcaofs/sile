<?php

namespace Tests\Feature\Relatorios;

use App\Jobs\GerarExportacaoJob;
use App\Models\Activity;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\PdfExporter;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Feature\Relatorios\Stubs\GrandeSyncOnlySource;
use Tests\Feature\Relatorios\Stubs\ProcessoBairroSource;
use Tests\TestCase;

/**
 * Orquestrador do contrato de exportação (HU-131): abaixo do limiar exporta
 * SÍNCRONO (streaming) e acima dispara o GerarExportacaoJob carregando o BAG
 * completo (RN-005/RN-006). Toda exportação é auditada (RN-008). A guarda
 * SyncOnly força o síncrono para sources com estado de construtor (nunca vão ao
 * Job). O conteúdo exportado reflete EXATAMENTE o conjunto filtrado (RN-005).
 */
class ReportExporterTest extends TestCase
{
    use RefreshDatabase;

    private function exporter(): ReportExporter
    {
        return app(ReportExporter::class);
    }

    private function capturar(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    #[Test]
    public function abaixo_do_limiar_exporta_sincrono_em_streaming_e_audita(): void
    {
        Queue::fake();
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Pituba']);
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Itapua']);

        $response = $this->exporter()->export(
            new ProcessoBairroSource,
            ReportFilters::fromArray([]),
            'csv',
            User::factory()->create(),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        Queue::assertNothingPushed();

        $activity = Activity::query()
            ->where('log_name', 'relatorios')
            ->where('event', 'exporta-processos-csv')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'Toda exportação é auditada (RN-008).');
        $this->assertSame('csv', $activity->properties['formato']);
        $this->assertSame(2, $activity->properties['volume']);
    }

    #[Test]
    public function acima_do_limiar_despacha_o_job_com_o_bag_completo(): void
    {
        Queue::fake();
        config(['sile.relatorios.export.assincrono_limiar_linhas' => 1]);
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Pituba']);
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Itapua']);

        $user = User::factory()->create();
        // Bag sem filtro de bairro (total 2 > limiar 1 → assíncrono); carrega uma
        // chave de auditoria que deve sobreviver no payload (RN-005 async).
        $bag = ['grupo' => 'em_andamento', 'usuario_id' => '9'];

        $response = $this->exporter()->export(
            new ProcessoBairroSource,
            ReportFilters::fromArray($bag),
            'csv',
            $user,
        );

        $this->assertSame(202, $response->getStatusCode());

        Queue::assertPushed(GerarExportacaoJob::class, function (GerarExportacaoJob $job) use ($user): bool {
            return $job->sourceClass === ProcessoBairroSource::class
                && $job->formato === 'csv'
                && $job->userId === $user->id
                && $job->filtros === ['grupo' => 'em_andamento', 'usuario_id' => '9'];
        });
    }

    #[Test]
    public function exportacao_sincrona_reflete_exatamente_o_conjunto_filtrado(): void
    {
        Queue::fake();
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Pituba']);
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Itapua']);

        $response = $this->exporter()->export(
            new ProcessoBairroSource,
            ReportFilters::fromArray(['bairro' => 'Pituba']),
            'csv',
            User::factory()->create(),
        );

        $csv = $this->capturar($response);

        // RN-005: a linha fora do filtro (Itapua) NÃO aparece no arquivo.
        $this->assertStringContainsString('Pituba', $csv);
        $this->assertStringNotContainsString('Itapua', $csv);
    }

    #[Test]
    public function exportacao_pdf_e_um_pdf_real_com_total_de_registros(): void
    {
        Queue::fake();
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Pituba']);

        $filtros = ReportFilters::fromArray([]);

        $response = $this->exporter()->export(new ProcessoBairroSource, $filtros, 'pdf', User::factory()->create());

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());

        // O conteúdo textual é provado pelo Blade (o dompdf comprime os streams).
        $html = view('relatorios.relatorio', app(PdfExporter::class)->dados((new ProcessoBairroSource)->definition($filtros)))->render();
        $this->assertStringContainsString('Total de registros: 1', $html);
    }

    #[Test]
    public function source_sync_only_acima_do_limiar_ainda_e_sincrono(): void
    {
        Queue::fake();
        config(['sile.relatorios.export.assincrono_limiar_linhas' => 1]);
        ViabilityRequest::factory()->count(3)->create();

        $response = $this->exporter()->export(
            new GrandeSyncOnlySource,
            ReportFilters::fromArray([]),
            'csv',
            User::factory()->create(),
        );

        $this->assertSame(200, $response->getStatusCode());
        // Guarda SyncOnly: mesmo acima do limiar, nunca despacha o Job.
        Queue::assertNothingPushed();
    }

    #[Test]
    public function formato_nao_disponivel_e_recusado(): void
    {
        // Anti-fachada: xlsx está no catálogo (config) mas o driver só nasce em
        // 15-08 — recusar honestamente em vez de oferecer um formato quebrado.
        ViabilityRequest::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->exporter()->export(
            new ProcessoBairroSource,
            ReportFilters::fromArray([]),
            'xlsx',
            User::factory()->create(),
        );
    }
}
