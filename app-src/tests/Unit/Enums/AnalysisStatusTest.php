<?php

namespace Tests\Unit\Enums;

use App\Enums\AnalysisStatus;
use PHPUnit\Framework\TestCase;

/**
 * Regra única da redistribuição (spec 2026-10-20): o apoio/gestor troca o
 * responsável em qualquer situação, menos depois da conclusão da análise.
 */
class AnalysisStatusTest extends TestCase
{
    public function test_redistribuicao_permitida_em_todas_as_situacoes_antes_da_conclusao(): void
    {
        foreach (AnalysisStatus::cases() as $status) {
            if ($status === AnalysisStatus::AnaliseConcluida) {
                continue;
            }

            $this->assertTrue($status->permiteRedistribuicao(), "{$status->value} deveria permitir redistribuição.");
        }
    }

    public function test_redistribuicao_bloqueada_apos_a_conclusao_da_analise(): void
    {
        $this->assertFalse(AnalysisStatus::AnaliseConcluida->permiteRedistribuicao());
    }
}
