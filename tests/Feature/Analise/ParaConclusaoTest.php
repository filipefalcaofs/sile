<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\ProcessoQueryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Aba "Para Conclusão" da caixa do setor: o analista termina a ficha, muda a
 * situação para "Para conclusão" (destinatário opcional) e o processo sai
 * da caixa dele para a fila centralizada do apoio. A conclusão final exige a
 * permissão concluir-analise.
 */
class ParaConclusaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analistaDoSetor(Sector $setor): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach($setor);

        return $analista;
    }

    private function analistaSemConcluir(): User
    {
        $analista = User::factory()->withAcceptedLgpdTerm()->create();
        $analista->givePermissionTo(['acessar-gestao', 'analisar-processos']);

        return $analista;
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

    public function test_encaminhar_para_chefia_pela_ficha_volta_para_a_caixa_de_entrada(): void
    {
        $setor = Sector::factory()->create();
        $servidor = User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create();
        $servidor->sectors()->attach($setor);
        $processo = $this->processoEmAnalise($setor, $servidor);

        $this->actingAs($servidor, 'gestao')
            ->from("/gestao/processos/{$processo->id}/ficha")
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'para_conclusao', 'voltar_para_caixa' => '1'])
            ->assertRedirect(route('gestao.processos.fila'))
            ->assertSessionHas('status', fn (string $mensagem): bool => str_contains($mensagem, 'aba Para conclusão'));

        $processo->refresh();
        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->analysis_status);
        $this->assertNull($processo->conclusao_encaminhada_para_user_id);
    }

    public function test_analista_servidor_envia_para_conclusao_sem_destinatario(): void
    {
        $setor = Sector::factory()->create();
        $servidor = User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create();
        $servidor->sectors()->attach($setor);
        $processo = $this->processoEmAnalise($setor, $servidor);

        $this->actingAs($servidor, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'para_conclusao'])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $processo->refresh();
        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->analysis_status);
        $this->assertNull($processo->conclusao_encaminhada_para_user_id);
        $this->assertSame($servidor->id, $processo->assigned_user_id);
    }

    public function test_terceirizada_em_analise_nao_marca_conclusao_convite_nem_vistoria(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);

        foreach (['analise_concluida', 'em_convite', 'vistoriar'] as $status) {
            $processo = $this->processoEmAnalise($setor, $analista);

            $this->actingAs($analista, 'gestao')
                ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => $status])
                ->assertSessionHas('error');

            $this->assertSame(AnalysisStatus::EmAnalise, $processo->refresh()->analysis_status, $status);
        }
    }

    public function test_menu_de_situacao_da_terceirizada_oferece_para_conclusao_e_encaminhado(): void
    {
        $setor = Sector::factory()->create();
        $terceirizada = $this->analistaDoSetor($setor);
        $servidor = User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create();
        $servidor->sectors()->attach($setor);
        $processo = $this->processoEmAnalise($setor, $terceirizada);

        $valoresPara = fn (User $usuario): array => collect(
            $this->actingAs($usuario, 'gestao')
                ->get("/gestao/processos/{$processo->id}")
                ->assertOk()
                ->viewData('page')['props']['analysisStatusProximas'],
        )->pluck('value')->all();

        $this->assertSame(['para_conclusao', 'encaminhado'], $valoresPara($terceirizada));
        $this->assertContains('analise_concluida', $valoresPara($servidor));
    }

    public function test_terceirizada_nao_recebe_a_permissao_que_exibe_o_bloco_conclusao_de_analise(): void
    {
        $setor = Sector::factory()->create();
        $terceirizada = $this->analistaDoSetor($setor);
        $servidor = User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create();
        $servidor->sectors()->attach($setor);
        $processo = $this->processoEmAnalise($setor, $terceirizada);

        $permissoesDe = fn (User $usuario): array => collect(
            $this->actingAs($usuario, 'gestao')
                ->get("/gestao/processos/{$processo->id}")
                ->assertOk()
                ->viewData('page')['props']['auth']['permissions'],
        )->all();

        $this->assertNotContains('concluir-analise', $permissoesDe($terceirizada));
        $this->assertContains('concluir-analise', $permissoesDe($servidor));
    }

    public function test_terceirizada_encaminha_o_processo_em_analise(): void
    {
        $setor = Sector::factory()->create();
        $outroSetor = Sector::factory()->create();
        $terceirizada = $this->analistaDoSetor($setor);
        $destino = $this->analistaDoSetor($outroSetor);
        $processo = $this->processoEmAnalise($setor, $terceirizada);

        $this->actingAs($terceirizada, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", [
                'status' => 'encaminhado',
                'sector_id' => $outroSetor->id,
                'user_id' => $destino->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $this->assertSame(AnalysisStatus::Encaminhado, $processo->refresh()->analysis_status);
    }

    public function test_analista_envia_para_conclusao_com_destinatario(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $chefia = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $processo = $this->processoEmAnalise($setor, $analista);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", [
                'status' => 'para_conclusao',
                'encaminhado_para_user_id' => $chefia->id,
            ])
            ->assertSessionHasNoErrors();

        $processo->refresh();
        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->analysis_status);
        $this->assertSame($chefia->id, $processo->conclusao_encaminhada_para_user_id);
    }

    public function test_destinatario_precisa_poder_concluir(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoEmAnalise($setor, $analista);
        $semPermissao = $this->analistaSemConcluir();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", [
                'status' => 'para_conclusao',
                'encaminhado_para_user_id' => $semPermissao->id,
            ])
            ->assertSessionHasErrors('encaminhado_para_user_id');

        $this->assertSame(AnalysisStatus::EmAnalise, $processo->refresh()->analysis_status);
    }

    public function test_processo_para_conclusao_sai_da_caixa_do_analista(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoEmAnalise($setor, $analista);
        $outro = $this->processoEmAnalise($setor, $analista);

        $processo->forceFill(['analysis_status' => AnalysisStatus::ParaConclusao])->save();

        $servico = app(ProcessoQueryService::class);
        $ids = $servico->fila($analista)->pluck('viability_requests.id')->all();

        $this->assertNotContains($processo->id, $ids);
        $this->assertContains($outro->id, $ids);
        $this->assertSame(1, $servico->contadores($analista)['aguardando_analise']);
    }

    public function test_aba_para_conclusao_lista_so_os_sem_destinatario_com_o_analista_de_origem(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $chefia = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $apoio->sectors()->attach($setor);

        $comDestino = $this->processoEmAnalise($setor, $analista);
        $comDestino->forceFill([
            'analysis_status' => AnalysisStatus::ParaConclusao,
            'conclusao_encaminhada_para_user_id' => $chefia->id,
        ])->save();
        $semDestino = $this->processoEmAnalise($setor, $analista);
        $semDestino->forceFill(['analysis_status' => AnalysisStatus::ParaConclusao])->save();
        $emAndamento = $this->processoEmAnalise($setor, $analista);

        $page = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?visao=para_conclusao')
            ->assertOk()
            ->viewData('page');

        $linhas = collect($page['props']['processos']['data'])->keyBy('id');

        $this->assertSame('para_conclusao', $page['props']['visao']);
        $this->assertSame([$semDestino->id], $linhas->keys()->all());
        $this->assertNull($linhas[$semDestino->id]['encaminhado_para']);
        $this->assertSame($analista->name, $linhas[$semDestino->id]['assigned_to']);
        $this->assertSame(1, $page['props']['contadores']['para_conclusao']);

        $outras = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?visao=distribuidos')
            ->viewData('page');

        $this->assertSame([$emAndamento->id], collect($outras['props']['processos']['data'])->pluck('id')->all());
        $this->assertSame(1, $outras['props']['contadores']['distribuidos']);
    }

    public function test_conclusao_exige_a_permissao_concluir_analise(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaSemConcluir();
        $analista->sectors()->attach($setor);
        $processo = $this->processoEmAnalise($setor, $analista);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", ['resultado' => 'deferido', 'tipo' => 'definitiva'])
            ->assertForbidden();
    }

    public function test_conclusao_a_partir_de_para_conclusao_fecha_o_eixo_e_limpa_o_destinatario(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $chefia = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $processo = $this->processoEmAnalise($setor, $analista);
        $processo->forceFill([
            'analysis_status' => AnalysisStatus::ParaConclusao,
            'conclusao_encaminhada_para_user_id' => $chefia->id,
        ])->save();
        AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'per_cnae' => [],
        ]);

        $this->actingAs($chefia, 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", ['resultado' => 'desativado'])
            ->assertSessionHasNoErrors();

        $processo->refresh();
        $this->assertSame(AnalysisStatus::AnaliseConcluida, $processo->analysis_status);
        $this->assertNull($processo->conclusao_encaminhada_para_user_id);
    }
}
