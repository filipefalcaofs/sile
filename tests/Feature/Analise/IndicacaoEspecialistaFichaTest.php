<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A ficha não exibe um card consolidado de “indicação do especialista”.
 * A sugestão por CNAE continua na lista de atividades; o analista decide.
 */
class IndicacaoEspecialistaFichaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_ficha_nao_envia_indicacao_consolidada_do_especialista(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => fake()->unique()->numerify('VIA-'.now()->year.'-######'),
            'protocoled_at' => now(),
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => [
                [
                    'cnae' => '4781400',
                    'status_sugerido' => 'analise',
                    'status_escolhido' => 'analise',
                    'justificativa' => 'Combinação zona × grupo de uso sem regra no Quadro 10 vigente.',
                ],
            ],
        ]);

        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->missing('indicacaoEspecialista'));
    }
}
