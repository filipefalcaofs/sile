<?php

namespace Tests\Unit\Viabilidade;

use App\Enums\ResultadoViabilidade;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Risco\RiscoResult;
use App\Services\Viabilidade\ConsultaViabilidadeInput;
use App\Services\Viabilidade\ConsultaViabilidadeResult;
use Tests\TestCase;

/**
 * Contrato dos DTOs da consulta prévia (07-04): o Input expõe as 3 entradas
 * honestas e o Result AGREGA os sub-resultados reais dos motores (geocode,
 * território, enquadramento LOUOS e risco) sem recomputar nada.
 *
 * Evidência anti-fachada central: o veredito locacional é PROPAGADO do
 * consolidado do motor LOUOS (`enquadramento->resultado()`/`->consolidado`),
 * nunca decidido pelo orquestrador — a degradação "sem zona → pendente" é
 * verdade única do motor (HU-044), aqui apenas espelhada. toArray() é o contrato
 * snake_case do endpoint JSON e do snapshot do histórico; versoes() agrega as
 * versões de TODAS as regras (território + LOUOS + risco — RN-002/RN-004).
 */
class ConsultaViabilidadeResultTest extends TestCase
{
    public function test_input_para_endereco_define_tipo_e_campos(): void
    {
        $input = ConsultaViabilidadeInput::paraEndereco('Av. Oceânica, 1, Barra', '4711301', 120.5);

        $this->assertSame(ConsultaViabilidadeInput::TIPO_ENDERECO, $input->tipo);
        $this->assertSame('Av. Oceânica, 1, Barra', $input->endereco);
        $this->assertSame('4711301', $input->cnae);
        $this->assertSame(120.5, $input->area);
        $this->assertNull($input->inscricao);
    }

    public function test_input_para_cnae_nao_carrega_endereco_nem_inscricao(): void
    {
        $input = ConsultaViabilidadeInput::paraCnae('4711301');

        $this->assertSame(ConsultaViabilidadeInput::TIPO_CNAE, $input->tipo);
        $this->assertSame('4711301', $input->cnae);
        $this->assertNull($input->area);
        $this->assertNull($input->endereco);
        $this->assertNull($input->inscricao);
    }

    public function test_input_para_inscricao_define_tipo_e_campos(): void
    {
        $input = ConsultaViabilidadeInput::paraInscricao('123456-7', '4711301', 80.0);

        $this->assertSame(ConsultaViabilidadeInput::TIPO_INSCRICAO, $input->tipo);
        $this->assertSame('123456-7', $input->inscricao);
        $this->assertSame('4711301', $input->cnae);
        $this->assertSame(80.0, $input->area);
        $this->assertNull($input->endereco);
    }

    public function test_veredito_locacional_propaga_o_consolidado_do_motor(): void
    {
        $veredito = $this->resultExemplo()->vereditoLocacional();

        $this->assertSame(ResultadoViabilidade::Pendente->value, $veredito['resultado']);
        $this->assertSame('Pendente de análise técnica', $veredito['label']);
        $this->assertSame(
            'Permissão por zona pendente da base oficial (SEDUR)',
            $veredito['motivo'],
        );
    }

    public function test_versoes_agrega_territorio_louos_e_risco(): void
    {
        $versoes = $this->resultExemplo()->versoes();

        $this->assertSame(['territorio', 'louos', 'risco'], array_keys($versoes));
        $this->assertSame('louos-q7-v1', $versoes['louos']['quadro7']);
        $this->assertSame('decreto-32636-2020', $versoes['risco']['municipal']);
        $this->assertSame('bairros-v1', $versoes['territorio']['bairro']);
        $this->assertNull($versoes['territorio']['zona']);
    }

    public function test_to_array_expoe_secoes_de_enquadramento_risco_e_restricoes(): void
    {
        $array = $this->resultExemplo()->toArray();

        $this->assertSame(
            [
                'entrada',
                'geocode',
                'territorio',
                'enquadramento',
                'risco',
                'restricoes',
                'veredito_locacional',
                'fundamentacao',
                'avisos',
                'versoes',
            ],
            array_keys($array),
        );

        $this->assertArrayHasKey('quadro7', $array['enquadramento']);
        $this->assertArrayHasKey('municipal', $array['risco']);
        $this->assertSame('identificado', $array['restricoes']['status']);
        $this->assertSame(ResultadoViabilidade::Pendente->value, $array['veredito_locacional']['resultado']);
    }

    public function test_to_array_degrada_geocode_e_territorio_nulos_na_consulta_por_cnae(): void
    {
        $array = $this->resultPorCnae()->toArray();

        $this->assertNull($array['geocode']);
        $this->assertNull($array['territorio']);
        $this->assertNull($array['restricoes']);
        $this->assertSame([], $array['versoes']['territorio']);
        $this->assertSame(ResultadoViabilidade::Pendente->value, $array['veredito_locacional']['resultado']);
    }

    public function test_fundamentacao_une_motor_louos_e_risco_sem_duplicar(): void
    {
        $fundamentacao = $this->resultExemplo()->fundamentacao();

        $this->assertContains('Lei nº 9.148/2016 (LOUOS) — Quadro 7', $fundamentacao);
        $this->assertContains('Decreto Municipal nº 32.636/2020', $fundamentacao);
        $this->assertSame(
            array_values(array_unique($fundamentacao)),
            $fundamentacao,
            'A fundamentação não pode conter referências duplicadas.',
        );
        $this->assertSame(
            1,
            count(array_keys($fundamentacao, 'Decreto Municipal nº 32.636/2020', true)),
            'A referência comum aos dois motores deve aparecer uma única vez.',
        );
    }

    private function resultExemplo(): ConsultaViabilidadeResult
    {
        return new ConsultaViabilidadeResult(
            entrada: [
                'tipo' => ConsultaViabilidadeInput::TIPO_ENDERECO,
                'cnae' => '4711301',
                'cnae_formatado' => '4711-3/01',
                'area' => 120.5,
                'endereco' => 'Av. Oceânica, 1, Barra',
            ],
            geocode: $this->geocodeExemplo(),
            territory: $this->territoryExemplo(),
            enquadramento: $this->enquadramentoPendente(),
            risco: $this->riscoClassificado(),
            avisos: ['Zona urbanística pendente da base oficial (SEDUR) — veredito locacional encaminhado para análise técnica.'],
        );
    }

    private function resultPorCnae(): ConsultaViabilidadeResult
    {
        return new ConsultaViabilidadeResult(
            entrada: [
                'tipo' => ConsultaViabilidadeInput::TIPO_CNAE,
                'cnae' => '4711301',
                'cnae_formatado' => '4711-3/01',
                'area' => null,
            ],
            geocode: null,
            territory: null,
            enquadramento: $this->enquadramentoPendente(),
            risco: $this->riscoClassificado(),
            avisos: ['Consulta por CNAE sem localização — enquadramento territorial não avaliado.'],
        );
    }

    private function enquadramentoPendente(): EnquadramentoResult
    {
        return new EnquadramentoResult(
            quadro7: [
                'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                'grupo' => 'nR1',
                'subgrupo' => 'nR1-01',
                'motivo' => null,
                'versao_regra' => 'louos-q7-v1',
            ],
            quadro10: [
                'status' => EnquadramentoResult::STATUS_INDISPONIVEL,
                'permissao' => null,
                'motivo' => 'Zona urbanística indisponível (pendente SEDUR)',
                'versao_regra' => null,
            ],
            quadro11: [
                'status' => EnquadramentoResult::STATUS_NAO_ENCONTRADO,
                'condicoes' => [],
                'motivo' => null,
                'versao_regra' => null,
            ],
            quadro11a: [
                'status' => EnquadramentoResult::STATUS_NAO_ENCONTRADO,
                'condicoes' => [],
                'motivo' => null,
                'versao_regra' => null,
            ],
            consolidado: [
                'resultado' => ResultadoViabilidade::Pendente->value,
                'motivo' => 'Permissão por zona pendente da base oficial (SEDUR)',
                'fundamentacao' => [
                    'Lei nº 9.148/2016 (LOUOS) — Quadro 7',
                    'Decreto Municipal nº 32.636/2020',
                ],
                'condicionantes' => [],
            ],
            versoes: [
                'quadro7' => 'louos-q7-v1',
                'quadro10' => null,
                'quadro11' => null,
                'quadro11a' => null,
            ],
        );
    }

    private function riscoClassificado(): RiscoResult
    {
        return new RiscoResult(
            municipal: [
                'status' => 'classificado',
                'nivel' => 'baixo_a',
                'nivel_label' => 'Baixo A',
                'condicionantes' => [],
                'versao_regras' => 'decreto-32636-2020',
            ],
            sanitario: [
                'status' => 'nao_classificado',
                'nivel_original' => null,
                'nivel_final' => null,
                'reclassificado' => false,
                'condicionantes_perguntas' => [],
                'versao_regras' => null,
            ],
            encaminhamento: [
                'fluxo' => 'expresso',
                'dimensao_decisiva' => 'municipal',
                'motivo' => null,
                'gatilhos_acionados' => [],
            ],
            fundamentacao: ['Decreto Municipal nº 32.636/2020'],
            versoes: [
                'municipal' => 'decreto-32636-2020',
                'sanitario' => null,
            ],
        );
    }

    private function territoryExemplo(): TerritoryResult
    {
        return new TerritoryResult(
            bairro: ['status' => 'identificado', 'nome' => 'Barra', 'versao_camada' => 'bairros-v1'],
            via: ['status' => 'identificado', 'nome' => 'Av. Oceânica', 'distancia_m' => 12, 'versao_camada' => 'vias-v1'],
            zona: ['status' => 'indisponivel', 'motivo' => 'pendente SEDUR', 'versao_camada' => null],
            lote: ['status' => 'indisponivel', 'motivo' => 'pendente SEDUR', 'versao_camada' => null],
            restricoes: ['status' => 'identificado', 'itens' => [['nome' => 'ZEIS']], 'versao_camada' => 'restricoes-v1'],
        );
    }

    private function geocodeExemplo(): GeocodeResult
    {
        return new GeocodeResult(
            latitude: -13.0102,
            longitude: -38.5326,
            displayName: 'Av. Oceânica, Barra, Salvador',
            confidence: 0.7,
            address: ['suburb' => 'Barra', 'city' => 'Salvador'],
        );
    }
}
