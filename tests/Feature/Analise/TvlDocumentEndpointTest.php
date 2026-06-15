<?php

namespace Tests\Feature\Analise;

use App\Models\TvlDocument;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\TvlPdfService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Endpoints do TVL (HU-132): o controller FINO emite o TVL de uma decisão
 * DEFERIDA via TvlPdfService::generate (10-13) e oferece o download por URL
 * TEMPORÁRIA ASSINADA servindo o disco NÃO público por streaming — nunca URL
 * pública, nunca ao cidadão (CA-02). Gated por emitir-tvl (403 auditado — CA-04);
 * decisão não deferida → 422 (FA-01); link sem assinatura/expirado → 403.
 */
class TvlDocumentEndpointTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function semEmitirTvl(): User
    {
        $user = User::factory()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('acessar-gestao');

        return $user;
    }

    private function processo(): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create([
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ])->fresh();
    }

    private function processoDeferido(): ViabilityRequest
    {
        $request = $this->processo();
        ViabilityDecision::factory()->create(['viability_request_id' => $request->id]);

        return $request->fresh();
    }

    private function processoIndeferido(): ViabilityRequest
    {
        $request = $this->processo();
        ViabilityDecision::factory()->indeferida()->create(['viability_request_id' => $request->id]);

        return $request->fresh();
    }

    public function test_sem_permissao_emitir_tvl_recebe_403_auditado(): void
    {
        $processo = $this->processoDeferido();

        $this->actingAs($this->semEmitirTvl(), 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/tvl")
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);

        $this->assertDatabaseCount('tvl_documents', 0);
    }

    public function test_emite_tvl_de_decisao_deferida(): void
    {
        // CA-01/RN-002: gera o documento (PDF real no disco não público), registra
        // a emissão auditada em tvl_documents e devolve o link de download.
        $processo = $this->processoDeferido();

        $response = $this->actingAs($this->analista(), 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/tvl")
            ->assertOk()
            ->assertJsonStructure(['document' => ['id', 'verification_code', 'generated_at'], 'download_url']);

        $this->assertDatabaseCount('tvl_documents', 1);

        $document = TvlDocument::query()->latest('id')->first();
        $this->assertNotSame('public', $document->disk);
        Storage::disk($document->disk)->assertExists($document->path);
        $this->assertStringStartsWith('%PDF', Storage::disk($document->disk)->get($document->path));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'tvl-emitido',
            'result' => 'sucesso',
        ]);

        $this->assertNotEmpty($response->json('download_url'));
    }

    public function test_bloqueia_emissao_de_decisao_indeferida_com_422(): void
    {
        // FA-01: o TVL só sai de decisão deferida. Indeferida → 422; nada gerado.
        $processo = $this->processoIndeferido();

        $this->actingAs($this->analista(), 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/tvl")
            ->assertStatus(422);

        $this->assertDatabaseCount('tvl_documents', 0);
    }

    public function test_download_com_url_assinada_valida_faz_streaming(): void
    {
        // HU-132: o download é por URL temporária assinada servindo o arquivo do
        // disco NÃO público por streaming (o conteúdo é o PDF real).
        $processo = $this->processoDeferido();
        $analista = $this->analista();

        $url = $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/tvl")
            ->assertOk()
            ->json('download_url');

        $download = $this->actingAs($analista, 'gestao')->get($url);

        $download->assertOk();
        $download->assertDownload();
        $this->assertStringStartsWith('%PDF', $download->streamedContent());
    }

    public function test_download_sem_assinatura_ou_expirado_e_403(): void
    {
        // Segurança: sem assinatura válida (ou expirada) → 403, nunca 200 (o disco
        // é não público; o acesso depende do link assinado com TTL).
        $decision = ViabilityDecision::factory()->create([
            'viability_request_id' => $this->processo()->id,
        ]);
        $document = app(TvlPdfService::class)->generate($decision, $this->analista());
        $analista = $this->analista();

        // Sem assinatura.
        $this->actingAs($analista, 'gestao')
            ->get(route('gestao.processos.tvl.download', $document))
            ->assertForbidden();

        // Assinatura expirada.
        $expirada = URL::temporarySignedRoute(
            'gestao.processos.tvl.download',
            now()->subMinute(),
            ['tvlDocument' => $document->id],
        );

        $this->actingAs($analista, 'gestao')->get($expirada)->assertForbidden();
    }
}
