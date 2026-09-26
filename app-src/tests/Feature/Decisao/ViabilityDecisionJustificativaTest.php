<?php

namespace Tests\Feature\Decisao;

use App\Models\ViabilityDecision;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ViabilityDecisionJustificativaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_indeferimento_sem_justificativa_nao_e_gravado(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Indeferimento sem justificativa');

        ViabilityDecision::factory()->indeferida()->create(['reason' => null]);
    }

    public function test_indeferimento_com_justificativa_em_branco_nao_e_gravado(): void
    {
        $this->expectException(DomainException::class);

        ViabilityDecision::factory()->indeferida()->create(['reason' => '   ']);
    }

    public function test_indeferimento_com_justificativa_e_gravado(): void
    {
        $decisao = ViabilityDecision::factory()->indeferida()->create(['reason' => 'Uso proibido na zona.']);

        $this->assertTrue($decisao->exists);
    }

    public function test_deferimento_nao_exige_justificativa(): void
    {
        $decisao = ViabilityDecision::factory()->create(['reason' => null]);

        $this->assertTrue($decisao->exists);
    }
}
