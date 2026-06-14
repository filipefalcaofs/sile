<?php

namespace Tests\Feature\Solicitacao;

use App\Models\DocumentRequirement;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Anexar documentos à solicitação (HU-066): upload via Storage com disk
 * PARAMETRIZADO (storage.documentos.disk, nunca público), MIME e tamanho
 * validados por parâmetro (HU-014), gravando disk/path/sha256/original_name e
 * auditando (RN-002). O download é por STREAMING e só do dono autenticado
 * (LGPD); anexo substituível/removível enquanto a solicitação é rascunho.
 */
class AnexarDocumentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    private function draftFor(User $user): ViabilityRequest
    {
        return ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_anexa_documento(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        $file = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');
        $sha256Esperado = hash_file('sha256', $file->getRealPath());

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->post(route('portal.solicitacoes.documentos.store', $solicitacao), [
                'file' => $file,
            ])
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHas('status');

        $documento = $solicitacao->documents()->first();

        $this->assertNotNull($documento);
        $this->assertSame('local', $documento->disk);
        $this->assertSame('documento.pdf', $documento->original_name);
        $this->assertSame(64, strlen((string) $documento->sha256));
        $this->assertSame($sha256Esperado, $documento->sha256);

        // O arquivo foi gravado DE VERDADE no disk (anti-fachada).
        Storage::disk('local')->assertExists($documento->path);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'documento-anexado',
            'subject_type' => $solicitacao->getMorphClass(),
            'subject_id' => $solicitacao->id,
        ]);
    }

    public function test_rejeita_mime_nao_permitido(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        // Restringe os MIMEs aceitos a apenas PDF (HU-014) — efeito sem deploy.
        Parameter::factory()->create([
            'key' => 'solicitacao.anexos.mime_permitidos',
            'group' => 'solicitacao',
            'type' => 'json',
            'value' => json_encode(['application/pdf']),
        ]);

        $png = UploadedFile::fake()->create('foto.png', 100, 'image/png');

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->post(route('portal.solicitacoes.documentos.store', $solicitacao), [
                'file' => $png,
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $solicitacao->documents()->count());
    }

    public function test_rejeita_tamanho_acima_do_maximo(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        // Limite reduzido para 1 MB (HU-014).
        Parameter::factory()->create([
            'key' => 'solicitacao.anexos.max_mb',
            'group' => 'solicitacao',
            'type' => 'integer',
            'value' => '1',
        ]);

        // 2 MB excede o limite de 1 MB → erro comunicado (não silencioso).
        $grande = UploadedFile::fake()->create('grande.pdf', 2048, 'application/pdf');

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->post(route('portal.solicitacoes.documentos.store', $solicitacao), [
                'file' => $grande,
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $solicitacao->documents()->count());
    }

    public function test_download_so_autenticado_e_dono(): void
    {
        $owner = $this->portalUser();
        $solicitacao = $this->draftFor($owner);

        $path = 'solicitacoes/'.$solicitacao->id.'/anexo.pdf';
        Storage::disk('local')->put($path, 'conteudo-do-documento');

        $documento = $solicitacao->documents()->create([
            'requirement_id' => null,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'anexo.pdf',
            'mime_type' => 'application/pdf',
            'size' => 21,
            'sha256' => hash('sha256', 'conteudo-do-documento'),
            'uploaded_by_user_id' => $owner->id,
        ]);

        // O dono baixa por streaming (conteúdo real do disk).
        $response = $this->actingAs($owner)
            ->get(route('portal.solicitacoes.documentos.download', [$solicitacao, $documento]));

        $response->assertOk();
        $this->assertSame('conteudo-do-documento', $response->streamedContent());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'documento-download',
            'subject_id' => $solicitacao->id,
        ]);

        // Terceiro NÃO baixa (LGPD) → 403 auditado (CA-04).
        $stranger = $this->portalUser();

        $this->actingAs($stranger)
            ->get(route('portal.solicitacoes.documentos.download', [$solicitacao, $documento]))
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
        ]);
    }

    public function test_disk_nunca_publico(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        $file = UploadedFile::fake()->create('documento.pdf', 50, 'application/pdf');

        $this->actingAs($user)
            ->post(route('portal.solicitacoes.documentos.store', $solicitacao), [
                'file' => $file,
            ]);

        $documento = $solicitacao->documents()->first();

        $this->assertNotNull($documento);
        // O disk vem do parâmetro (default local), NUNCA o disk público (LGPD).
        $this->assertSame('local', $documento->disk);
        $this->assertNotSame('public', $documento->disk);
    }

    public function test_substituir_e_remover_antes_do_protocolo(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);
        $requisito = DocumentRequirement::factory()->required()->create();

        // Primeiro anexo do requisito.
        $this->actingAs($user)->post(route('portal.solicitacoes.documentos.store', $solicitacao), [
            'file' => UploadedFile::fake()->create('primeiro.pdf', 50, 'application/pdf'),
            'requirement_id' => $requisito->id,
        ]);

        $anexoAntigo = $solicitacao->documents()->where('requirement_id', $requisito->id)->firstOrFail();
        $pathAntigo = $anexoAntigo->path;
        Storage::disk('local')->assertExists($pathAntigo);

        // Substituição: novo anexo do MESMO requisito → o anterior some (disk + db).
        $this->actingAs($user)->post(route('portal.solicitacoes.documentos.store', $solicitacao), [
            'file' => UploadedFile::fake()->create('segundo.pdf', 50, 'application/pdf'),
            'requirement_id' => $requisito->id,
        ]);

        $this->assertSame(1, $solicitacao->documents()->where('requirement_id', $requisito->id)->count());
        Storage::disk('local')->assertMissing($pathAntigo);

        $anexoAtual = $solicitacao->documents()->where('requirement_id', $requisito->id)->firstOrFail();
        Storage::disk('local')->assertExists($anexoAtual->path);

        // Remoção enquanto rascunho.
        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->delete(route('portal.solicitacoes.documentos.destroy', [$solicitacao, $anexoAtual]))
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('viability_request_documents', ['id' => $anexoAtual->id]);
        Storage::disk('local')->assertMissing($anexoAtual->path);
    }
}
