<?php

namespace Tests\Unit\Analise;

use App\Enums\AnalysisStatus;
use PHPUnit\Framework\TestCase;

class AnalysisStatusTest extends TestCase
{
    public function test_todos_os_11_estados_existem_com_rotulo(): void
    {
        $this->assertCount(11, AnalysisStatus::cases());
        $this->assertSame('Para distribuir', AnalysisStatus::ParaDistribuir->label());
        $this->assertSame('Em convite', AnalysisStatus::EmConvite->label());
        $this->assertSame('Vistoriado', AnalysisStatus::Vistoriado->label());
    }

    public function test_grupo_agrupa_por_fase_operacional(): void
    {
        $this->assertSame('Convite', AnalysisStatus::ConviteCancelado->grupo());
        $this->assertSame('Vistoria', AnalysisStatus::Vistoriar->grupo());
        $this->assertSame('Distribuição', AnalysisStatus::ParaDistribuir->grupo());
        $this->assertSame('Análise', AnalysisStatus::EmAnalise->grupo());
    }

    public function test_options_devolve_value_label_para_o_front(): void
    {
        $options = AnalysisStatus::options();
        $this->assertContains(['value' => 'para_distribuir', 'label' => 'Para distribuir'], $options);
        $this->assertCount(11, $options);
    }

    public function test_proximas_lista_transicoes_manuais(): void
    {
        $this->assertSame(
            ['analise_concluida', 'em_convite', 'vistoriar'],
            array_map(fn (AnalysisStatus $s) => $s->value, AnalysisStatus::EmAnalise->proximas()),
        );
        $this->assertSame([], AnalysisStatus::AnaliseConcluida->proximas());
    }
}
