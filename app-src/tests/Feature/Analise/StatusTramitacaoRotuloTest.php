<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Rótulo da coluna Status de tramitação: acompanha a etapa do processo, com o
 * setor de destino no encaminhamento e o resultado na análise concluída.
 */
class StatusTramitacaoRotuloTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function processo(?AnalysisStatus $status, array $atributos = []): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill(array_merge(['analysis_status' => $status], $atributos))->save();

        return $processo->fresh();
    }

    public function test_sem_eixo_operacional_nao_tem_rotulo(): void
    {
        $this->assertNull($this->processo(null)->rotuloStatusTramitacao());
    }

    public function test_estados_comuns_usam_o_rotulo_do_enum(): void
    {
        $this->assertSame('Para distribuir', $this->processo(AnalysisStatus::ParaDistribuir)->rotuloStatusTramitacao());
        $this->assertSame('Analisar', $this->processo(AnalysisStatus::Analisar)->rotuloStatusTramitacao());
        $this->assertSame('Em análise', $this->processo(AnalysisStatus::EmAnalise)->rotuloStatusTramitacao());
        $this->assertSame('Vistoriado', $this->processo(AnalysisStatus::Vistoriado)->rotuloStatusTramitacao());
        $this->assertSame('Prazo para convite expirado', $this->processo(AnalysisStatus::ConviteExpirado)->rotuloStatusTramitacao());
    }

    public function test_encaminhado_mostra_o_setor_de_destino(): void
    {
        $setor = Sector::factory()->create(['name' => 'SEDUR-DLI-GAB-Gabinete']);

        $processo = $this->processo(AnalysisStatus::Encaminhado, ['sector_id' => $setor->id]);

        $this->assertSame('Encaminhado para SEDUR-DLI-GAB-Gabinete', $processo->rotuloStatusTramitacao());
    }

    public function test_encaminhado_sem_setor_fica_so_com_o_rotulo_do_enum(): void
    {
        $this->assertSame('Encaminhado para', $this->processo(AnalysisStatus::Encaminhado)->rotuloStatusTramitacao());
    }

    public function test_analise_concluida_traz_o_resultado(): void
    {
        $esperado = [
            [ViabilityRequestStatus::Deferida, 'Análise concluída – Deferido'],
            [ViabilityRequestStatus::Indeferida, 'Análise concluída – Indeferido'],
            [ViabilityRequestStatus::Cassado, 'Análise concluída – Cassado'],
            [ViabilityRequestStatus::Revogado, 'Análise concluída – Revogado'],
            [ViabilityRequestStatus::Desativado, 'Análise concluída – Desativado'],
        ];

        foreach ($esperado as [$status, $rotulo]) {
            $processo = $this->processo(AnalysisStatus::AnaliseConcluida, ['status' => $status]);

            $this->assertSame($rotulo, $processo->rotuloStatusTramitacao());
        }
    }

    public function test_analise_concluida_sem_resultado_conhecido_fica_so_com_o_rotulo(): void
    {
        $processo = $this->processo(AnalysisStatus::AnaliseConcluida, ['status' => ViabilityRequestStatus::EmAnalise]);

        $this->assertSame('Análise concluída', $processo->rotuloStatusTramitacao());
    }
}
