<?php

namespace Tests\Unit\Risco;

use App\Enums\Fluxo;
use App\Enums\RiscoMunicipal;
use App\Enums\RiscoSanitario;
use App\Services\Risco\RiscoInput;
use App\Services\Risco\RiscoResult;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use Tests\TestCase;

/**
 * Contrato dos DTOs readonly do motor de risco (06-05) e do consumidor EP07+,
 * espelhando TerritoryResult: imutáveis, com dimensões municipal e sanitária
 * SEPARADAS, toArray() em snake_case e resumo de versões por dimensão. O
 * encaminhamento carrega o fluxo decidido e os gatilhos acionados.
 */
class RiscoDtoTest extends TestCase
{
    public function test_risco_input_para_cnae_inicializa_sem_respostas_nem_gatilhos(): void
    {
        $input = RiscoInput::paraCnae('6201500');

        $this->assertSame('6201500', $input->cnaeCode);
        $this->assertSame([], $input->respostasCondicionantes);
        $this->assertSame([], $input->gatilhosContexto);
        $this->assertNull($input->data);
        $this->assertNull($input->areaUtilizada);
        $this->assertNull($input->tipoImovel);
        $this->assertNull($input->subcategoriaUso);
    }

    public function test_risco_input_aceita_area_tipo_imovel_e_subcategoria(): void
    {
        $tipo = TipoImovel::fromRegin('GALPÃO', TipoImovelCatalog::sedur200826());
        $input = new RiscoInput(
            cnaeCode: '0111301',
            areaUtilizada: 800.0,
            tipoImovel: $tipo,
            subcategoriaUso: 'nR1-12',
        );

        $this->assertSame(800.0, $input->areaUtilizada);
        $this->assertTrue($input->tipoImovel?->dirigeRegra());
        $this->assertSame('nR1-12', $input->subcategoriaUso);
    }

    public function test_risco_result_to_array_em_snake_case(): void
    {
        $array = $this->resultExemplo()->toArray();

        $this->assertSame(
            ['municipal', 'sanitario', 'encaminhamento', 'fundamentacao', 'versoes'],
            array_keys($array),
        );

        $this->assertArrayHasKey('nivel_label', $array['municipal']);
        $this->assertArrayHasKey('versao_regras', $array['municipal']);
        $this->assertArrayHasKey('nivel_final', $array['sanitario']);
        $this->assertArrayHasKey('condicionantes_perguntas', $array['sanitario']);
        $this->assertArrayHasKey('dimensao_decisiva', $array['encaminhamento']);
        $this->assertArrayHasKey('gatilhos_acionados', $array['encaminhamento']);
    }

    public function test_versoes_devolve_versao_por_dimensao(): void
    {
        $this->assertSame(
            ['municipal' => 'decreto-32636-2020', 'sanitario' => 'visa-unificada-2026-04-30'],
            $this->resultExemplo()->versoes(),
        );
    }

    public function test_encaminhado_para_analise_reflete_o_fluxo(): void
    {
        $this->assertTrue($this->resultExemplo(Fluxo::Analise)->encaminhadoParaAnalise());
        $this->assertFalse($this->resultExemplo(Fluxo::Expresso)->encaminhadoParaAnalise());
    }

    private function resultExemplo(Fluxo $fluxo = Fluxo::Expresso): RiscoResult
    {
        return new RiscoResult(
            municipal: [
                'status' => 'classificado',
                'nivel' => RiscoMunicipal::BaixoA->value,
                'nivel_label' => RiscoMunicipal::BaixoA->label(),
                'condicionantes' => [],
                'versao_regras' => 'decreto-32636-2020',
            ],
            sanitario: [
                'status' => 'classificado',
                'nivel_original' => RiscoSanitario::Baixo->value,
                'nivel_final' => RiscoSanitario::Baixo->value,
                'reclassificado' => false,
                'condicionantes_perguntas' => [],
                'versao_regras' => 'visa-unificada-2026-04-30',
            ],
            encaminhamento: [
                'fluxo' => $fluxo->value,
                'dimensao_decisiva' => 'municipal',
                'motivo' => $fluxo === Fluxo::Analise ? 'Alto risco municipal exige análise técnica' : null,
                'gatilhos_acionados' => [],
            ],
            fundamentacao: [
                'Decreto Municipal nº 32.636/2020',
            ],
            versoes: [
                'municipal' => 'decreto-32636-2020',
                'sanitario' => 'visa-unificada-2026-04-30',
            ],
        );
    }
}
