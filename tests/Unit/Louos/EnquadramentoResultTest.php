<?php

namespace Tests\Unit\Louos;

use App\Enums\ResultadoViabilidade;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\EnquadramentoResult;
use Tests\TestCase;

/**
 * Contrato dos DTOs readonly do motor LOUOS (05-03+), espelhando
 * RiscoResult/TerritoryResult: cada Quadro é uma dimensão de shape estável
 * {status, ...dados, motivo, versao_regra} e o consolidado carrega o veredito,
 * a fundamentação e as condicionantes. toArray() em snake_case e versoes() por
 * quadro alimentam a auditoria (RN-002) e o consumidor EP07+.
 */
class EnquadramentoResultTest extends TestCase
{
    public function test_enquadramento_input_para_consulta_inicializa_defaults(): void
    {
        $input = EnquadramentoInput::paraConsulta(120.5, '4711301');

        $this->assertSame(120.5, $input->area);
        $this->assertSame('4711301', $input->cnaePrincipal);
        $this->assertSame([], $input->cnaesSecundarios);
        $this->assertNull($input->territory);
        $this->assertSame([], $input->vagasDeclaradas);
        $this->assertNull($input->data);
        $this->assertSame([], $input->versoesOverride);
    }

    public function test_enquadramento_input_carrega_territorio_e_overrides(): void
    {
        $territory = $this->territoryExemplo();

        $input = new EnquadramentoInput(
            area: 800.0,
            cnaePrincipal: '4711301',
            cnaesSecundarios: ['4729699'],
            territory: $territory,
            vagasDeclaradas: ['estacionamento' => 10],
            versoesOverride: ['louos_quadro7' => 'louos-quadro7-v2'],
        );

        $this->assertSame(800.0, $input->area);
        $this->assertSame(['4729699'], $input->cnaesSecundarios);
        $this->assertSame($territory, $input->territory);
        $this->assertSame(['estacionamento' => 10], $input->vagasDeclaradas);
        $this->assertSame(['louos_quadro7' => 'louos-quadro7-v2'], $input->versoesOverride);
    }

    public function test_to_array_e_versoes_expoem_o_contrato(): void
    {
        $array = $this->resultExemplo()->toArray();

        $this->assertSame(
            ['quadro7', 'quadro10', 'quadro11', 'quadro11a', 'consolidado', 'versoes'],
            array_keys($array),
        );

        $this->assertArrayHasKey('versao_regra', $array['quadro7']);
        $this->assertArrayHasKey('status', $array['quadro10']);
        $this->assertSame(EnquadramentoResult::STATUS_INDISPONIVEL, $array['quadro10']['status']);
        $this->assertArrayHasKey('fundamentacao', $array['consolidado']);
        $this->assertArrayHasKey('condicionantes', $array['consolidado']);

        $this->assertSame(
            ['quadro7' => 'louos-quadro7-v1', 'quadro10' => null, 'quadro11' => 'louos-quadro11-v1', 'quadro11a' => null],
            $this->resultExemplo()->versoes(),
        );
    }

    public function test_pendente_reflete_o_resultado_consolidado(): void
    {
        $this->assertTrue($this->resultExemplo(ResultadoViabilidade::Pendente->value)->pendente());

        $permitido = $this->resultExemplo(ResultadoViabilidade::Permitido->value);

        $this->assertFalse($permitido->pendente());
        $this->assertSame('permitido', $permitido->resultado());
    }

    public function test_constantes_de_status_cobrem_a_degradacao(): void
    {
        $this->assertSame('identificado', EnquadramentoResult::STATUS_IDENTIFICADO);
        $this->assertSame('nao_encontrado', EnquadramentoResult::STATUS_NAO_ENCONTRADO);
        $this->assertSame('indisponivel', EnquadramentoResult::STATUS_INDISPONIVEL);
    }

    private function resultExemplo(string $resultado = ResultadoViabilidade::PermitidoComCondicoes->value): EnquadramentoResult
    {
        return new EnquadramentoResult(
            quadro7: [
                'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                'grupo' => 'nR1',
                'subgrupo' => 'nR1-01',
                'motivo' => null,
                'versao_regra' => 'louos-quadro7-v1',
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
                'motivo' => 'Classe de via não informada',
                'versao_regra' => 'louos-quadro11-v1',
            ],
            quadro11a: [
                'status' => EnquadramentoResult::STATUS_NAO_ENCONTRADO,
                'condicoes' => [],
                'motivo' => null,
                'versao_regra' => null,
            ],
            consolidado: [
                'resultado' => $resultado,
                'fundamentacao' => ['Quadro 7 da Lei nº 9.148/2016'],
                'condicionantes' => [],
                'motivo' => 'Permissão por zona pendente (Quadro 10 indisponível)',
            ],
            versoes: [
                'quadro7' => 'louos-quadro7-v1',
                'quadro10' => null,
                'quadro11' => 'louos-quadro11-v1',
                'quadro11a' => null,
            ],
        );
    }

    private function territoryExemplo(): TerritoryResult
    {
        $indisponivel = ['status' => 'indisponivel', 'motivo' => 'pendente SEDUR', 'versao_camada' => null];

        return new TerritoryResult(
            bairro: ['status' => 'identificado', 'nome' => 'Barra', 'versao_camada' => 'bairros-v1'],
            via: ['status' => 'identificado', 'nome' => 'Av. Oceânica', 'distancia_m' => 12, 'versao_camada' => 'vias-v1'],
            zona: $indisponivel,
            lote: $indisponivel,
            restricoes: ['status' => 'nao_encontrado', 'itens' => [], 'versao_camada' => 'restricoes-v1'],
        );
    }
}
