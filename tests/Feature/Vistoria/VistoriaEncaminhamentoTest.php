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
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * O processo chega à ficha em para_distribuir (e segue encaminhado/analisar
     * antes de alguém marcar "Em análise" no dropdown). O botão de vistoria
     * tem de fazer o handoff a partir desses estados.
     *
     * @return array<string, array{0: AnalysisStatus}>
     */
    public static function eixosAbertosParaVistoria(): array
    {
        return [
            'para distribuir' => [AnalysisStatus::ParaDistribuir],
            'encaminhado' => [AnalysisStatus::Encaminhado],
            'analisar' => [AnalysisStatus::Analisar],
            'em análise' => [AnalysisStatus::EmAnalise],
            'para conclusão' => [AnalysisStatus::ParaConclusao],
            'convite respondido' => [AnalysisStatus::ConviteRespondido],
            'vistoriado' => [AnalysisStatus::Vistoriado],
        ];
    }

    #[DataProvider('eixosAbertosParaVistoria')]
    public function test_encaminhar_a_partir_do_eixo_aberto_faz_o_handoff(AnalysisStatus $origem): void
    {
        $setor = Sector::factory()->create();
        $setorVistoria = Sector::factory()->create();
        $analista = $this->analista();
        $processo = $this->processoEmAnalise($setor, $analista);
        $processo->forceFill(['analysis_status' => $origem])->save();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'Precisa de vistoria.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $fresh = $processo->refresh();
        $this->assertSame(AnalysisStatus::Vistoriar, $fresh->analysis_status);
        $this->assertSame($setorVistoria->id, $fresh->sector_id);
        $this->assertNull($fresh->assigned_user_id);
    }

    public function test_encaminhar_com_analise_concluida_e_recusado_com_mensagem(): void
    {
        $setor = Sector::factory()->create();
        $setorVistoria = Sector::factory()->create();
        $analista = $this->analista();
        $processo = $this->processoEmAnalise($setor, $analista);
        $processo->forceFill(['analysis_status' => AnalysisStatus::AnaliseConcluida])->save();

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'Precisa de vistoria.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo']);

        $this->assertSame(AnalysisStatus::AnaliseConcluida, $processo->refresh()->analysis_status);
        $this->assertSame($setor->id, $processo->sector_id);
        $this->assertSame(0, InspectionReferral::query()->count());
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

        // 2. Abrir o processo não o atribui: sem distribuição, a ficha não abre.
        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertForbidden();
        $this->assertNull($processo->refresh()->assigned_user_id);

        // O apoio distribui ao vistoriador; só então ele abre a ficha e conclui com o parecer.
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $vistoriador->id,
            ])
            ->assertSessionMissing('error');

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertOk();

        $this->assertSame($vistoriador->id, $processo->refresh()->assigned_user_id);

        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", [
                'parecer' => 'Área murada, 108 botijões de GLP; escola a 40 m.',
            ])
            ->assertOk();

        // 3. A conclusão da ficha deixa o processo Vistoriado, ainda com o
        //    vistoriador; o retorno à análise é um encaminhamento feito por ele.
        $fresh = $processo->refresh();
        $this->assertSame(AnalysisStatus::Vistoriado, $fresh->analysis_status);
        $this->assertSame($setorVistoria->id, $fresh->sector_id);
        $this->assertSame($vistoriador->id, $fresh->assigned_user_id);
        $this->assertSame(InspectionStatus::Concluida, Inspection::query()->sole()->status);

        // 4. Encaminhar para a Caixa do Setor: volta ao setor de origem, sem
        //    responsável, e segue Vistoriado; o setor distribui de novo.
        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar-ao-setor")
            ->assertRedirect()
            ->assertSessionMissing('error');

        $fresh = $processo->refresh();
        $this->assertSame(AnalysisStatus::Vistoriado, $fresh->analysis_status);
        $this->assertSame($setorAnalise->id, $fresh->sector_id);
        $this->assertNull($fresh->assigned_user_id);
        $this->assertNull($fresh->assigned_at);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'vistoria',
            'event' => 'vistoria-devolver',
        ]);

        $transicoes = $processo->analysisStatusTransitions()->get()
            ->map(fn ($transicao) => $transicao->to_status->value)
            ->all();
        $this->assertContains(AnalysisStatus::Vistoriado->value, $transicoes);
    }

    public function test_encaminhar_ao_setor_pode_designar_um_analista_do_setor_de_origem(): void
    {
        [$processo, $vistoriador, $setorAnalise] = $this->processoVistoriado();
        $designado = $this->analista();
        $designado->sectors()->attach($setorAnalise);
        $deOutroSetor = $this->analista();
        $deOutroSetor->sectors()->attach(Sector::factory()->create());

        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar-ao-setor", ['analista_id' => $deOutroSetor->id])
            ->assertSessionHas('error');
        $this->assertSame($vistoriador->id, $processo->refresh()->assigned_user_id);

        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar-ao-setor", ['analista_id' => $designado->id])
            ->assertSessionMissing('error');

        $fresh = $processo->refresh();
        $this->assertSame($setorAnalise->id, $fresh->sector_id);
        $this->assertSame($designado->id, $fresh->assigned_user_id);
        $this->assertSame(AnalysisStatus::Vistoriado, $fresh->analysis_status);
    }

    public function test_apos_a_devolucao_o_setor_distribui_o_processo_a_um_analista(): void
    {
        $setorAnalise = Sector::factory()->create();
        $setorVistoria = Sector::factory()->create();
        $analista = $this->analista();
        $analista->sectors()->attach($setorAnalise);
        $outraAnalista = $this->analista();
        $outraAnalista->sectors()->attach($setorAnalise);
        $vistoriador = $this->analista();
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();

        $processo = $this->processoEmAnalise($setorAnalise, $analista);
        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'Conferir a atividade no local.',
            ]);
        $processo->forceFill(['assigned_user_id' => $vistoriador->id])->save();
        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Atividade conforme.'])
            ->assertOk();
        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar-ao-setor")
            ->assertSessionMissing('error');

        $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertInertia(fn (Assert $page) => $page
                ->where('contadores.para_distribuir', 1)
                ->where('processos.data.0.id', $processo->id));

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $outraAnalista->id,
            ])
            ->assertSessionMissing('error');

        $fresh = $processo->refresh();
        $this->assertSame($outraAnalista->id, $fresh->assigned_user_id);
        $this->assertSame($setorAnalise->id, $fresh->sector_id);
        $this->assertSame(AnalysisStatus::Vistoriado, $fresh->analysis_status);
        $this->assertSame(InspectionStatus::Concluida, Inspection::query()->sole()->status);
    }

    /**
     * SPEC-05: em vistoria, o processo fica só na Caixa de Vistoria. Ele não
     * aparece na Caixa do Setor nem para o apoio, que vê todos os setores, nem
     * para quem é do setor de vistoria. Volta a uma única caixa após o retorno.
     */
    public function test_processo_em_vistoria_fica_so_na_caixa_de_vistoria(): void
    {
        $setorAnalise = Sector::factory()->create();
        $setorVistoria = Sector::factory()->create();
        $analista = $this->analista();
        $analista->sectors()->attach($setorAnalise);
        $membroDaVistoria = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $membroDaVistoria->sectors()->attach($setorVistoria);
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();

        $processo = $this->processoEmAnalise($setorAnalise, $analista);
        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'Conferir a atividade no local.',
            ])
            ->assertSessionMissing('error');

        foreach ([$apoio, $membroDaVistoria] as $usuario) {
            $this->actingAs($usuario, 'gestao')
                ->get('/gestao/caixa-setor')
                ->assertInertia(fn (Assert $page) => $page
                    ->where('contadores.para_distribuir', 0)
                    ->where('contadores.distribuidos', 0)
                    ->has('processos.data', 0));

            $this->actingAs($usuario, 'gestao')
                ->get('/gestao/vistorias')
                ->assertInertia(fn (Assert $page) => $page->where('vistorias.data.0.id', $processo->id));
        }

        $vistoriador = $this->analista();
        $processo->forceFill(['assigned_user_id' => $vistoriador->id])->save();
        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Atividade conforme.'])
            ->assertOk();

        // Ficha concluída, ainda não encaminhada: segue na vistoria.
        $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?visao=distribuidos')
            ->assertInertia(fn (Assert $page) => $page
                ->where('contadores.para_distribuir', 0)
                ->where('contadores.distribuidos', 0)
                ->has('processos.data', 0));

        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar-ao-setor")
            ->assertSessionMissing('error');

        $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertInertia(fn (Assert $page) => $page
                ->where('contadores.para_distribuir', 1)
                ->where('contadores.distribuidos', 0)
                ->where('processos.data.0.id', $processo->id));
    }

    public function test_bloqueio_do_encaminhamento_informa_a_situacao_real_do_processo(): void
    {
        $vistoriador = $this->analista();
        $processo = $this->processoEmAnalise(Sector::factory()->create(), $vistoriador);
        Inspection::factory()->create([
            'viability_request_id' => $processo->id,
            'vistoriador_user_id' => $vistoriador->id,
            'status' => InspectionStatus::Concluida,
            'concluded_at' => now(),
        ]);

        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar-ao-setor")
            ->assertSessionHas('error', fn (string $mensagem): bool => str_contains($mensagem, 'Em análise')
                && ! str_contains($mensagem, 'depois que a ficha de vistoria é concluída'));
    }

    public function test_processo_que_ja_voltou_a_caixa_do_setor_informa_que_foi_devolvido(): void
    {
        // Caso real da homologação (processo #17): a ficha segue concluída, o
        // processo já voltou ao setor de origem e a análise foi retomada.
        $setor = Sector::factory()->create();
        $vistoriador = $this->analista();
        $processo = $this->processoEmAnalise($setor, null);
        InspectionReferral::query()->forceCreate([
            'viability_request_id' => $processo->id,
            'encaminhado_por_user_id' => $vistoriador->id,
            'setor_origem_id' => $setor->id,
            'analista_origem_user_id' => $vistoriador->id,
            'setor_vistoria_id' => Sector::factory()->create()->id,
            'motivo' => 'Conferir a atividade no local.',
        ]);
        Inspection::factory()->concluida()->create([
            'viability_request_id' => $processo->id,
            'vistoriador_user_id' => $vistoriador->id,
        ]);

        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar-ao-setor")
            ->assertSessionHas('error', fn (string $mensagem): bool => str_contains($mensagem, 'já foi devolvido à Caixa do Setor')
                && str_contains($mensagem, 'Em análise'));
    }

    public function test_ficha_expoe_a_situacao_de_analise_para_a_tela_so_oferecer_o_encaminhamento_em_vistoriado(): void
    {
        $vistoriador = $this->analista();
        $processo = $this->processoEmAnalise(Sector::factory()->create(), $vistoriador);
        $processo->forceFill(['analysis_status' => AnalysisStatus::Vistoriado])->save();
        Inspection::factory()->concluida()->create([
            'viability_request_id' => $processo->id,
            'vistoriador_user_id' => $vistoriador->id,
        ]);

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertInertia(fn (Assert $page) => $page->where('processo.analysis_status', 'vistoriado'));
    }

    public function test_encaminhar_ao_setor_vale_a_qualquer_momento_sem_ficha_concluida(): void
    {
        $setorAnalise = Sector::factory()->create();
        $setorVistoria = Sector::factory()->create();
        $analista = $this->analista();
        $analista->sectors()->attach($setorAnalise);
        $vistoriador = $this->analista();
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();

        $processo = $this->processoEmAnalise($setorAnalise, $analista);
        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'Conferir a atividade no local.',
            ]);
        $processo->forceFill(['assigned_user_id' => $vistoriador->id])->save();
        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");

        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar-ao-setor")
            ->assertSessionMissing('error');

        $fresh = $processo->refresh();
        $this->assertSame($setorAnalise->id, $fresh->sector_id);
        $this->assertNull($fresh->assigned_user_id);
        $this->assertSame(AnalysisStatus::ParaDistribuir, $fresh->analysis_status);

        $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertInertia(fn (Assert $page) => $page
                ->where('contadores.para_distribuir', 1)
                ->where('processos.data.0.id', $processo->id));
        $this->actingAs($apoio, 'gestao')
            ->get('/gestao/vistorias')
            ->assertInertia(fn (Assert $page) => $page->has('vistorias.data', 0));

        // E volta à vistoria quando o setor encaminha de novo: sai da Caixa do Setor.
        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'Nova conferência.',
            ])
            ->assertSessionMissing('error');
        $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertInertia(fn (Assert $page) => $page->where('contadores.para_distribuir', 0));
        $this->actingAs($apoio, 'gestao')
            ->get('/gestao/vistorias')
            ->assertInertia(fn (Assert $page) => $page->has('vistorias.data', 1));
    }

    public function test_encaminhar_ao_setor_sem_origem_registrada_devolve_sem_responsavel_para_distribuir(): void
    {
        $setor = Sector::factory()->create();
        $vistoriador = $this->analista();
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::Vistoriar,
            'sector_id' => $setor->id,
            'assigned_user_id' => $vistoriador->id,
        ])->save();
        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Parecer da vistoria.'])
            ->assertOk();

        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar-ao-setor")
            ->assertSessionMissing('error');

        $fresh = $processo->refresh();
        $this->assertSame($setor->id, $fresh->sector_id);
        $this->assertNull($fresh->assigned_user_id);
        $this->assertSame(AnalysisStatus::Vistoriado, $fresh->analysis_status);
    }

    public function test_vistoriado_com_referral_sem_origem_sai_da_caixa_de_vistoria_ao_ir_para_a_caixa_do_setor(): void
    {
        $setor = Sector::factory()->create();
        $vistoriador = $this->analista();
        $vistoriador->sectors()->attach($setor);
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::Vistoriar,
            'sector_id' => $setor->id,
            'assigned_user_id' => $vistoriador->id,
        ])->save();
        InspectionReferral::create([
            'viability_request_id' => $processo->id,
            'encaminhado_por_user_id' => $vistoriador->id,
            'setor_origem_id' => null,
            'setor_vistoria_id' => $setor->id,
            'motivo' => 'Conferir no local.',
        ]);
        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Parecer da vistoria.'])
            ->assertOk();

        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar-ao-setor")
            ->assertSessionMissing('error');

        $this->assertFalse(ViabilityRequest::query()->naCaixaDeVistoria()->whereKey($processo->id)->exists());
    }

    public function test_processo_vistoriado_na_caixa_do_setor_vai_a_analista_e_nao_a_vistoriador(): void
    {
        $setor = Sector::factory()->create();
        $analista = User::factory()->withAcceptedLgpdTerm()->create();
        $analista->givePermissionTo(['acessar-gestao', 'analisar-processos']);
        $analista->sectors()->attach($setor);
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::Vistoriado,
            'sector_id' => $setor->id,
        ])->save();

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionMissing('error');

        $this->assertSame($analista->id, $processo->refresh()->assigned_user_id);
        $this->assertSame(AnalysisStatus::Vistoriado, $processo->analysis_status);
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
            'assigned_user_id' => $vistoriador->id,
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
        $processo->refresh()->forceFill(['assigned_user_id' => $vistoriador->id])->save();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Parecer da vistoria.']);

        $this->assertSame(AnalysisStatus::Vistoriado, $processo->refresh()->analysis_status);

        $this->actingAs($vistoriador, 'gestao')
            ->get('/gestao/vistorias?situacao=concluida')
            ->assertInertia(fn (Assert $page) => $page
                ->has('vistorias.data', 1)
                ->where('vistorias.data.0.id', $processo->id)
                ->where('vistorias.data.0.situacao', 'concluida'));
    }

    /**
     * @return array{0: ViabilityRequest, 1: User, 2: Sector}
     */
    private function processoVistoriado(): array
    {
        $setorAnalise = Sector::factory()->create();
        $setorVistoria = Sector::factory()->create();
        $analista = $this->analista();
        $analista->sectors()->attach($setorAnalise);
        $vistoriador = $this->analista();

        $processo = $this->processoEmAnalise($setorAnalise, $analista);
        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'Conferir a atividade no local.',
            ]);
        $processo->forceFill(['assigned_user_id' => $vistoriador->id])->save();
        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Atividade conforme.'])
            ->assertOk();

        return [$processo, $vistoriador, $setorAnalise];
    }
}
