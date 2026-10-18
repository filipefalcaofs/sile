<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Caixa do setor: colunas Data de entrada, Tipo do imóvel e Status de
 * tramitação, e a lista de analistas do setor no filtro.
 */
class CaixaSetorColunasEFiltrosTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function chefia(Sector $setor): User
    {
        $chefia = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $chefia->sectors()->attach($setor);

        return $chefia;
    }

    private function analista(Sector $setor, string $nome): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => $nome]);
        $analista->sectors()->attach($setor);

        return $analista;
    }

    private function processo(Sector $setor, ?User $responsavel = null): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create([
            'tipo_imovel' => 'Edifício comercial',
            'protocoled_at' => now()->subDays(2),
        ]);
        $processo->forceFill([
            'sector_id' => $setor->id,
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
            'analysis_stage' => AnalysisStage::Analise,
            'assigned_user_id' => $responsavel?->id,
            'analysis_due_at' => now()->addDays(3),
        ])->save();

        return $processo;
    }

    public function test_linha_traz_data_de_entrada_tipo_do_imovel_e_status_de_tramitacao(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $processo = $this->processo($setor);

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('processos.data.0.id', $processo->id)
                ->where('processos.data.0.tipo_imovel', 'Edifício comercial')
                ->where('processos.data.0.status_tramitacao', AnalysisStatus::ParaDistribuir->label())
                ->where('processos.data.0.data_entrada', $processo->protocoled_at->toIso8601String()));
    }

    public function test_filtro_de_analista_lista_os_analistas_do_setor(): void
    {
        $setor = Sector::factory()->create();
        $outroSetor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $this->analista($setor, 'Beatriz do Setor');
        $this->analista($outroSetor, 'Carla de Outro Setor');

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analistaOptions', fn ($opcoes) => collect($opcoes)->pluck('label')->contains('Beatriz do Setor')
                    && ! collect($opcoes)->pluck('label')->contains('Carla de Outro Setor')));
    }

    public function test_filtra_a_caixa_pelo_analista_escolhido(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $beatriz = $this->analista($setor, 'Beatriz');
        $carla = $this->analista($setor, 'Carla');
        $daBeatriz = $this->processo($setor, $beatriz);
        $this->processo($setor, $carla);

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/caixa-setor?visao=distribuidos&analista='.$beatriz->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $daBeatriz->id));
    }
}
