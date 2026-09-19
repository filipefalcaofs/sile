<?php

namespace Tests\Unit\Analise;

use App\Enums\Fluxo;
use App\Services\Analise\JustificativaFundamentadaComposer;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Risco\RiscoResult;
use App\Services\Viabilidade\ConsultaViabilidadeResult;
use Tests\TestCase;

/**
 * A justificativa da ficha é um parecer por atividade: texto de analista
 * fundamentado nos fatos do motor (LOUOS + risco), sem inventar zona, grupo
 * ou desfecho.
 */
class JustificativaFundamentadaComposerTest extends TestCase
{
    public function test_redige_deferimento_fundamentado_com_quadros_zona_e_risco(): void
    {
        $texto = app(JustificativaFundamentadaComposer::class)->paraConsulta(
            $this->consultaPermitida(),
            [
                'cnae' => '4771701',
                'cnae_formatado' => '4771-7/01',
                'is_primary' => true,
                'descricao' => 'Comércio varejista de produtos farmacêuticos',
            ],
        );

        $this->assertStringContainsString('Lei nº 9.148/2016', $texto);
        $this->assertStringContainsString('4771-7/01', $texto);
        $this->assertStringContainsString('Comércio varejista de produtos farmacêuticos', $texto);
        $this->assertStringContainsString('75', $texto);
        $this->assertStringContainsString('enquadramento de uso', mb_strtolower($texto));
        $this->assertStringContainsString('nR1', $texto);
        $this->assertStringContainsString('não autoriza', mb_strtolower($texto));
        $this->assertStringContainsString('Quadro 10', $texto);
        $this->assertStringContainsString('ZCMe 2', $texto);
        $this->assertStringContainsString('Comércio', $texto);
        $this->assertStringContainsString('Decreto Municipal nº 32.636/2020', $texto);
        $this->assertStringContainsString('deferimento', mb_strtolower($texto));
        $this->assertGreaterThan(500, mb_strlen($texto));
    }

    public function test_redige_indeferimento_quando_quadro_10_proibe(): void
    {
        $texto = app(JustificativaFundamentadaComposer::class)->paraConsulta(
            $this->consultaProibida(),
            [
                'cnae' => '0111301',
                'cnae_formatado' => '8888-8/83',
                'is_primary' => true,
            ],
        );

        $this->assertStringContainsString('Quadro 10', $texto);
        $this->assertStringContainsString('proibido', mb_strtolower($texto));
        $this->assertStringContainsString('indeferimento', mb_strtolower($texto));
        $this->assertStringNotContainsString('manifesta-se pelo deferimento', mb_strtolower($texto));
    }

    public function test_nao_sugere_desfecho_quando_nao_ha_enquadramento_na_planilha(): void
    {
        $texto = app(JustificativaFundamentadaComposer::class)->paraConsulta(
            $this->consultaSemEnquadramento(),
            [
                'cnae' => '4721104',
                'cnae_formatado' => '4721-1/04',
                'is_primary' => false,
            ],
        );

        $this->assertStringContainsString('enquadramento de uso', mb_strtolower($texto));
        $this->assertStringContainsString('CNAE sem enquadramento', $texto);
        $this->assertStringNotContainsString('cNAE', $texto);
        $this->assertStringContainsString('análise técnica', mb_strtolower($texto));
        $this->assertStringNotContainsString('manifesta-se pelo deferimento', mb_strtolower($texto));
        $this->assertStringNotContainsString('manifesta-se pelo indeferimento', mb_strtolower($texto));
    }

    public function test_omite_exigencia_de_vagas_ainda_nao_parametrizada(): void
    {
        $consulta = $this->consultaPermitida();
        $consulta = new ConsultaViabilidadeResult(
            entrada: $consulta->entrada,
            geocode: $consulta->geocode,
            territory: $consulta->territory,
            enquadramento: new EnquadramentoResult(
                enquadramento: $consulta->enquadramento->enquadramento,
                quadro10: $consulta->enquadramento->quadro10,
                quadro11a: $consulta->enquadramento->quadro11a,
                consolidado: [
                    ...$consulta->enquadramento->consolidado,
                    'condicionantes' => [[
                        'tipo' => 'vagas',
                        'motivo' => 'Exigência de vagas não parametrizada para o grupo',
                        'exigido' => null,
                    ]],
                ],
                versoes: $consulta->enquadramento->versoes(),
            ),
            risco: $consulta->risco,
            avisos: $consulta->avisos,
        );

        $texto = app(JustificativaFundamentadaComposer::class)->paraConsulta($consulta, [
            'cnae' => '4771701',
            'cnae_formatado' => '4771-7/01',
        ]);

        $this->assertStringNotContainsString('não parametrizada', $texto);
        $this->assertStringContainsString('sem condicionantes urbanísticas incidentes', $texto);
    }

    public function test_redige_a_partir_do_snapshot_sem_recomputar_o_motor(): void
    {
        $consulta = $this->consultaPermitida();
        $item = [
            'cnae' => '4771701',
            'cnae_formatado' => '4771-7/01',
            'descricao' => 'Comércio varejista de produtos farmacêuticos',
        ];

        $aoVivo = app(JustificativaFundamentadaComposer::class)->paraConsulta($consulta, $item);
        $doSnapshot = app(JustificativaFundamentadaComposer::class)->paraSnapshot($consulta->toArray(), $item);

        $this->assertSame($aoVivo, $doSnapshot);
    }

    private function consultaPermitida(): ConsultaViabilidadeResult
    {
        return $this->consulta(
            resultado: 'permitido',
            enquadramento: [
                'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                'grupo' => 'nR1',
                'subgrupo' => 'nR1-01',
                'motivo' => 'O CNAE 4771-7/01 com área 75 m² enquadra-se no grupo nR1 (nR1-01) da LOUOS (07.01.05).',
            ],
            quadro10: [
                'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                'permissao' => 'permitido',
                'motivo' => 'O grupo nR1 é permitido na zona ZCMe 2 segundo o Quadro 10 da LOUOS.',
            ],
            motivo: 'Permitido: o CNAE 4771-7/01 (área 75 m²) classificou-se no grupo nR1 pelo enquadramento da planilha vigente e esse grupo é permitido na zona ZCMe 2 pelo Quadro 10.',
            zona: ['status' => 'identificado', 'nome' => 'ZCMe 2'],
        );
    }

    private function consultaProibida(): ConsultaViabilidadeResult
    {
        return $this->consulta(
            resultado: 'nao_permitido',
            enquadramento: [
                'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                'grupo' => 'nR3',
                'subgrupo' => 'nR3-01',
                'motivo' => 'O CNAE 8888-8/83 enquadra-se no grupo nR3 da LOUOS.',
            ],
            quadro10: [
                'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                'permissao' => 'proibido',
                'motivo' => 'O grupo nR3 é proibido na zona ZR-1 segundo o Quadro 10 da LOUOS.',
            ],
            motivo: 'Não permitido: o grupo nR3 é proibido na zona ZR-1 pelo Quadro 10.',
            zona: ['status' => 'identificado', 'nome' => 'ZR-1'],
            cnae: '0111301',
            area: 120.0,
        );
    }

    private function consultaSemEnquadramento(): ConsultaViabilidadeResult
    {
        return $this->consulta(
            resultado: 'pendente',
            enquadramento: [
                'status' => EnquadramentoResult::STATUS_NAO_ENCONTRADO,
                'grupo' => null,
                'subgrupo' => null,
                'motivo' => 'CNAE sem enquadramento parametrizado na planilha vigente',
            ],
            quadro10: [
                'status' => EnquadramentoResult::STATUS_NAO_ENCONTRADO,
                'permissao' => null,
                'motivo' => 'Sem grupo de uso, o Quadro 10 não se aplica.',
            ],
            motivo: 'CNAE sem enquadramento parametrizado na planilha vigente',
            zona: ['status' => 'identificado', 'nome' => 'ZCMe 2'],
            cnae: '4721104',
            area: 75.0,
        );
    }

    /**
     * @param  array<string, mixed>  $enquadramento
     * @param  array<string, mixed>  $quadro10
     * @param  array<string, mixed>  $zona
     */
    private function consulta(
        string $resultado,
        array $enquadramento,
        array $quadro10,
        string $motivo,
        array $zona,
        string $cnae = '4771701',
        float $area = 75.0,
    ): ConsultaViabilidadeResult {
        $formatado = strlen($cnae) === 7
            ? substr($cnae, 0, 4).'-'.substr($cnae, 4, 1).'/'.substr($cnae, 5, 2)
            : $cnae;

        return new ConsultaViabilidadeResult(
            entrada: [
                'tipo' => 'ponto',
                'cnae' => $cnae,
                'cnae_formatado' => $formatado,
                'area' => $area,
            ],
            geocode: null,
            territory: new TerritoryResult(
                bairro: ['status' => 'identificado', 'nome' => 'Comércio', 'versao_camada' => 'bairro-2024'],
                via: ['status' => 'identificado', 'nome' => 'Av. Estados Unidos', 'versao_camada' => 'via-2024'],
                zona: [...$zona, 'versao_camada' => 'zona-2026'],
                lote: ['status' => 'nao_encontrado', 'versao_camada' => null],
                restricoes: ['status' => 'nao_encontrado', 'itens' => [], 'versao_camada' => null],
            ),
            enquadramento: new EnquadramentoResult(
                enquadramento: $enquadramento,
                quadro10: $quadro10,
                quadro11a: [
                    'status' => EnquadramentoResult::STATUS_NAO_ENCONTRADO,
                    'condicoes' => [],
                    'motivo' => null,
                ],
                consolidado: [
                    'resultado' => $resultado,
                    'fundamentacao' => [
                        'Lei nº 9.148/2016 (LOUOS) — nR1-01',
                        'Quadro 10 da Lei nº 9.148/2016',
                    ],
                    'condicionantes' => [],
                    'motivo' => $motivo,
                ],
                versoes: [
                    'risco_tratamento' => 'planilha-20-08-26',
                    'quadro10' => 'lei-9148-2016-quadro10',
                    'quadro11a' => null,
                ],
            ),
            risco: new RiscoResult(
                municipal: [
                    'status' => RiscoResult::STATUS_CLASSIFICADO,
                    'nivel' => 'baixo_a',
                    'nivel_label' => 'Baixo',
                    'condicionantes' => [],
                    'versao_regras' => 'decreto-32636-2020',
                ],
                sanitario: [
                    'status' => RiscoResult::STATUS_NAO_CLASSIFICADO,
                    'nivel_original' => null,
                    'nivel_final' => null,
                    'reclassificado' => false,
                    'condicionantes_perguntas' => [],
                ],
                encaminhamento: [
                    'fluxo' => Fluxo::Analise->value,
                    'dimensao_decisiva' => 'municipal',
                    'motivo' => 'Nível baixo_a (municipal) encaminhado para análise técnica',
                    'gatilhos_acionados' => [],
                ],
                fundamentacao: ['Decreto Municipal nº 32.636/2020'],
                versoes: ['municipal' => 'decreto-32636-2020', 'sanitario' => null],
            ),
        );
    }
}
