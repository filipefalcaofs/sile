<?php

namespace Tests\Feature\Solicitacao;

use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ComprovanteProtocoloService;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Comprovante de protocolo em PDF (documento formal do cidadão): emitido sob
 * demanda no portal AUTENTICADO, só para solicitação protocolada, com código de
 * verificação determinístico (HMAC do protocolo — nada é inventado nem
 * armazenado). Autorização pela ViabilityRequestPolicy::view (dono/representado);
 * toda emissão é auditada (RN-002).
 */
class ComprovanteProtocoloTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    private function protocoladaDoUsuario(User $user): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->draft()->withPrimaryCnae()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);

        app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $user);

        return $solicitacao->refresh();
    }

    public function test_dono_baixa_comprovante_pdf_da_solicitacao_protocolada(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->protocoladaDoUsuario($user);

        $response = $this->actingAs($user)
            ->get("/portal/solicitacoes/{$solicitacao->id}/comprovante")
            ->assertOk();

        // PDF REAL gerado pelo dompdf (anti-fachada: começa com %PDF).
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));

        // A emissão é auditada (RN-002).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'comprovante-protocolo-emitido',
            'result' => 'sucesso',
        ]);
    }

    public function test_rascunho_nao_tem_comprovante(): void
    {
        $user = $this->portalUser();
        $rascunho = ViabilityRequest::factory()->draft()->withPrimaryCnae()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get("/portal/solicitacoes/{$rascunho->id}/comprovante")
            ->assertUnprocessable();
    }

    public function test_terceiro_recebe_403(): void
    {
        $dono = $this->portalUser();
        $solicitacao = $this->protocoladaDoUsuario($dono);
        $terceiro = $this->portalUser();

        $this->actingAs($terceiro)
            ->get("/portal/solicitacoes/{$solicitacao->id}/comprovante")
            ->assertForbidden();
    }

    public function test_codigo_de_verificacao_e_deterministico_e_vinculado_ao_protocolo(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->protocoladaDoUsuario($user);
        $service = app(ComprovanteProtocoloService::class);

        $codigo = $service->codigoVerificacao($solicitacao);

        // Determinístico: mesma solicitação, mesmo código (nada armazenado).
        $this->assertSame($codigo, $service->codigoVerificacao($solicitacao));
        $this->assertMatchesRegularExpression(
            '/^CMP-'.preg_quote((string) $solicitacao->protocol_number, '/').'-[a-f0-9]{10}$/',
            $codigo,
        );

        // Vinculado ao protocolo: outra solicitação, outro código.
        $outra = $this->protocoladaDoUsuario($user);
        $this->assertNotSame($codigo, $service->codigoVerificacao($outra));
    }
}
