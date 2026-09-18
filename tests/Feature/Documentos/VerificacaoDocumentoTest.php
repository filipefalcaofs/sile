<?php

namespace Tests\Feature\Documentos;

use App\Models\IndeferimentoDocument;
use App\Models\TvlDocument;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ComprovanteProtocoloService;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Verificação PÚBLICA de autenticidade de documentos (TVL, indeferimento e
 * comprovante de protocolo) pelo código de verificação — SEM login. Responde
 * SÓ tipo do documento, protocolo e data de referência (payload mínimo, LGPD:
 * nunca empresa/CNPJ/endereço). Código inexistente ou adulterado → inválido
 * (nunca um "válido" inventado). Toda consulta é auditada com IP (RN-002).
 */
class VerificacaoDocumentoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function documentoTvl(): TvlDocument
    {
        $decision = ViabilityDecision::factory()->create([
            'viability_request_id' => ViabilityRequest::factory()->create([
                'protocol_number' => 'VIA-2026-000100',
                'protocoled_at' => now(),
            ])->id,
        ]);

        return TvlDocument::factory()->create(['viability_decision_id' => $decision->id]);
    }

    public function test_codigo_tvl_valido_confirma_autenticidade_sem_expor_dados_sensiveis(): void
    {
        $documento = $this->documentoTvl();

        $this->get("/verificar-documento/{$documento->verification_code}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('verificacao-documento', false)
                ->where('resultado.valido', true)
                ->where('resultado.tipo', 'Termo de Viabilidade de Localização (TVL)')
                ->where('resultado.protocolo', 'VIA-2026-000100')
                ->has('resultado.data')
                ->missing('resultado.empresa')
                ->missing('resultado.cnpj'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'documentos',
            'event' => 'verificacao-documento-publica',
            'result' => 'sucesso',
        ]);
    }

    public function test_codigo_indeferimento_valido_confirma_autenticidade(): void
    {
        $decision = ViabilityDecision::factory()->indeferida()->create([
            'viability_request_id' => ViabilityRequest::factory()->create([
                'protocol_number' => 'VIA-2026-000200',
                'protocoled_at' => now(),
            ])->id,
        ]);
        $documento = IndeferimentoDocument::factory()->create(['viability_decision_id' => $decision->id]);

        $this->get("/verificar-documento/{$documento->verification_code}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('resultado.valido', true)
                ->where('resultado.tipo', 'Documento de Indeferimento')
                ->where('resultado.protocolo', 'VIA-2026-000200'));
    }

    public function test_codigo_de_comprovante_valido_confirma_autenticidade(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
        $solicitacao = ViabilityRequest::factory()->draft()->withPrimaryCnae()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);
        app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $user);

        $codigo = app(ComprovanteProtocoloService::class)->codigoVerificacao($solicitacao->refresh());

        $this->get("/verificar-documento/{$codigo}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('resultado.valido', true)
                ->where('resultado.tipo', 'Comprovante de Protocolo')
                ->where('resultado.protocolo', $solicitacao->protocol_number));
    }

    public function test_codigo_de_comprovante_adulterado_e_invalido(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
        $solicitacao = ViabilityRequest::factory()->draft()->withPrimaryCnae()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);
        app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $user);

        $codigo = app(ComprovanteProtocoloService::class)->codigoVerificacao($solicitacao->refresh());
        $adulterado = substr($codigo, 0, -10).'0000000000';

        $this->get("/verificar-documento/{$adulterado}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('resultado.valido', false));
    }

    public function test_codigo_inexistente_e_invalido(): void
    {
        $this->get('/verificar-documento/TVL-00000000000000000000000000')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('resultado.valido', false));
    }
}
