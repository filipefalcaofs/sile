<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelarConviteEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function emConvite(): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmPendencia])->save();

        return $request->refresh();
    }

    public function test_analista_cancela_convite_com_parecer(): void
    {
        $analista = User::factory()->analista()->create();
        $request = $this->emConvite();
        $convite = AnalysisPendency::factory()->for($request, 'viabilityRequest')->create([
            'status' => AnalysisPendencyStatus::Aberta,
        ]);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$request->id}/cancelar-convite", ['parecer' => 'Dado já consta nos autos.'])
            ->assertRedirect();

        $convite->refresh();
        $this->assertSame(AnalysisPendencyStatus::Cancelada, $convite->status);
        $this->assertSame('Dado já consta nos autos.', $convite->parecer);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
    }

    public function test_parecer_obrigatorio(): void
    {
        $analista = User::factory()->analista()->create();
        $request = $this->emConvite();
        AnalysisPendency::factory()->for($request, 'viabilityRequest')->create([
            'status' => AnalysisPendencyStatus::Aberta,
        ]);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$request->id}/cancelar-convite", ['parecer' => ''])
            ->assertSessionHasErrors('parecer');

        $this->assertSame(ViabilityRequestStatus::EmPendencia, $request->fresh()->status);
    }

    public function test_sem_convite_aberto_avisa(): void
    {
        $analista = User::factory()->analista()->create();
        $request = $this->emConvite(); // em_pendencia mas sem convite Aberta criado

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$request->id}/cancelar-convite", ['parecer' => 'motivo'])
            ->assertSessionHas('error');
    }
}
