<?php

namespace Tests\Feature\Relatorios;

use App\Enums\ViabilityRequestStatus;
use App\Models\BenchmarkIngestion;
use App\Models\BenchmarkMunicipalityMetric;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\DistanciaBenchmarkService;
use App\Support\Capitais;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Sala de Controle: os cinco blocos num único carregamento, com a ressalva
 * metodológica viajando junto do número de distância.
 */
class SalaDeControleTelaTest extends TestCase
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

    private function cenarioCompleto(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse('2026-03-02 00:00:00'),
        ]);

        $transicao = $request->transitions()->create([
            'from_status' => null,
            'to_status' => ViabilityRequestStatus::EmAnalise,
        ]);
        $transicao->forceFill(['created_at' => Carbon::parse('2026-03-02 00:00:00')])->save();

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'tvl_product_number' => fake()->unique()->numerify('TVL-2026-######'),
            'decided_at' => Carbon::parse('2026-03-03 00:00:00'),
        ]);

        $request->forceFill(['viabilidade_horas_uteis' => 2.0])->save();

        foreach (Capitais::ufs() as $uf) {
            BenchmarkIngestion::factory()->create(['uf' => $uf, 'ano' => 2026, 'mes' => 8]);
        }

        BenchmarkMunicipalityMetric::factory()->salvador()->create([
            'ano' => 2026, 'mes' => 8, 'media' => 20.0,
        ]);
    }

    public function test_entrega_os_cinco_blocos_num_unico_carregamento(): void
    {
        $this->cenarioCompleto();

        $this->actingAs($this->gestor(), 'gestao')
            ->get(route('gestao.sala-de-controle', ['data_de' => '2026-03-01', 'data_ate' => '2026-03-31']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('gestao/sala-de-controle')
                ->where('tempo.n', 1)
                ->where('operacao.total', 1)
                ->where('distancia.diferenca_horas', -18)
                ->has('evolucao', 1)
                ->has('gargalos'),
            );
    }

    public function test_a_ressalva_metodologica_viaja_junto_do_numero(): void
    {
        $this->cenarioCompleto();

        $this->actingAs($this->gestor(), 'gestao')
            ->get(route('gestao.sala-de-controle', ['data_de' => '2026-03-01', 'data_ate' => '2026-03-31']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('distancia.ressalva', DistanciaBenchmarkService::RESSALVA),
            );
    }

    public function test_sem_benchmark_a_tela_informa_o_motivo_e_mantem_o_resto(): void
    {
        $this->actingAs($this->gestor(), 'gestao')
            ->get(route('gestao.sala-de-controle'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('distancia.referencia', null)
                ->whereNot('distancia.motivo', null)
                ->where('tempo.n', 0),
            );
    }

    public function test_exige_permissao_de_relatorios(): void
    {
        $this->actingAs($this->semPermissaoDeRelatorios(), 'gestao')
            ->get(route('gestao.sala-de-controle'))
            ->assertForbidden();
    }
}
