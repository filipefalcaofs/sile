<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateMachinePendenciaIndefereTest extends TestCase
{
    use RefreshDatabase;

    public function test_em_pendencia_pode_indeferir(): void
    {
        $sm = app(ViabilityRequestStateMachine::class);

        $this->assertTrue(
            $sm->canTransition(ViabilityRequestStatus::EmPendencia, ViabilityRequestStatus::Indeferida),
        );
        // Mantém o retorno à análise (resposta do requerente).
        $this->assertTrue(
            $sm->canTransition(ViabilityRequestStatus::EmPendencia, ViabilityRequestStatus::EmAnalise),
        );
    }
}
