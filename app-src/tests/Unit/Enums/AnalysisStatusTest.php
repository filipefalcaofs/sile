<?php

namespace Tests\Unit\Enums;

use App\Enums\AnalysisStatus;
use PHPUnit\Framework\TestCase;

/**
 * Regra única da redistribuição (spec 2026-09-22): a troca da analista
 * responsável pelo apoio/gestor só é permitida ANTES da conclusão da análise.
 */
class AnalysisStatusTest extends TestCase
{
    public function test_redistribuicao_permitida_antes_da_conclusao(): void
    {
        $permitidos = [
            AnalysisStatus::ParaDistribuir,
            AnalysisStatus::Encaminhado,
            AnalysisStatus::Analisar,
            AnalysisStatus::EmAnalise,
        ];

        foreach ($permitidos as $status) {
            $this->assertTrue($status->permiteRedistribuicao(), "{$status->value} deveria permitir redistribuição.");
        }
    }

    public function test_redistribuicao_bloqueada_apos_conclusao_ou_em_convite_ou_vistoria(): void
    {
        $bloqueados = [
            AnalysisStatus::AnaliseConcluida,
            AnalysisStatus::EmConvite,
            AnalysisStatus::ConviteRespondido,
            AnalysisStatus::ConviteCancelado,
            AnalysisStatus::ConviteExpirado,
            AnalysisStatus::Vistoriar,
            AnalysisStatus::Vistoriado,
        ];

        foreach ($bloqueados as $status) {
            $this->assertFalse($status->permiteRedistribuicao(), "{$status->value} não deveria permitir redistribuição.");
        }
    }
}
