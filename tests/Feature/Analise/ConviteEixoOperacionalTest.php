<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\IndeferirPorPrazoConviteService;
use App\Services\Analise\PendenciaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * O status de tramitação acompanha o convite: Em convite ao abrir, Convite
 * respondido, Convite cancelado e Prazo para convite expirado. Quando o
 * requerente responde, o processo sobe ao topo da caixa de entrada.
 */
class ConviteEixoOperacionalTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function emAnalise(?AnalysisStatus $eixo = AnalysisStatus::EmAnalise): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => 'VIA-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        ]);
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => $eixo,
        ])->save();

        return $request->refresh();
    }

    public function test_abrir_convite_muda_o_status_para_em_convite(): void
    {
        Notification::fake();
        $analista = User::factory()->create();
        $request = $this->emAnalise();

        app(PendenciaService::class)->abrir($request, $analista, 'Envie o IPTU.');

        $this->assertSame(AnalysisStatus::EmConvite, $request->fresh()->analysis_status);
    }

    public function test_abrir_convite_com_eixo_ainda_nao_iniciado_tambem_marca_em_convite(): void
    {
        Notification::fake();
        $analista = User::factory()->create();
        $request = $this->emAnalise(null);

        app(PendenciaService::class)->abrir($request, $analista, 'Envie o IPTU.');

        $this->assertSame(AnalysisStatus::EmConvite, $request->fresh()->analysis_status);
    }

    public function test_quem_abre_o_convite_em_processo_de_outro_analista_passa_a_ser_o_responsavel(): void
    {
        Notification::fake();
        $analistaOriginal = User::factory()->create();
        $quemConclui = User::factory()->create();
        $request = $this->emAnalise(AnalysisStatus::ParaConclusao);
        $request->forceFill(['assigned_user_id' => $analistaOriginal->id, 'assigned_at' => now()->subDay()])->save();

        app(PendenciaService::class)->abrir($request->fresh(), $quemConclui, 'Envie o IPTU.');

        $fresh = $request->fresh();
        $this->assertSame($quemConclui->id, $fresh->assigned_user_id);
        $this->assertTrue($fresh->assigned_at->isToday());
        $this->assertDatabaseHas('activity_log', [
            'event' => 'assumir',
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
        ]);
    }

    public function test_analista_responsavel_que_abre_o_convite_continua_responsavel(): void
    {
        Notification::fake();
        $analista = User::factory()->create();
        $request = $this->emAnalise();
        $atribuidoEm = now()->subDay()->startOfSecond();
        $request->forceFill(['assigned_user_id' => $analista->id, 'assigned_at' => $atribuidoEm])->save();

        app(PendenciaService::class)->abrir($request->fresh(), $analista, 'Envie o IPTU.');

        $fresh = $request->fresh();
        $this->assertSame($analista->id, $fresh->assigned_user_id);
        $this->assertTrue($atribuidoEm->equalTo($fresh->assigned_at));
        $this->assertDatabaseMissing('activity_log', ['event' => 'assumir', 'subject_id' => $request->id]);
    }

    public function test_resposta_do_requerente_marca_convite_respondido(): void
    {
        Notification::fake();
        $analista = User::factory()->create();
        $request = $this->emAnalise();
        $pendency = app(PendenciaService::class)->abrir($request, $analista, 'Envie o IPTU.');

        app(PendenciaService::class)->responder($pendency->fresh(), 'IPTU anexado.');

        $this->assertSame(AnalysisStatus::ConviteRespondido, $request->fresh()->analysis_status);
    }

    public function test_cancelar_convite_marca_convite_cancelado(): void
    {
        Notification::fake();
        $analista = User::factory()->create();
        $request = $this->emAnalise();
        $pendency = app(PendenciaService::class)->abrir($request, $analista, 'Envie o IPTU.');

        app(PendenciaService::class)->cancelar($pendency->fresh(), $analista, 'Documento já constava do processo.');

        $this->assertSame(AnalysisStatus::ConviteCancelado, $request->fresh()->analysis_status);
    }

    public function test_prazo_do_convite_expirado_marca_prazo_para_convite_expirado_e_indefere(): void
    {
        $request = $this->emAnalise(AnalysisStatus::EmConvite);
        $request->forceFill(['status' => ViabilityRequestStatus::EmPendencia])->save();
        $pendency = AnalysisPendency::factory()->for($request->fresh(), 'viabilityRequest')->create([
            'status' => AnalysisPendencyStatus::Aberta,
            'due_at' => now()->subHour(),
        ]);

        $decision = app(IndeferirPorPrazoConviteService::class)->indeferir($request->fresh(), $pendency);

        $fresh = $request->fresh();
        $this->assertSame(ViabilityRequestStatus::Indeferida, $fresh->status);
        $this->assertSame(AnalysisStatus::ConviteExpirado, $fresh->analysis_status);
        $this->assertSame(
            "Processo indeferido pelo não atendimento ao convite no prazo estabelecido em consonância com Lei Federal nº 11.598/2007 e Resolução CGSIM nº 61/2020.\n\nRecomenda-se a abertura de novo processo contendo toda a documentação/ esclarecimento solicitado(s).",
            $decision->reason,
        );
    }

    public function test_convite_respondido_sobe_ao_topo_da_caixa_de_entrada_e_vai_em_destaque(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach(Sector::factory()->create());

        $maisUrgente = $this->emAnalise();
        $maisUrgente->forceFill(['assigned_user_id' => $analista->id, 'analysis_due_at' => now()->addDay()])->save();
        $respondido = $this->emAnalise(AnalysisStatus::ConviteRespondido);
        $respondido->forceFill(['assigned_user_id' => $analista->id, 'analysis_due_at' => now()->addDays(20)])->save();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/processos/fila')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('processos.data.0.id', $respondido->id)
                ->where('processos.data.0.analysis_status', 'convite_respondido')
                ->where('processos.data.1.id', $maisUrgente->id));
    }
}
