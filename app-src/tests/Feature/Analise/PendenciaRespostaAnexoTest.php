<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\AnalysisPendencyAttachment;
use App\Models\Cidadao;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PendenciaRespostaAnexoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::fake('local');
    }

    private function portalUser(): Cidadao
    {
        return Cidadao::factory()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @return array{0: Cidadao, 1: ViabilityRequest, 2: AnalysisPendency}
     */
    private function convite(): array
    {
        $dono = $this->portalUser();
        $request = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'requester_cidadao_id' => $dono->id,
            'created_by_type' => $dono->getMorphClass(),
            'created_by_id' => $dono->id,
        ]);
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmPendencia,
            'protocol_number' => 'VIA-'.now()->year.'-'.str_pad((string) $request->id, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now(),
        ])->save();

        $pendency = AnalysisPendency::factory()->create([
            'viability_request_id' => $request->id,
            'status' => AnalysisPendencyStatus::Aberta,
        ]);

        return [$dono, $request->refresh(), $pendency];
    }

    private function responder(Cidadao $dono, ViabilityRequest $request, AnalysisPendency $pendency, array $dados)
    {
        return $this->actingAs($dono)
            ->from(route('portal.solicitacoes.pendencias', $request))
            ->post(route('portal.solicitacoes.pendencias.responder', [$request, $pendency]), $dados);
    }

    public function test_resposta_com_anexo_grava_o_arquivo_e_os_metadados(): void
    {
        [$dono, $request, $pendency] = $this->convite();
        $arquivo = UploadedFile::fake()->create('iptu.pdf', 120, 'application/pdf');

        $this->responder($dono, $request, $pendency, ['response' => 'Segue o IPTU.', 'attachment' => $arquivo])
            ->assertRedirect(route('portal.solicitacoes.show', $request))
            ->assertSessionHasNoErrors();

        $anexo = $pendency->attachments()->firstOrFail();
        $this->assertSame('iptu.pdf', $anexo->original_name);
        $this->assertSame('application/pdf', $anexo->mime_type);
        $this->assertSame(64, strlen($anexo->sha256));
        Storage::disk('local')->assertExists($anexo->path);
        $this->assertSame(AnalysisPendencyStatus::Respondida, $pendency->refresh()->status);
    }

    public function test_resposta_sem_anexo_continua_valida(): void
    {
        [$dono, $request, $pendency] = $this->convite();

        $this->responder($dono, $request, $pendency, ['response' => 'Sem documento.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $pendency->attachments()->count());
        $this->assertSame(AnalysisPendencyStatus::Respondida, $pendency->refresh()->status);
    }

    public function test_tipo_de_arquivo_nao_permitido_e_recusado_sem_mudar_o_convite(): void
    {
        [$dono, $request, $pendency] = $this->convite();
        $arquivo = UploadedFile::fake()->create('programa.exe', 10, 'application/x-msdownload');

        $this->responder($dono, $request, $pendency, ['response' => 'Segue.', 'attachment' => $arquivo])
            ->assertSessionHasErrors('attachment');

        $this->assertSame(AnalysisPendencyStatus::Aberta, $pendency->refresh()->status);
        $this->assertSame(0, AnalysisPendencyAttachment::query()->count());
    }

    public function test_arquivo_maior_que_o_limite_e_recusado(): void
    {
        [$dono, $request, $pendency] = $this->convite();
        $arquivo = UploadedFile::fake()->create('grande.pdf', 11 * 1024, 'application/pdf');

        $this->responder($dono, $request, $pendency, ['response' => 'Segue.', 'attachment' => $arquivo])
            ->assertSessionHasErrors('attachment');

        $this->assertSame(AnalysisPendencyStatus::Aberta, $pendency->refresh()->status);
    }

    public function test_dono_baixa_o_anexo_e_terceiro_nao(): void
    {
        [$dono, $request, $pendency] = $this->convite();
        Storage::disk('local')->put('convites/teste.pdf', 'conteudo');
        $anexo = AnalysisPendencyAttachment::factory()->create([
            'analysis_pendency_id' => $pendency->id,
            'path' => 'convites/teste.pdf',
            'original_name' => 'iptu.pdf',
        ]);
        $rota = route('portal.solicitacoes.pendencias.anexo', [$request, $pendency, $anexo]);

        $this->actingAs($dono)->get($rota)->assertOk()->assertDownload('iptu.pdf');
        $this->actingAs($this->portalUser())->get($rota)->assertForbidden();
    }

    public function test_anexo_de_outro_convite_da_404(): void
    {
        [$dono, $request, $pendency] = $this->convite();
        $outroConvite = AnalysisPendency::factory()->create(['viability_request_id' => ViabilityRequest::factory()->create()->id]);
        $outro = AnalysisPendencyAttachment::factory()->create(['analysis_pendency_id' => $outroConvite->id]);

        $this->actingAs($dono)
            ->get(route('portal.solicitacoes.pendencias.anexo', [$request, $pendency, $outro]))
            ->assertNotFound();
    }

    public function test_analista_baixa_o_anexo_pela_gestao(): void
    {
        [, $request, $pendency] = $this->convite();
        Storage::disk('local')->put('convites/teste.pdf', 'conteudo');
        $anexo = AnalysisPendencyAttachment::factory()->create([
            'analysis_pendency_id' => $pendency->id,
            'path' => 'convites/teste.pdf',
            'original_name' => 'iptu.pdf',
        ]);
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$request->id}/pendencias/{$pendency->id}/anexos/{$anexo->id}")
            ->assertOk()
            ->assertDownload('iptu.pdf');
    }
}
