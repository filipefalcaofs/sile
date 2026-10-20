<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\ProcessoQueryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Aba "Para conclusão" da caixa do setor: o apoio distribui (inclusive os que
 * já têm responsável) e o analista assume o processo, sem mexer na situação nem no prazo.
 */
class CaixaSetorParaConclusaoDistribuicaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function apoio(Sector $setor): User
    {
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $apoio->sectors()->attach($setor);

        return $apoio;
    }

    private function analista(Sector $setor): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach($setor);

        return $analista;
    }

    private function processoParaConclusao(Sector $setor, ?User $responsavel = null, AnalysisStatus $status = AnalysisStatus::ParaConclusao): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill([
            'sector_id' => $setor->id,
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => $status,
            'analysis_stage' => AnalysisStage::Analise,
            'assigned_user_id' => $responsavel?->id,
            'analysis_due_at' => now()->addDays(3)->startOfSecond(),
        ])->save();

        return $processo;
    }

    public function test_apoio_distribui_processo_para_conclusao_sem_responsavel(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoio($setor);
        $destino = $this->analista($setor);
        $processo = $this->processoParaConclusao($setor);
        $prazo = $processo->analysis_due_at;

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $destino->id,
            ])
            ->assertSessionMissing('error');

        $processo->refresh();
        $this->assertSame($destino->id, $processo->assigned_user_id);
        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->analysis_status);
        $this->assertTrue($prazo->equalTo($processo->analysis_due_at));
    }

    public function test_processo_distribuido_sai_da_aba_e_do_contador_e_fica_na_caixa_do_destinatario(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoio($setor);
        $destino = $this->analista($setor);
        $processo = $this->processoParaConclusao($setor);
        $outro = $this->processoParaConclusao($setor);

        $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?visao=para_conclusao')
            ->assertInertia(fn ($page) => $page->where('contadores.para_conclusao', 2));

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $destino->id,
            ])
            ->assertSessionMissing('error');

        $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?visao=para_conclusao')
            ->assertInertia(fn ($page) => $page
                ->where('contadores.para_conclusao', 1)
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $outro->id));

        $processo->refresh();
        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->analysis_status);
        $this->assertSame($destino->id, $processo->conclusao_encaminhada_para_user_id);
        $this->assertContains(
            $processo->id,
            app(ProcessoQueryService::class)->fila($destino)->pluck('viability_requests.id')->all(),
        );
    }

    public function test_apoio_redistribui_processo_para_conclusao_que_ja_tem_responsavel(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoio($setor);
        $anterior = $this->analista($setor);
        $destino = $this->analista($setor);
        $processo = $this->processoParaConclusao($setor, $anterior);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $destino->id,
            ])
            ->assertSessionMissing('error');

        $this->assertSame($destino->id, $processo->refresh()->assigned_user_id);
        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->analysis_status);
    }

    public function test_distribuir_continua_recusando_processo_ja_atribuido_fora_de_para_conclusao(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoio($setor);
        $anterior = $this->analista($setor);
        $destino = $this->analista($setor);
        $processo = $this->processoParaConclusao($setor, $anterior, AnalysisStatus::EmAnalise);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $destino->id,
            ])
            ->assertSessionHas('error');

        $this->assertSame($anterior->id, $processo->refresh()->assigned_user_id);
    }

    public function test_analista_assume_processo_para_conclusao_sem_responsavel(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analista($setor);
        $processo = $this->processoParaConclusao($setor);
        $prazo = $processo->analysis_due_at;

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/caixa-setor/{$processo->id}/assumir")
            ->assertSessionHas('status')
            ->assertSessionMissing('error');

        $processo->refresh();
        $this->assertSame($analista->id, $processo->assigned_user_id);
        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->analysis_status);
        $this->assertTrue($prazo->equalTo($processo->analysis_due_at));
    }

    public function test_analista_assume_processo_para_conclusao_que_ficou_com_o_responsavel_anterior(): void
    {
        $setor = Sector::factory()->create();
        $anterior = $this->analista($setor);
        $analista = $this->analista($setor);
        $processo = $this->processoParaConclusao($setor, $anterior);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/caixa-setor/{$processo->id}/assumir")
            ->assertSessionHas('status')
            ->assertSessionMissing('error');

        $this->assertSame($analista->id, $processo->refresh()->assigned_user_id);
    }

    public function test_analista_nao_assume_processo_em_analise_de_outro_analista(): void
    {
        $setor = Sector::factory()->create();
        $anterior = $this->analista($setor);
        $analista = $this->analista($setor);
        $processo = $this->processoParaConclusao($setor, $anterior, AnalysisStatus::EmAnalise);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/caixa-setor/{$processo->id}/assumir")
            ->assertSessionHas('error');

        $this->assertSame($anterior->id, $processo->refresh()->assigned_user_id);
    }

    public function test_apoio_nao_assume_processo(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoio($setor);
        $processo = $this->processoParaConclusao($setor);

        $this->actingAs($apoio, 'gestao')
            ->post("/gestao/caixa-setor/{$processo->id}/assumir")
            ->assertForbidden();

        $this->assertNull($processo->refresh()->assigned_user_id);
    }

    private function chefia(Sector $setor): User
    {
        $chefia = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $chefia->sectors()->attach($setor);

        return $chefia;
    }

    public function test_chefia_ve_na_aba_para_conclusao_os_processos_de_qualquer_setor(): void
    {
        $meuSetor = Sector::factory()->create();
        $outroSetor = Sector::factory()->create();
        $chefia = $this->chefia($meuSetor);
        $doOutroSetor = $this->processoParaConclusao($outroSetor);

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/caixa-setor?visao=para_conclusao')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contadores.para_conclusao', 1)
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $doOutroSetor->id)
                ->where('distribuiEmQualquerSetor', true));
    }

    public function test_chefia_continua_presa_ao_proprio_setor_nas_outras_abas(): void
    {
        $meuSetor = Sector::factory()->create();
        $outroSetor = Sector::factory()->create();
        $chefia = $this->chefia($meuSetor);
        $this->processoParaConclusao($outroSetor, null, AnalysisStatus::ParaDistribuir);

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contadores.para_distribuir', 0)
                ->where('distribuiEmQualquerSetor', false));
    }

    public function test_chefia_distribui_para_conclusao_de_outro_setor_a_analista_de_qualquer_setor(): void
    {
        $meuSetor = Sector::factory()->create();
        $setorDoProcesso = Sector::factory()->create();
        $setorDoDestino = Sector::factory()->create();
        $chefia = $this->chefia($meuSetor);
        $destino = $this->analista($setorDoDestino);
        $processo = $this->processoParaConclusao($setorDoProcesso);

        $this->actingAs($chefia, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $destino->id,
            ])
            ->assertSessionMissing('error');

        $processo->refresh();
        $this->assertSame($destino->id, $processo->assigned_user_id);
        $this->assertSame($setorDoDestino->id, $processo->sector_id);
        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->analysis_status);
    }

    public function test_chefia_nao_distribui_de_outro_setor_fora_da_aba_para_conclusao(): void
    {
        $meuSetor = Sector::factory()->create();
        $outroSetor = Sector::factory()->create();
        $chefia = $this->chefia($meuSetor);
        $destino = $this->analista($outroSetor);
        $processo = $this->processoParaConclusao($outroSetor, null, AnalysisStatus::ParaDistribuir);

        $this->actingAs($chefia, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $destino->id,
            ])
            ->assertSessionHas('error');

        $this->assertNull($processo->refresh()->assigned_user_id);
    }
}
