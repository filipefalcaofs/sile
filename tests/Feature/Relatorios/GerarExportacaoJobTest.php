<?php

namespace Tests\Feature\Relatorios;

use App\Jobs\GerarExportacaoJob;
use App\Models\ExportFile;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\ExportacaoPronta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Feature\Relatorios\Stubs\ProcessoBairroSource;
use Tests\TestCase;

/**
 * Job de exportação assíncrona (HU-131 RN-006): reconstrói a definition SÓ a
 * partir de sourceClass + bag (ReportFilters::fromArray), grava o arquivo REAL no
 * disco não-público, registra o ExportFile (row_count real) e notifica o dono com
 * ExportacaoPronta. RN-005 no assíncrono: o arquivo reflete o MESMO conjunto
 * filtrado. Anti-fachada (Pitfall 6): failed() audita a falha e NÃO fabrica
 * ExportFile/link.
 */
class GerarExportacaoJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function handle_grava_o_arquivo_filtrado_cria_exportfile_e_notifica(): void
    {
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->create();
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Pituba']);
        ViabilityRequest::factory()->create(['address_neighborhood' => 'Itapua']);

        // O bag serializado carrega o filtro de bairro: a reconstrução via
        // ReportFilters::fromArray reproduz o conjunto filtrado (RN-005 async).
        (new GerarExportacaoJob(ProcessoBairroSource::class, ['bairro' => 'Pituba'], 'csv', $user->id))->handle();

        $this->assertDatabaseCount('export_files', 1);

        $export = ExportFile::query()->firstOrFail();
        $this->assertSame(1, $export->row_count);
        $this->assertSame('csv', $export->format);
        $this->assertSame($user->id, $export->user_id);
        $this->assertNotSame('public', $export->disk);

        Storage::disk($export->disk)->assertExists($export->path);
        $conteudo = (string) Storage::disk($export->disk)->get($export->path);
        $this->assertStringContainsString('Pituba', $conteudo);
        $this->assertStringNotContainsString('Itapua', $conteudo);

        Notification::assertSentTo($user, ExportacaoPronta::class);
    }

    #[Test]
    public function failed_audita_a_falha_e_nao_cria_exportfile(): void
    {
        // Anti-fachada: um export que estoura as tentativas audita 'falha' (RN-002)
        // e NÃO disponibiliza arquivo/link.
        $job = new GerarExportacaoJob(ProcessoBairroSource::class, ['bairro' => 'Pituba'], 'csv', null);

        $job->failed(new RuntimeException('falha simulada na geração'));

        $this->assertDatabaseCount('export_files', 0);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'exporta-falha',
            'result' => 'falha',
        ]);
    }

    #[Test]
    public function define_retry_timeout_e_backoff_da_config(): void
    {
        $job = new GerarExportacaoJob(ProcessoBairroSource::class, [], 'csv', null);

        $this->assertSame(3, $job->tries);
        $this->assertSame(300, $job->timeout);
        $this->assertSame([30, 60, 120], $job->backoff);
    }
}
