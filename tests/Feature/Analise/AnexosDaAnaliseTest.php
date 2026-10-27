<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\AnalysisAttachment;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Anexos da análise: o analista junta documentos ao processo na ficha, no
 * mesmo padrão dos anexos da vistoria.
 */
class AnexosDaAnaliseTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['viabiliza.storage.documentos.disk' => 'local']);
        Storage::fake('local');
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function processo(AnalysisRecordStatus $status = AnalysisRecordStatus::Rascunho, ?User $dono = null): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->protocoled()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-2026-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => $status,
            'analyst_user_id' => $dono?->id,
        ]);

        return $processo;
    }

    public function test_analista_anexa_documentos_e_a_ficha_lista(): void
    {
        $analista = $this->analista();
        $processo = $this->processo();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/analise/anexos", [
                'files' => [
                    UploadedFile::fake()->create('parecer-externo.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->image('fachada.jpg'),
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $processo->analysisAttachments()->count());
        $anexo = $processo->analysisAttachments()->where('original_name', 'parecer-externo.pdf')->sole();
        Storage::disk('local')->assertExists($anexo->path);
        $this->assertSame($analista->id, $anexo->uploaded_by_user_id);
        $this->assertSame(64, strlen($anexo->sha256));
        $this->assertSame(2, Activity::query()->where('event', 'anexo-adicionado')->count());

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page
                ->has('anexosAnalise', 2)
                ->where('anexosAnalise.0.enviado_por', $analista->name));
    }

    public function test_tipo_de_arquivo_nao_permitido_e_recusado(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/analise/anexos", [
                'file' => UploadedFile::fake()->create('programa.exe', 10, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $processo->analysisAttachments()->count());
    }

    public function test_ficha_finalizada_nao_aceita_nem_remove_anexo(): void
    {
        $processo = $this->processo(AnalysisRecordStatus::Finalizada);
        $anexo = AnalysisAttachment::factory()->create(['viability_request_id' => $processo->id]);
        $analista = $this->analista();

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/analise/anexos", [
                'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            ])
            ->assertUnprocessable();

        $this->actingAs($analista, 'gestao')
            ->deleteJson("/gestao/processos/{$processo->id}/analise/anexos/{$anexo->id}")
            ->assertUnprocessable();

        $this->assertSame(1, $processo->analysisAttachments()->count());
    }

    public function test_ficha_de_outro_analista_nao_recebe_anexo(): void
    {
        $dono = $this->analista();
        $processo = $this->processo(dono: $dono);

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/analise/anexos", [
                'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertSame(0, $processo->analysisAttachments()->count());
    }

    public function test_quem_nao_analisa_nao_anexa_nem_baixa(): void
    {
        $processo = $this->processo();
        $anexo = AnalysisAttachment::factory()->create(['viability_request_id' => $processo->id]);
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->post("/gestao/processos/{$processo->id}/analise/anexos", [
                'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($semPermissao, 'gestao')
            ->get("/gestao/processos/{$processo->id}/analise/anexos/{$anexo->id}")
            ->assertForbidden();
    }

    public function test_download_faz_streaming_do_arquivo_e_audita(): void
    {
        $processo = $this->processo();
        Storage::disk('local')->put('analises/doc.pdf', 'conteudo-do-pdf');
        $anexo = AnalysisAttachment::factory()->create([
            'viability_request_id' => $processo->id,
            'disk' => 'local',
            'path' => 'analises/doc.pdf',
            'original_name' => 'doc.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $resposta = $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/analise/anexos/{$anexo->id}");

        $resposta->assertOk();
        $this->assertSame('conteudo-do-pdf', $resposta->streamedContent());
        $this->assertSame(1, Activity::query()->where('event', 'anexo-download')->count());
    }

    public function test_anexo_de_outro_processo_nao_e_acessivel_pela_url(): void
    {
        $processo = $this->processo();
        $outro = $this->processo();
        $anexo = AnalysisAttachment::factory()->create(['viability_request_id' => $outro->id]);
        $analista = $this->analista();

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}/analise/anexos/{$anexo->id}")
            ->assertNotFound();

        $this->actingAs($analista, 'gestao')
            ->delete("/gestao/processos/{$processo->id}/analise/anexos/{$anexo->id}")
            ->assertNotFound();
    }

    public function test_remover_apaga_arquivo_e_registro(): void
    {
        $processo = $this->processo();
        Storage::disk('local')->put('analises/doc.pdf', 'x');
        $anexo = AnalysisAttachment::factory()->create([
            'viability_request_id' => $processo->id,
            'disk' => 'local',
            'path' => 'analises/doc.pdf',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->delete("/gestao/processos/{$processo->id}/analise/anexos/{$anexo->id}")
            ->assertRedirect();

        $this->assertSame(0, $processo->analysisAttachments()->count());
        Storage::disk('local')->assertMissing('analises/doc.pdf');
        $this->assertSame(1, Activity::query()->where('event', 'anexo-removido')->count());
    }
}
