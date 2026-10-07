<?php

namespace Tests\Feature\Tratamento;

use App\Services\Tratamento\PlanilhaTratamentoWorkbook;
use Tests\TestCase;

class PlanilhaWorkbookCompletudeTest extends TestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private static ?array $carga = null;

    /**
     * @return array<string, mixed>
     */
    private function carga(): array
    {
        return self::$carga ??= (new PlanilhaTratamentoWorkbook)->ler(
            base_path('database/data/regras-20-08-26/planilha-regras.xlsx'),
        );
    }

    public function test_le_todas_as_linhas_de_enquadramento_da_aba_cnaes(): void
    {
        $this->assertCount(2850, $this->carga()['enquadramentos']);
    }

    public function test_enquadramento_traz_metadados_da_linha(): void
    {
        $primeira = $this->carga()['enquadramentos'][0];

        $this->assertSame('2024-04-12', $primeira['metadados']['atualizado_em']);
        $this->assertSame('Regra 5', $primeira['metadados']['atualizacao_recente']);
        $this->assertArrayHasKey('subdivisao', $primeira['metadados']);
    }

    public function test_vagas_trazem_as_variantes_de_carga_patio_e_embarque(): void
    {
        $vagas = $this->carga()['enquadramentos'][0]['vagas'];

        foreach ([1, 2, 3] as $n) {
            foreach (['aplica', 'analise', 'quantidade'] as $campo) {
                $this->assertArrayHasKey("carga_{$n}_{$campo}", $vagas);
                $this->assertArrayHasKey("patio_{$n}_{$campo}", $vagas);
            }

            foreach (['aplica', 'analise'] as $campo) {
                $this->assertArrayHasKey("embarque_{$n}_{$campo}", $vagas);
            }
        }

        $this->assertArrayHasKey('carga', $vagas);
    }

    public function test_toda_variante_de_vagas_chega_com_valor_da_planilha(): void
    {
        $chaves = [];

        foreach ([1, 2, 3] as $n) {
            foreach (['carga', 'patio'] as $grupo) {
                foreach (['aplica', 'analise', 'quantidade'] as $campo) {
                    $chaves[] = "{$grupo}_{$n}_{$campo}";
                }
            }

            $chaves[] = "embarque_{$n}_aplica";
            $chaves[] = "embarque_{$n}_analise";
        }

        foreach ($chaves as $chave) {
            $preenchidas = count(array_filter(
                $this->carga()['enquadramentos'],
                fn (array $linha): bool => $linha['vagas'][$chave] !== null,
            ));

            $this->assertGreaterThan(0, $preenchidas, "A coluna {$chave} não chegou com valor.");
        }
    }

    public function test_cnlu_sai_no_nivel_de_subgrupo_conforme_a_sedur(): void
    {
        $codigos = array_column($this->carga()['cnlu'], 'codigo');

        $this->assertContains('nR3-08', $codigos);
        $this->assertContains('nRa-02', $codigos);
        $this->assertContains('ID3-02', $codigos);
        $this->assertNotContains('nR3', $codigos);
        $this->assertNotContains('nRa', $codigos);
    }

    public function test_nenhum_item_cnlu_fica_aguardando_a_sedur(): void
    {
        $notas = array_column($this->carga()['notas'], 'texto', 'chave');

        $this->assertArrayNotHasKey('cnlu_aguardando_sedur', $notas);
    }
}
