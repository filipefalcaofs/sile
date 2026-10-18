<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
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
 * As fichas de análise salvas e as finalizadas ficam à consulta de qualquer
 * usuário com acesso à análise, mesmo de outro setor e de outra analista.
 */
class FichaConsultaPorTodosTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analistaDoSetor(): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach(Sector::factory()->create());

        return $analista;
    }

    public function test_outra_analista_de_outro_setor_consulta_a_ficha_finalizada(): void
    {
        $autora = $this->analistaDoSetor();
        $consulta = $this->analistaDoSetor();
        $processo = ViabilityRequest::factory()->create(['status' => ViabilityRequestStatus::EmAnalise]);
        $processo->forceFill(['assigned_user_id' => $autora->id])->save();
        AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $processo->id,
            'analyst_user_id' => $autora->id,
        ]);

        $this->actingAs($consulta, 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.status', AnalysisRecordStatus::Finalizada->value)
                ->where('ficha.editavel', false));
    }

    public function test_outra_analista_consulta_a_ficha_salva_em_rascunho(): void
    {
        $autora = $this->analistaDoSetor();
        $consulta = $this->analistaDoSetor();
        $processo = ViabilityRequest::factory()->create(['status' => ViabilityRequestStatus::EmAnalise]);
        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'status' => AnalysisRecordStatus::Rascunho,
            'analyst_user_id' => $autora->id,
        ]);

        $this->actingAs($consulta, 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk();
    }
}
