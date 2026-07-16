<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cast_do_analysis_status_e_relation_da_timeline(): void
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::ParaDistribuir])->save();

        $request->analysisStatusTransitions()->create([
            'from_status' => null,
            'to_status' => AnalysisStatus::ParaDistribuir,
        ]);

        $request->refresh();
        $this->assertInstanceOf(AnalysisStatus::class, $request->analysis_status);
        $this->assertSame(AnalysisStatus::ParaDistribuir, $request->analysis_status);
        $this->assertCount(1, $request->analysisStatusTransitions);
        $this->assertSame(AnalysisStatus::ParaDistribuir, $request->analysisStatusTransitions->first()->to_status);
    }
}
