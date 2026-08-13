<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisPendencyCancelamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_campos_de_cancelamento_persistem(): void
    {
        $request = ViabilityRequest::factory()->create();
        $user = User::factory()->create();
        $pendency = AnalysisPendency::factory()->for($request, 'viabilityRequest')->create();

        $pendency->update([
            'status' => AnalysisPendencyStatus::Cancelada,
            'parecer' => 'Motivo do cancelamento',
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $user->id,
        ]);

        $pendency->refresh();
        $this->assertSame(AnalysisPendencyStatus::Cancelada, $pendency->status);
        $this->assertSame('Motivo do cancelamento', $pendency->parecer);
        $this->assertNotNull($pendency->cancelled_at);
        $this->assertSame($user->id, $pendency->cancelled_by_user_id);
    }
}
