<?php

namespace Tests\Feature\Motor;

use App\Enums\AnalysisRecordStatus;
use App\Enums\EtapaMotor;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEtapa;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FichaEtapasDoMotorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function processoComFicha(): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-000300',
            'protocoled_at' => now(),
            'address_street' => 'Rua das Flores',
            'address_number' => '100',
            'address_neighborhood' => 'Centro',
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        return $processo;
    }

    public function test_ficha_traz_as_etapas_do_motor_em_ordem_e_a_permissao_de_reprocessar(): void
    {
        $processo = $this->processoComFicha();
        ViabilityRequestEtapa::factory()->create(['viability_request_id' => $processo->id, 'etapa' => EtapaMotor::Zona]);
        ViabilityRequestEtapa::factory()->indisponivel()->create([
            'viability_request_id' => $processo->id,
            'etapa' => EtapaMotor::LocalizacaoInscricao,
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('podeReprocessar', true)
                ->has('etapasMotor', 2)
                ->where('etapasMotor.0.etapa', 'localizacao_inscricao')
                ->where('etapasMotor.0.situacao', 'indisponivel')
                ->where('etapasMotor.0.servico', 'inscricao_imobiliaria')
                ->where('etapasMotor.0.esgotada', false)
                ->where('etapasMotor.1.etapa', 'zona'));
    }

    public function test_ficha_sem_etapas_traz_lista_vazia(): void
    {
        $processo = $this->processoComFicha();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('etapasMotor', 0));
    }
}
