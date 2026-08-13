<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\DocumentRequirement;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comando solicitacao:protocolar (fechamento da Fase 8): exercita o
 * ProtocolarSolicitacaoService REAL sobre uma solicitação instruída, imprimindo
 * o número gerado e a transição rascunho→protocolada — evidência de ponta a
 * ponta para homologação manual, espelhando viabilidade:consultar /
 * louos:enquadrar.
 *
 * Sem fachada: documento obrigatório faltante BLOQUEIA com aviso e exit 1
 * (nenhum número consumido); dados mínimos ausentes idem; solicitação já
 * protocolada é transição inválida (exit 1). O sucesso sai com exit 0.
 */
class SolicitacaoProtocolarCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Rascunho COMPLETO (empresa, imóvel/polígono, área, CNAE principal) pronto
     * para protocolar — o created_by é o ator que o comando usa.
     */
    private function completeDraft(User $user): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'used_area_m2' => 120.0,
        ]);

        $cnae = Cnae::factory()->create(['code' => '4712100']);
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);

        return $solicitacao;
    }

    public function test_protocola_solicitacao_instruida_e_imprime_numero(): void
    {
        $solicitacao = $this->completeDraft($this->portalUser());

        $this->artisan('solicitacao:protocolar', ['solicitacao' => $solicitacao->id])
            ->expectsOutputToContain('PROTOCOLADA')
            ->expectsOutputToContain('VIA-')
            ->assertSuccessful();

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Protocolada, $solicitacao->status);
        $this->assertMatchesRegularExpression('/^VIA-\d{4}-\d{6}$/', (string) $solicitacao->protocol_number);
        $this->assertSame(1, $solicitacao->transitions()->where('to_status', ViabilityRequestStatus::Protocolada)->count());
    }

    public function test_bloqueia_e_falha_sem_documento_obrigatorio(): void
    {
        $solicitacao = $this->completeDraft($this->portalUser());
        $cnae = $solicitacao->cnaes()->first();
        $requisito = DocumentRequirement::factory()->required()->create();
        $requisito->cnaes()->attach($cnae->id);

        $this->artisan('solicitacao:protocolar', ['solicitacao' => $solicitacao->id])
            ->expectsOutputToContain($requisito->name)
            ->assertFailed();

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);
        $this->assertNull($solicitacao->protocol_number);
    }

    public function test_falha_sem_dados_minimos(): void
    {
        // Sem CNAE principal: bloqueio honesto (exit 1) antes de consumir número.
        $user = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'used_area_m2' => 120.0,
        ]);

        $this->artisan('solicitacao:protocolar', ['solicitacao' => $solicitacao->id])
            ->assertFailed();

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);
        $this->assertNull($solicitacao->protocol_number);
    }

    public function test_falha_para_solicitacao_ja_protocolada(): void
    {
        $user = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->protocoled()->withPrimaryCnae()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);
        $numeroAntes = $solicitacao->protocol_number;

        $this->artisan('solicitacao:protocolar', ['solicitacao' => $solicitacao->id])
            ->assertFailed();

        $solicitacao->refresh();
        $this->assertSame($numeroAntes, $solicitacao->protocol_number);
    }

    public function test_falha_para_solicitacao_inexistente(): void
    {
        $this->artisan('solicitacao:protocolar', ['solicitacao' => 999999])
            ->expectsOutputToContain('não encontrada')
            ->assertFailed();
    }
}
