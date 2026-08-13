<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\AnalysisRecordStatus;
use App\Http\Resources\AnalysisRecordResource;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Escritório virtual — flag "Sede de Escritório Virtual" na ficha (RN-EV-02):
 * o analista confirma sede=Sim/Não via autosave; o resource expõe o campo.
 * Setup espelha AnalysisRecordAutosaveTest.
 */
class FichaSedeFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function fichaRascunho(): AnalysisRecord
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        return AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => [[
                'cnae' => '8211300',
                'cnae_formatado' => '8211-3/00',
                'is_primary' => true,
                'status_sugerido' => 'deferida',
                'status_escolhido' => 'deferida',
            ]],
            'parecer' => null,
        ]);
    }

    public function test_autosave_grava_e_expoe_is_virtual_office_hq(): void
    {
        $ficha = $this->fichaRascunho();

        $this->actingAs($this->analista(), 'gestao')
            ->patchJson("/gestao/processos/{$ficha->viability_request_id}/ficha", [
                'is_virtual_office_hq' => true,
            ])
            ->assertOk();

        $ficha->refresh();
        $this->assertTrue($ficha->is_virtual_office_hq);

        $payload = (new AnalysisRecordResource($ficha))->resolve();
        $this->assertArrayHasKey('is_virtual_office_hq', $payload);
        $this->assertTrue($payload['is_virtual_office_hq']);
    }
}
