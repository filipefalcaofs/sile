<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Encaminhar para Chefia": o analista pode indicar, sem obrigação, quem da chefia
 * deve concluir. A indicação é só uma sugestão para o Apoio: o processo vai
 * sempre para a aba Para conclusão, e quem distribui é o Apoio.
 */
class IndicacaoParaConclusaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function processoEmAnalise(Sector $setor, User $analista): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill([
            'sector_id' => $setor->id,
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
            'assigned_user_id' => $analista->id,
            'analysis_due_at' => now()->addDays(2),
        ])->save();

        return $processo;
    }

    private function terceirizado(Sector $setor): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach($setor);

        return $analista;
    }

    public function test_indicacao_fica_registrada_e_o_processo_vai_para_a_aba_sem_destinatario(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->terceirizado($setor);
        $coordenador = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $processo = $this->processoEmAnalise($setor, $analista);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", [
                'status' => 'para_conclusao',
                'indicado_user_id' => $coordenador->id,
            ])
            ->assertSessionHasNoErrors();

        $processo->refresh();
        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->analysis_status);
        $this->assertSame($coordenador->id, $processo->conclusao_indicada_para_user_id);
        $this->assertNull($processo->conclusao_encaminhada_para_user_id);
    }

    public function test_indicacao_e_opcional(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->terceirizado($setor);
        $processo = $this->processoEmAnalise($setor, $analista);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'para_conclusao'])
            ->assertSessionHasNoErrors();

        $processo->refresh();
        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->analysis_status);
        $this->assertNull($processo->conclusao_indicada_para_user_id);
    }

    public function test_so_aceita_indicar_chefe_de_setor_subcoordenador_ou_coordenador(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->terceirizado($setor);
        $colega = $this->terceirizado($setor);
        $processo = $this->processoEmAnalise($setor, $analista);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", [
                'status' => 'para_conclusao',
                'indicado_user_id' => $colega->id,
            ])
            ->assertSessionHasErrors('indicado_user_id');

        $processo->refresh();
        $this->assertSame(AnalysisStatus::EmAnalise, $processo->analysis_status);
        $this->assertNull($processo->conclusao_indicada_para_user_id);
    }

    public function test_aba_para_conclusao_do_apoio_mostra_quem_foi_indicado(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->terceirizado($setor);
        $coordenador = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $apoio->sectors()->attach($setor);
        $processo = $this->processoEmAnalise($setor, $analista);
        $semIndicacao = $this->processoEmAnalise($setor, $analista);

        foreach ([$processo, $semIndicacao] as $alvo) {
            $this->actingAs($analista, 'gestao')->post("/gestao/processos/{$alvo->id}/status-analise", [
                'status' => 'para_conclusao',
                'indicado_user_id' => $alvo->is($processo) ? $coordenador->id : null,
            ]);
        }

        $linhas = collect($this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?visao=para_conclusao')
            ->viewData('page')['props']['processos']['data'])->keyBy('id');

        $this->assertSame($coordenador->name, $linhas[$processo->id]['indicado_para']);
        $this->assertNull($linhas[$semIndicacao->id]['indicado_para']);
    }

    public function test_ficha_lista_so_quem_pode_ser_indicado(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->terceirizado($setor);
        $coordenador = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $colega = $this->terceirizado($setor);
        $processo = $this->processoEmAnalise($setor, $analista);
        AnalysisRecord::factory()->create(['viability_request_id' => $processo->id, 'revision' => 1]);

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(function (Assert $page) use ($coordenador, $colega): void {
                $ids = collect($page->toArray()['props']['usuariosIndicaveis'])->pluck('id')->all();

                $this->assertContains($coordenador->id, $ids);
                $this->assertNotContains($colega->id, $ids);
            });
    }
}
