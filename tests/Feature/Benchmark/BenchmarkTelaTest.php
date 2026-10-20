<?php

namespace Tests\Feature\Benchmark;

use App\Models\BenchmarkIngestion;
use App\Models\BenchmarkMunicipalityMetric;
use App\Models\User;
use App\Support\Capitais;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Tela do benchmark nacional. Sem competência com cobertura completa, a tela
 * informa a lacuna — nunca um ranking parcial disfarçado de nacional.
 */
class BenchmarkTelaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function gestor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    private function semPermissaoDeRelatorios(): User
    {
        $user = User::factory()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('acessar-gestao');

        return $user;
    }

    private function competenciaCompletaComSalvador(): void
    {
        foreach (Capitais::ufs() as $uf) {
            BenchmarkIngestion::factory()->create(['uf' => $uf, 'ano' => 2026, 'mes' => 8]);
        }

        BenchmarkMunicipalityMetric::factory()->salvador()->create([
            'ano' => 2026, 'mes' => 8, 'media' => 23.24, 'n' => 665,
        ]);

        BenchmarkMunicipalityMetric::factory()->capital('SE')->create([
            'ano' => 2026, 'mes' => 8, 'media' => 2.0,
        ]);
    }

    public function test_entrega_posicao_ranking_de_capitais_e_serie(): void
    {
        $this->competenciaCompletaComSalvador();

        $this->actingAs($this->gestor(), 'gestao')
            ->get(route('gestao.relatorios.benchmark'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('gestao/relatorios/benchmark')
                ->where('competencia.ano', 2026)
                ->where('competencia.mes', 8)
                ->where('posicao.posicao', 2)
                ->has('capitais', 27)
                ->has('serie', 1),
            );
    }

    public function test_sem_competencia_completa_informa_a_lacuna(): void
    {
        BenchmarkIngestion::factory()->create(['uf' => 'BA', 'ano' => 2026, 'mes' => 8]);

        $this->actingAs($this->gestor(), 'gestao')
            ->get(route('gestao.relatorios.benchmark'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('competencia', null)
                ->where('capitais', [])
                ->where('posicao.posicao', null),
            );
    }

    public function test_exige_permissao_de_relatorios(): void
    {
        $this->actingAs($this->semPermissaoDeRelatorios(), 'gestao')
            ->get(route('gestao.relatorios.benchmark'))
            ->assertForbidden();
    }

    public function test_entrega_a_evolucao_do_mes_atual_contra_o_mes_anterior_da_redesim(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        BenchmarkMunicipalityMetric::factory()->salvador()->create([
            'ano' => 2026, 'mes' => 9, 'media' => 21.0, 'n' => 600,
        ]);

        $this->actingAs($this->gestor(), 'gestao')
            ->get(route('gestao.relatorios.benchmark'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('evolucao.anterior.ano', 2026)
                ->where('evolucao.anterior.mes', 9)
                ->where('evolucao.anterior.media', 21)
                ->where('evolucao.atual.mes', 10)
                ->where('evolucao.atual.media', null)
                ->where('evolucao.diferenca_horas', null)
                ->has('evolucao.ressalva'),
            );
    }
}
