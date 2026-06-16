<?php

namespace Tests\Feature\Relatorios;

use App\Models\ExportFile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Download das exportações assíncronas (HU-131): o arquivo gerado pelo
 * GerarExportacaoJob (15-02) é baixado por URL TEMPORÁRIA ASSINADA (middleware
 * signed) servindo o disco NÃO público por streaming — nunca URL pública (LGPD),
 * espelhando o TvlDocumentController (HU-132). É o alvo do link da Notification
 * ExportacaoPronta. Gated por consultar-relatorios; o download é auditado
 * (RN-008). Sem assinatura → 403; arquivo podado por retenção (15-15) → 404
 * honesto (sem fabricar).
 */
class ExportacaoDownloadTest extends TestCase
{
    use RefreshDatabase;

    private string $disk = 'local';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake($this->disk);
    }

    private function gestor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    /**
     * ExportFile real no disco fake (não público) com conteúdo legível.
     *
     * @param  array<string, mixed>  $attrs
     */
    private function exportFileComConteudo(array $attrs = [], string $conteudo = "Processo\nVIA-2026-000001\n"): ExportFile
    {
        $path = 'relatorios/exportacoes/'.fake()->uuid().'.csv';
        Storage::disk($this->disk)->put($path, $conteudo);

        return ExportFile::factory()->create(array_merge([
            'disk' => $this->disk,
            'path' => $path,
            'filename' => 'relatorio-solicitacoes.csv',
            'format' => 'csv',
        ], $attrs));
    }

    private function urlAssinada(ExportFile $file): string
    {
        return URL::temporarySignedRoute(
            'gestao.relatorios.exportacoes.download',
            now()->addMinutes(15),
            ['exportFile' => $file->id],
        );
    }

    public function test_download_com_assinatura_valida_faz_streaming_do_disco_nao_publico(): void
    {
        $file = $this->exportFileComConteudo();
        $gestor = $this->gestor();

        $download = $this->actingAs($gestor, 'gestao')->get($this->urlAssinada($file));

        $download->assertOk();
        $download->assertDownload($file->filename);
        $this->assertStringContainsString('Processo', $download->streamedContent());

        // O disco é não público (LGPD) e o download é auditado (RN-008).
        $this->assertNotSame('public', $file->disk);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'baixa-exportacao',
            'result' => 'sucesso',
        ]);
    }

    public function test_download_sem_assinatura_e_403(): void
    {
        $file = $this->exportFileComConteudo();

        $this->actingAs($this->gestor(), 'gestao')
            ->get(route('gestao.relatorios.exportacoes.download', $file))
            ->assertForbidden();
    }

    public function test_download_de_arquivo_ausente_e_404_honesto(): void
    {
        // Sem put no Storage: o arquivo foi podado por retenção (15-15) → 404
        // honesto, sem fabricar um arquivo vazio.
        $file = ExportFile::factory()->create([
            'disk' => $this->disk,
            'path' => 'relatorios/exportacoes/inexistente.csv',
        ]);

        $this->actingAs($this->gestor(), 'gestao')
            ->get($this->urlAssinada($file))
            ->assertNotFound();
    }

    public function test_download_recusa_disco_publico(): void
    {
        // Guarda anti-'public': um ExportFile nunca é servido de disco público
        // por este endpoint (a exportação pode conter PII — RN-007/LGPD).
        Storage::fake('public');
        $path = 'relatorios/exportacoes/publico.csv';
        Storage::disk('public')->put($path, "Processo\nVIA\n");

        $file = ExportFile::factory()->create(['disk' => 'public', 'path' => $path]);

        $this->actingAs($this->gestor(), 'gestao')
            ->get($this->urlAssinada($file))
            ->assertNotFound();
    }
}
