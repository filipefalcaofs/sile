<?php

namespace Tests\Feature\Vistoria;

use App\Enums\AnalysisStatus;
use App\Enums\InspectionStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Inspection;
use App\Models\InspectionReferral;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Encaminhamento à vistoria como handoff REAL: o analista envia o processo à
 * caixa do setor de vistoria (setor obrigatório), o eixo operacional vai a
 * Vistoriar pela state machine e o responsável é desatribuído — o processo
 * cai em "Para distribuir" para o apoio distribuir a um vistoriador. A
 * vistoria COMPÕE o processo (sem número novo): a origem (setor/analista) é
 * registrada e a conclusão da ficha devolve o processo à análise de origem.
 */
class VistoriaEncaminhamentoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function processoEmAnalise(Sector $setor, ?User $responsavel): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create();

        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
            'sector_id' => $setor->id,
            'assigned_user_id' => $responsavel?->id,
            'assigned_at' => $responsavel !== null ? now() : null,
        ])->save();

        return $processo;
    }

    public function test_encaminhar_faz_o_handoff_para_a_caixa_do_setor_de_vistoria(): void
    {
        $setorAnalise = Sector::factory()->create();
        $setorVistoria = Sector::factory()->create();
        $analista = $this->analista();
        $processo = $this->processoEmAnalise($setorAnalise, $analista);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'Atividade de alto risco — armazenamento de GLP.',
            ])
            ->assertRedirect();

        $fresh = $processo->refresh();

        // Handoff: eixo operacional em vistoria, setor de vistoria, SEM
        // responsável — cai em "Para distribuir" da caixa do setor de vistoria.
        $this->assertSame(AnalysisStatus::Vistoriar, $fresh->analysis_status);
        $this->assertSame($setorVistoria->id, $fresh->sector_id);
        $this->assertNull($fresh->assigned_user_id);

        // A vistoria compõe o processo: origem registrada para o retorno.
        $referral = InspectionReferral::query()->sole();
        $this->assertSame($processo->id, $referral->viability_request_id);
        $this->assertSame($setorAnalise->id, $referral->setor_origem_id);
        $this->assertSame($analista->id, $referral->analista_origem_user_id);
        $this->assertSame($setorVistoria->id, $referral->setor_vistoria_id);
        $this->assertSame($analista->id, $referral->encaminhado_por_user_id);

        // Timeline do eixo operacional + auditoria (RN-002).
        $this->assertTrue(
            $processo->analysisStatusTransitions()
                ->where('to_status', AnalysisStatus::Vistoriar->value)
                ->where('actor_user_id', $analista->id)
                ->exists()
        );
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'vistoria',
            'event' => 'vistoria-encaminhar',
        ]);
    }

    public function test_encaminhar_exige_setor_de_vistoria_e_motivo(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analista();
        $processo = $this->processoEmAnalise($setor, $analista);

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/encaminhar", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['setor_vistoria_id', 'motivo']);

        $this->assertSame(AnalysisStatus::EmAnalise, $processo->refresh()->analysis_status);
        $this->assertSame(0, InspectionReferral::query()->count());
    }

    public function test_encaminhar_fora_do_eixo_em_analise_e_recusado(): void
    {
        $setor = Sector::factory()->create();
        $setorVistoria = Sector::factory()->create();
        $analista = $this->analista();
        $processo = $this->processoEmAnalise($setor, $analista);
        $processo->forceFill(['analysis_status' => AnalysisStatus::Analisar])->save();

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'Precisa de vistoria.',
            ])
            ->assertUnprocessable();

        $this->assertSame(AnalysisStatus::Analisar, $processo->refresh()->analysis_status);
        $this->assertSame($setor->id, $processo->sector_id);
    }

    public function test_encaminhar_exige_permissao_de_analise(): void
    {
        $setor = Sector::factory()->create();
        $setorVistoria = Sector::factory()->create();
        $apoio = User::factory()->withAcceptedLgpdTerm()->create();
        $apoio->assignRole('apoio');
        $processo = $this->processoEmAnalise($setor, null);

        $this->actingAs($apoio, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'Precisa de vistoria.',
            ])
            ->assertForbidden();
    }

    public function test_fluxo_completo_distribuicao_e_retorno_automatico_a_origem(): void
    {
        $setorAnalise = Sector::factory()->create();
        $setorVistoria = Sector::factory()->create();
        $analista = $this->analista();
        $vistoriador = $this->analista();
        $vistoriador->sectors()->attach($setorVistoria);

        $processo = $this->processoEmAnalise($setorAnalise, $analista);

        // 1. Analista encaminha → handoff para a caixa do setor de vistoria.
        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'GLP de alto risco.',
            ])
            ->assertRedirect();

        // 2. Vistoriador abre a ficha (processo sem responsável → ele assume
        //    o processo junto com a ficha) e conclui com o parecer.
        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertOk();

        $this->assertSame($vistoriador->id, $processo->refresh()->assigned_user_id);

        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", [
                'parecer' => 'Área murada, 108 botijões de GLP; escola a 40 m.',
            ])
            ->assertOk();

        // 3. Retorno automático: processo de volta ao setor e ao analista de
        //    origem, eixo operacional em EmAnalise, ficha concluída.
        $fresh = $processo->refresh();
        $this->assertSame(AnalysisStatus::EmAnalise, $fresh->analysis_status);
        $this->assertSame($setorAnalise->id, $fresh->sector_id);
        $this->assertSame($analista->id, $fresh->assigned_user_id);
        $this->assertSame(InspectionStatus::Concluida, Inspection::query()->sole()->status);

        $transicoes = $processo->analysisStatusTransitions()->get()
            ->map(fn ($transicao) => $transicao->to_status->value)
            ->all();
        $this->assertContains(AnalysisStatus::Vistoriado->value, $transicoes);
    }

    public function test_concluir_ficha_sem_encaminhamento_registrado_permanece_vistoriado(): void
    {
        // Vistoria marcada manualmente pelo dropdown (sem referral): o retorno
        // automático não se aplica — o analista devolve manualmente.
        $vistoriador = $this->analista();
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::Vistoriar,
        ])->save();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Parecer da vistoria.'])
            ->assertOk();

        $this->assertSame(AnalysisStatus::Vistoriado, $processo->refresh()->analysis_status);
    }

    public function test_consulta_mantem_concluida_apos_o_retorno_a_analise(): void
    {
        // Universo da consulta: eixo de vistoria OU processo com ficha — a
        // vistoria concluída e devolvida continua visível em "Concluídas".
        $setorAnalise = Sector::factory()->create();
        $setorVistoria = Sector::factory()->create();
        $analista = $this->analista();
        $vistoriador = $this->analista();

        $processo = $this->processoEmAnalise($setorAnalise, $analista);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'GLP de alto risco.',
            ]);

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Parecer da vistoria.']);

        $this->assertSame(AnalysisStatus::EmAnalise, $processo->refresh()->analysis_status);

        $this->actingAs($vistoriador, 'gestao')
            ->get('/gestao/vistorias?situacao=concluida')
            ->assertInertia(fn (Assert $page) => $page
                ->has('vistorias.data', 1)
                ->where('vistorias.data.0.id', $processo->id)
                ->where('vistorias.data.0.situacao', 'concluida'));
    }
}
