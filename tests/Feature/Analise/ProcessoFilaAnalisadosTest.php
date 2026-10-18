<?php

namespace Tests\Feature\Analise;

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
