<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Resposta da pendência pelo portal (HU-084 — "Minhas solicitações"): só o dono
 * (ou representante, mecanismo da Fase 1) acessa e responde a própria pendência;
 * responder reabre a análise (em_pendencia→em_analise). Terceiro → 403 auditado
 * (CA-04). Pendência de outra solicitação → 404 (anti-IDOR). Já respondida →
 * aviso comunicado (CA-03), nada muda. Anti-fachada: a resposta executa de
 * verdade (estado real + auditoria), não há etapa fingida.
 */
class PendenciaRespostaPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Cidadão habilitado a navegar no portal (papel + termo LGPD aceito).
     */
    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Solicitação do dono parada em em_pendencia (caminho real do ciclo). Gera um
     * protocolo ÚNICO por solicitação (evita a colisão do protocoled() hardcoded
     * — armadilha herdada do 10-02 quando há mais de uma solicitação no teste).
     */
    private function emPendenciaDoDono(User $owner): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'requester_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
        ]);

        $request->forceFill([
            'status' => ViabilityRequestStatus::EmPendencia,
            'protocol_number' => 'VIA-'.now()->year.'-'.str_pad((string) $request->id, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now(),
        ])->save();

        return $request->refresh();
    }

    public function test_dono_responde_e_reabre_a_analise(): void
    {
        // CA-01: o dono responde a pendência pelo portal → respondida + análise
        // reaberta (em_analise), com flash de sucesso.
        $owner = $this->portalUser();
        $request = $this->emPendenciaDoDono($owner);
        $pendency = AnalysisPendency::factory()->create([
            'viability_request_id' => $request->id,
            'status' => AnalysisPendencyStatus::Aberta,
        ]);

        $this->actingAs($owner)
            ->from(route('portal.solicitacoes.pendencias', $request))
            ->post(route('portal.solicitacoes.pendencias.responder', [$request, $pendency]), [
                'response' => 'Segue o IPTU atualizado conforme solicitado.',
            ])
            ->assertRedirect(route('portal.solicitacoes.show', $request))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $pendency->refresh();
        $this->assertSame(AnalysisPendencyStatus::Respondida, $pendency->status);
        $this->assertSame('Segue o IPTU atualizado conforme solicitado.', $pendency->response);
        $this->assertNotNull($pendency->responded_at);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->refresh()->status);
    }

    public function test_terceiro_nao_responde_e_e_403_auditado(): void
    {
        // CA-04: terceiro não responde pendência de outro (403 auditado
        // globalmente) e nada muda.
        $owner = $this->portalUser();
        $stranger = $this->portalUser();
        $request = $this->emPendenciaDoDono($owner);
        $pendency = AnalysisPendency::factory()->create([
            'viability_request_id' => $request->id,
            'status' => AnalysisPendencyStatus::Aberta,
        ]);

        $this->actingAs($stranger)
            ->post(route('portal.solicitacoes.pendencias.responder', [$request, $pendency]), [
                'response' => 'Tentando responder pendência de terceiro.',
            ])
            ->assertForbidden();

        $this->assertSame(AnalysisPendencyStatus::Aberta, $pendency->refresh()->status);
        $this->assertSame(ViabilityRequestStatus::EmPendencia, $request->refresh()->status);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
        ]);
    }

    public function test_pendencia_de_outra_solicitacao_da_404(): void
    {
        // Anti-IDOR: a pendência precisa pertencer à solicitação da rota.
        $owner = $this->portalUser();
        $requestA = $this->emPendenciaDoDono($owner);

        $requestB = $this->emPendenciaDoDono($owner);
        $pendencyB = AnalysisPendency::factory()->create([
            'viability_request_id' => $requestB->id,
            'status' => AnalysisPendencyStatus::Aberta,
        ]);

        $this->actingAs($owner)
            ->post(route('portal.solicitacoes.pendencias.responder', [$requestA, $pendencyB]), [
                'response' => 'Resposta cruzada indevida.',
            ])
            ->assertNotFound();

        $this->assertSame(AnalysisPendencyStatus::Aberta, $pendencyB->refresh()->status);
    }

    public function test_pendencia_ja_respondida_vira_aviso(): void
    {
        // CA-03: responder uma pendência já respondida (processo fora de
        // em_pendencia) é bloqueado com aviso comunicado — nada muda.
        $owner = $this->portalUser();
        $request = ViabilityRequest::factory()->protocoled()->withPrimaryCnae()->create([
            'requester_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
        ]);
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        $pendency = AnalysisPendency::factory()->respondida()->create([
            'viability_request_id' => $request->id,
        ]);

        $this->actingAs($owner)
            ->from(route('portal.solicitacoes.pendencias', $request))
            ->post(route('portal.solicitacoes.pendencias.responder', [$request, $pendency]), [
                'response' => 'Resposta duplicada.',
            ])
            ->assertRedirect(route('portal.solicitacoes.pendencias', $request))
            ->assertSessionHas('error');

        $this->assertSame(AnalysisPendencyStatus::Respondida, $pendency->refresh()->status);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->refresh()->status);
    }

    public function test_show_lista_a_pendencia_aberta(): void
    {
        // O dono abre a página de resposta e vê a pendência aberta da própria
        // solicitação (Inertia component + props).
        $owner = $this->portalUser();
        $request = $this->emPendenciaDoDono($owner);
        $pendency = AnalysisPendency::factory()->create([
            'viability_request_id' => $request->id,
            'status' => AnalysisPendencyStatus::Aberta,
            'description' => 'Envie o contrato de locação assinado.',
        ]);

        $this->actingAs($owner)
            ->get(route('portal.solicitacoes.pendencias', $request))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/solicitacoes/pendencias')
                ->has('pendencias', 1)
                ->where('pendencias.0.id', $pendency->id)
                ->where('pendencias.0.description', 'Envie o contrato de locação assinado.')
            );
    }

    public function test_terceiro_nao_ve_a_pagina_de_pendencias(): void
    {
        // CA-04: terceiro não acessa a página de pendências de outro (403).
        $owner = $this->portalUser();
        $stranger = $this->portalUser();
        $request = $this->emPendenciaDoDono($owner);

        $this->actingAs($stranger)
            ->get(route('portal.solicitacoes.pendencias', $request))
            ->assertForbidden();
    }

    public function test_resposta_e_obrigatoria(): void
    {
        // A resposta é obrigatória: sem ela → erro de validação, nada muda.
        $owner = $this->portalUser();
        $request = $this->emPendenciaDoDono($owner);
        $pendency = AnalysisPendency::factory()->create([
            'viability_request_id' => $request->id,
            'status' => AnalysisPendencyStatus::Aberta,
        ]);

        $this->actingAs($owner)
            ->from(route('portal.solicitacoes.pendencias', $request))
            ->post(route('portal.solicitacoes.pendencias.responder', [$request, $pendency]), [])
            ->assertRedirect(route('portal.solicitacoes.pendencias', $request))
            ->assertSessionHasErrors('response');

        $this->assertSame(AnalysisPendencyStatus::Aberta, $pendency->refresh()->status);
        $this->assertSame(ViabilityRequestStatus::EmPendencia, $request->refresh()->status);
    }
}
