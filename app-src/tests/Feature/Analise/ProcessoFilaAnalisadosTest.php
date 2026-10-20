<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\AnalysisStatusTransition;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Caixa de entrada do analista: aba Análise (fila de trabalho) e aba Analisados
 * (processos que ele analisou), com o quadro de analisados por janela de dias.
 */
class ProcessoFilaAnalisadosTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach(Sector::factory()->create());

        return $analista;
    }

    private function processoAnalisadoPor(User $analista, int $diasAtras): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create(['status' => ViabilityRequestStatus::Deferida]);

        AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $processo->id,
            'analyst_user_id' => $analista->id,
            'finalized_at' => now()->subDays($diasAtras),
        ]);

        return $processo;
    }

    /**
     * Processo em análise com outra pessoa, sem ficha finalizada pelo analista:
     * só a tramitação liga o analista a ele.
     */
    private function processoComTransicao(User $ator, ?AnalysisStatus $de, AnalysisStatus $para, int $diasAtras = 0): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create(['status' => ViabilityRequestStatus::EmAnalise]);
        $this->transicao($processo, $ator, $de, $para, $diasAtras);

        return $processo;
    }

    private function transicao(ViabilityRequest $processo, User $ator, ?AnalysisStatus $de, AnalysisStatus $para, int $diasAtras = 0): void
    {
        /** @var AnalysisStatusTransition $transicao */
        $transicao = $processo->analysisStatusTransitions()->create([
            'from_status' => $de,
            'to_status' => $para,
            'actor_user_id' => $ator->id,
        ]);
        $transicao->forceFill(['created_at' => now()->subDays($diasAtras)])->save();
    }

    /**
     * @return list<int>
     */
    private function idsAnalisados(User $analista, string $query = ''): array
    {
        $props = $this->actingAs($analista, 'gestao')
            ->get('/gestao/processos/fila?visao=analisados'.$query)
            ->assertOk()
            ->viewData('page')['props'];

        return array_column($props['processos']['data'], 'id');
    }

    public function test_enviado_para_conclusao_pela_analista_aparece_em_analisados(): void
    {
        $analista = $this->analista();
        $processo = $this->processoComTransicao($analista, AnalysisStatus::EmAnalise, AnalysisStatus::ParaConclusao);

        $this->assertContains($processo->id, $this->idsAnalisados($analista));
    }

    public function test_encaminhado_pela_analista_aparece_em_analisados(): void
    {
        $analista = $this->analista();
        $processo = $this->processoComTransicao($analista, AnalysisStatus::EmAnalise, AnalysisStatus::Encaminhado);

        $this->assertContains($processo->id, $this->idsAnalisados($analista));
    }

    public function test_analise_concluida_pela_analista_aparece_em_analisados(): void
    {
        $analista = $this->analista();
        $processo = $this->processoComTransicao($analista, AnalysisStatus::EmAnalise, AnalysisStatus::AnaliseConcluida);

        $this->assertContains($processo->id, $this->idsAnalisados($analista));
    }

    public function test_distribuicao_feita_pelo_apoio_nao_conta_como_analisado(): void
    {
        $apoio = $this->analista();
        $processo = $this->processoComTransicao($apoio, AnalysisStatus::ParaDistribuir, AnalysisStatus::Encaminhado);

        $this->assertNotContains($processo->id, $this->idsAnalisados($apoio));
    }

    public function test_processo_com_ficha_e_transicao_aparece_uma_vez(): void
    {
        $analista = $this->analista();
        $processo = $this->processoAnalisadoPor($analista, 1);
        $this->transicao($processo, $analista, AnalysisStatus::EmAnalise, AnalysisStatus::ParaConclusao);

        $this->assertSame([$processo->id], $this->idsAnalisados($analista));
    }

    public function test_janela_de_dias_usa_a_data_da_transicao(): void
    {
        $analista = $this->analista();
        $this->processoComTransicao($analista, AnalysisStatus::EmAnalise, AnalysisStatus::ParaConclusao, 40);

        $contador = fn (int $janela): int => $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/fila?janela={$janela}")
            ->viewData('page')['props']['contadores']['analisados'];

        $this->assertSame(0, $contador(30));
        $this->assertSame(1, $contador(90));
    }

    public function test_aba_analisados_lista_o_que_o_analista_finalizou_e_nada_de_outros(): void
    {
        $analista = $this->analista();
        $outro = $this->analista();
        $meu = $this->processoAnalisadoPor($analista, 3);
        $this->processoAnalisadoPor($outro, 3);

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/processos/fila?visao=analisados')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visao', 'analisados')
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $meu->id));
    }

    public function test_aba_analise_continua_sendo_a_fila_de_trabalho(): void
    {
        $analista = $this->analista();
        $this->processoAnalisadoPor($analista, 3);

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/processos/fila')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visao', 'analise')
                ->has('processos.data', 0));
    }

    public function test_quadro_analisados_conta_so_a_janela_escolhida(): void
    {
        $analista = $this->analista();
        $this->processoAnalisadoPor($analista, 3);
        $this->processoAnalisadoPor($analista, 20);
        $this->processoAnalisadoPor($analista, 60);

        $contar = fn (string $query): int => $this->actingAs($analista, 'gestao')
            ->get('/gestao/processos/fila'.$query)
            ->assertOk()
            ->viewData('page')['props']['contadores']['analisados'];

        $this->assertSame(1, $contar('?janela=7'));
        $this->assertSame(2, $contar('?janela=30'));
        $this->assertSame(3, $contar('?janela=90'));
        $this->assertSame(2, $contar(''), 'Sem janela escolhida vale a janela padrão do painel (30 dias).');
    }

    public function test_janela_invalida_cai_na_padrao(): void
    {
        $analista = $this->analista();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/processos/fila?janela=15')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('janela', 30)
                ->where('janelas', [7, 30, 90]));
    }

    public function test_processo_com_duas_fichas_finalizadas_do_mesmo_analista_conta_uma_vez(): void
    {
        $analista = $this->analista();
        $processo = $this->processoAnalisadoPor($analista, 3);
        AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $processo->id,
            'revision' => 2,
            'analyst_user_id' => $analista->id,
            'finalized_at' => now()->subDay(),
        ]);

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/processos/fila?visao=analisados')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('contadores.analisados', 1));
    }
}
