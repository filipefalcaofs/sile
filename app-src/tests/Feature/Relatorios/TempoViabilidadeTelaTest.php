<?php

namespace Tests\Feature\Relatorios;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A tela de tempo passa a entregar a distribuição na régua federal e a
 * decomposição dos gargalos, sem alterar os blocos que o EP15 já entregava.
 */
class TempoViabilidadeTelaTest extends TestCase
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

    /**
     * Entra na gestão, mas não consulta relatórios — é o caso que prova o gate.
     */
    private function semPermissaoDeRelatorios(): User
    {
        $user = User::factory()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('acessar-gestao');

        return $user;
    }

    private function processoDecidido(): ViabilityRequest
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

        return $request->fresh();
    }

    public function test_entrega_percentis_e_gargalos_para_o_gestor(): void
    {
        $this->processoDecidido();

        $this->actingAs($this->gestor(), 'gestao')
            ->get(route('gestao.relatorios.tempo', ['data_de' => '2026-03-01', 'data_ate' => '2026-03-31']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('gestao/relatorios/tempo')
                ->where('percentisViabilidade.n', 1)
                ->where('percentisViabilidade.p90', fn ($valor): bool => abs((float) $valor - 24.0) < 0.01)
                ->where('gargalos.0.faixa', 'analise_manual')
                ->has('tempoPorEtapa'),
            );
    }

    public function test_periodo_sem_processos_entrega_estrutura_nula_e_lista_vazia(): void
    {
        $this->actingAs($this->gestor(), 'gestao')
            ->get(route('gestao.relatorios.tempo', ['data_de' => '2026-03-01', 'data_ate' => '2026-03-31']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('percentisViabilidade.n', 0)
                ->where('percentisViabilidade.media', null)
                ->where('gargalos', []),
            );
    }

    public function test_usuario_sem_permissao_de_relatorios_nao_acessa(): void
    {
        $this->actingAs($this->semPermissaoDeRelatorios(), 'gestao')
            ->get(route('gestao.relatorios.tempo'))
            ->assertForbidden();
    }
}
