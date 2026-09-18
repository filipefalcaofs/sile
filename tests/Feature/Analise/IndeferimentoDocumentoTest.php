<?php

namespace Tests\Feature\Analise;

use App\Models\IndeferimentoDocument;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\IndeferimentoPdfService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Documento de indeferimento fundamentado (espelho do TVL — HU-132): emitido
 * no backoffice sob demanda APÓS a decisão INDEFERIDA, com a fundamentação da
 * ViabilityDecision + parecer da ficha. Mesmas garantias do TVL: PDF real no
 * disco NÃO público, linha auditada por emissão, download por URL temporária
 * assinada, gate emitir-tvl (403 auditado). Decisão não indeferida → 422.
 */
class IndeferimentoDocumentoTest extends TestCase
{
    use LazilyRefreshDatabase;

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
        $processo = $this->processoIndeferido();

        $this->actingAs($this->semEmitirTvl(), 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/indeferimento")
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);

        $this->assertDatabaseCount('indeferimento_documents', 0);
    }

    public function test_emite_documento_de_decisao_indeferida_com_fundamentacao(): void
    {
        $processo = $this->processoIndeferido();

        $response = $this->actingAs($this->analista(), 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/indeferimento")
            ->assertOk()
            ->assertJsonStructure(['document' => ['id', 'verification_code', 'generated_at'], 'download_url']);

        $this->assertDatabaseCount('indeferimento_documents', 1);

        $document = IndeferimentoDocument::query()->latest('id')->first();
        $this->assertNotSame('public', $document->disk);
        Storage::disk($document->disk)->assertExists($document->path);
        $this->assertStringStartsWith('%PDF', Storage::disk($document->disk)->get($document->path));
        $this->assertStringStartsWith('IND-', $document->verification_code);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'indeferimento-emitido',
            'result' => 'sucesso',
        ]);

        $this->assertNotEmpty($response->json('download_url'));
    }

    public function test_bloqueia_emissao_de_decisao_deferida_com_422(): void
    {
        $processo = $this->processoDeferido();

        $this->actingAs($this->analista(), 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/indeferimento")
            ->assertStatus(422);

        $this->assertDatabaseCount('indeferimento_documents', 0);
    }

    public function test_bloqueia_emissao_sem_decisao_com_422(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/indeferimento")
            ->assertStatus(422);

        $this->assertDatabaseCount('indeferimento_documents', 0);
    }

    public function test_download_com_url_assinada_valida_faz_streaming(): void
    {
        $processo = $this->processoIndeferido();
        $analista = $this->analista();

        $url = $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/indeferimento")
            ->assertOk()
            ->json('download_url');

        $download = $this->actingAs($analista, 'gestao')->get($url);

        $download->assertOk();
        $download->assertDownload();
        $this->assertStringStartsWith('%PDF', $download->streamedContent());
    }

    public function test_download_sem_assinatura_ou_expirado_e_403(): void
    {
        $decision = ViabilityDecision::factory()->indeferida()->create([
            'viability_request_id' => $this->processo()->id,
        ]);
        $document = app(IndeferimentoPdfService::class)->generate($decision, $this->analista());
        $analista = $this->analista();

        $this->actingAs($analista, 'gestao')
            ->get(route('gestao.processos.indeferimento.download', $document))
            ->assertForbidden();

        $expirada = URL::temporarySignedRoute(
            'gestao.processos.indeferimento.download',
            now()->subMinute(),
            ['indeferimentoDocument' => $document->id],
        );

        $this->actingAs($analista, 'gestao')->get($expirada)->assertForbidden();
    }
}
