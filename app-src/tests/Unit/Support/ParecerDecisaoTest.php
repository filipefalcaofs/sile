<?php

namespace Tests\Unit\Support;

use App\Enums\DecisionOutcome;
use App\Enums\TvlKind;
use App\Models\ViabilityDecision;
use App\Support\ParecerDecisao;
use Tests\TestCase;

/**
 * Parecer da decisão como o portal mostra ao requerente: inclui o tipo do
 * deferimento e até quando a viabilidade vale.
 */
class ParecerDecisaoTest extends TestCase
{
    public function test_inclui_tipo_e_validade(): void
    {
        $decisao = (new ViabilityDecision)->forceFill([
            'outcome' => DecisionOutcome::Deferida,
            'tvl_kind' => TvlKind::Provisoria,
            'tvl_valid_until' => '2027-04-20',
        ]);

        $parecer = ParecerDecisao::de($decisao);

        $this->assertSame('Provisória', $parecer['tipo']);
        $this->assertSame('2027-04-20', $parecer['valido_ate']);
    }

    public function test_sem_tipo_nem_validade_vem_nulo(): void
    {
        $decisao = (new ViabilityDecision)->forceFill(['outcome' => DecisionOutcome::Indeferida]);

        $parecer = ParecerDecisao::de($decisao);

        $this->assertNull($parecer['tipo']);
        $this->assertNull($parecer['valido_ate']);
    }
}
