<?php

namespace Tests\Feature\Tratamento;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use App\Services\Tratamento\TratamentoRamoInput;
use App\Services\Tratamento\TratamentoRamoResolver;
use App\Services\Tratamento\TratamentoRamoResult;
use App\Services\Tratamento\TratamentoRamosSedur;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Ramos curados das regras 9, 29, 30, 31, 33, 37, 38, 39, 40, 43, 53 e 55 conforme o texto da
 * planilha 20.08.26 e as respostas da SEDUR de 07/10/2026. Cada teste leva no
 * nome a frase da planilha que o motiva.
 */
class TratamentoRamosSedurTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const SO_ORGAO = 'Esta atividade só é permitida para órgãos da Administração Pública';

    protected function setUp(): void
    {
        parent::setUp();

        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-20-08-26',
        ]);
        (new TratamentoRegrasImportService)->import($versao, database_path('data/regras-20-08-26'));
        (new TratamentoRamosSedur)->carregar($versao, database_path('data/regras-20-08-26/regras-fluxo-sedur.json'));
    }

    /**
     * @param  array<int, bool|int|list<int>>  $respostas
     */
    private function resolver(string $cnae, array $respostas, float $area = 100.0, ?string $categoria = null, ?string $tipo = null): TratamentoRamoResult
    {
        return app(TratamentoRamoResolver::class)->resolver(new TratamentoRamoInput(
            cnae: $cnae,
            respostas: $respostas,
            areaUtilizada: $area,
            tipoImovel: $tipo === null ? null : TipoImovel::fromRegin($tipo, TipoImovelCatalog::sedur200826()),
            categoriaEmpresa: $categoria,
        ));
    }

    private function assertEnquadra(TratamentoRamoResult $ramo, string $codigo, string $fluxo): void
    {
        $this->assertSame('resolvido', $ramo->status, (string) $ramo->motivo);
        $this->assertFalse($ramo->bloqueia);
        $this->assertSame($codigo, $ramo->codigoLouos);
        $this->assertSame($fluxo, $ramo->fluxo);
    }

    private function assertBloqueia(TratamentoRamoResult $ramo, string $mensagem): void
    {
        $this->assertTrue($ramo->bloqueia);
        $this->assertStringStartsWith($mensagem, (string) $ramo->motivo);
    }

    // ---- Regra 38: Administração Pública (8411-6/00, pergunta 7) ----

    public function test_regra_38_orgao_publico_sim_na_p7_enquadra_orgaos_dos_poderes_no_expresso(): void
    {
        $this->assertEnquadra($this->resolver('8411-6/00', [7 => true], categoria: 'orgao_publico'), '07.11.03', 'expresso');
    }

    public function test_regra_38_orgao_publico_em_galpao_ou_container_vai_ao_semiexpresso(): void
    {
        $this->assertEnquadra($this->resolver('8411-6/00', [7 => true], categoria: 'orgao_publico', tipo: 'Galpão'), '07.11.03', 'semiexpresso');
        $this->assertEnquadra($this->resolver('8411-6/00', [7 => true], categoria: 'orgao_publico', tipo: 'Container'), '07.11.03', 'semiexpresso');
    }

    public function test_regra_38_categoria_diferente_de_orgao_publico_nao_prossegue(): void
    {
        $this->assertBloqueia($this->resolver('8411-6/00', [7 => true], categoria: 'outra'), self::SO_ORGAO);
    }

    public function test_regra_38_orgao_publico_com_nao_na_p7_nao_prossegue(): void
    {
        $this->assertBloqueia($this->resolver('8411-6/00', [7 => false], categoria: 'orgao_publico'), self::SO_ORGAO);
    }

    public function test_regra_38_imovel_residencial_nao_prossegue(): void
    {
        $this->assertBloqueia(
            $this->resolver('8411-6/00', [7 => true], categoria: 'orgao_publico', tipo: 'Edificação Residencial'),
            'A (s) atividade (s) solicitada (s) não é (são) permitida(s) em imóvel residencial conforme Decreto Municipal nº 29.987/2018',
        );
    }

    public function test_regra_38_sem_a_categoria_da_empresa_o_motor_nao_decide(): void
    {
        $ramo = $this->resolver('8411-6/00', [7 => true], categoria: null);

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertFalse($ramo->bloqueia);
        $this->assertSame('Categoria da empresa ausente para a regra que depende dela', $ramo->motivo);
    }

    // ---- Regra 39: CNAE 8423-0/00 (pergunta 6) ----

    public function test_regra_39_orgao_publico_nao_na_p6_enquadra_orgaos_dos_poderes(): void
    {
        $this->assertEnquadra($this->resolver('8423-0/00', [6 => false], categoria: 'orgao_publico'), '07.11.03', 'expresso');
        $this->assertEnquadra($this->resolver('8423-0/00', [6 => false], categoria: 'orgao_publico', tipo: 'Galpão'), '07.11.03', 'semiexpresso');
    }

    public function test_regra_39_outra_categoria_sim_na_p6_enquadra_escritorio(): void
    {
        $this->assertEnquadra($this->resolver('8423-0/00', [6 => true], categoria: 'outra'), '07.12.13', 'expresso');
        $this->assertEnquadra($this->resolver('8423-0/00', [6 => true], categoria: 'outra', tipo: 'Container'), '07.12.13', 'semiexpresso');
    }

    public function test_regra_39_resposta_incompativel_com_a_categoria_nao_prossegue(): void
    {
        $mensagem = 'Resposta selecionada para a CNAE 8423-0/00 não é compatível com a categoria da empresa indicada nesta etapa.';

        $this->assertBloqueia($this->resolver('8423-0/00', [6 => true], categoria: 'orgao_publico'), $mensagem);
        $this->assertBloqueia($this->resolver('8423-0/00', [6 => false], categoria: 'outra'), $mensagem);
    }

    public function test_regra_39_imovel_residencial_nao_prossegue(): void
    {
        $this->assertBloqueia(
            $this->resolver('8423-0/00', [6 => false], categoria: 'orgao_publico', tipo: 'Edificação Residencial'),
            'A atividade solicitada não é permitida em imóvel residencial conforme Decreto Municipal nº 29.987/2018',
        );
    }

    // ---- Regra 55: CNAE 3514-0/00 (perguntas 31 e 32) ----

    public function test_regra_55_concessionaria_enquadra_gestao_de_servicos_publicos_no_expresso(): void
    {
        $this->assertEnquadra($this->resolver('3514-0/00', [31 => true, 32 => 1]), '07.11.01', 'expresso');
        $this->assertEnquadra($this->resolver('3514-0/00', [31 => true, 32 => 1], area: 2000.0), '07.11.01', 'expresso');
    }

    public function test_regra_55_distribuicao_transmissao_ou_recarga_enquadra_central_eletrica_no_semiexpresso(): void
    {
        foreach ([2, 3, 4] as $opcao) {
            $this->assertEnquadra($this->resolver('3514-0/00', [31 => true, 32 => $opcao]), '08.01.11', 'semiexpresso');
        }
    }

    public function test_regra_55_em_galpao_ou_container_vai_ao_semiexpresso(): void
    {
        $this->assertEnquadra($this->resolver('3514-0/00', [31 => true, 32 => 1], tipo: 'Galpão'), '07.11.01', 'semiexpresso');
        $this->assertEnquadra($this->resolver('3514-0/00', [31 => true, 32 => 3], tipo: 'Container'), '08.01.11', 'semiexpresso');
    }

    public function test_regra_55_imovel_residencial_nao_prossegue(): void
    {
        $this->assertBloqueia(
            $this->resolver('3514-0/00', [31 => true, 32 => 1], tipo: 'Edificação Residencial'),
            'A atividade solicitada não é permitida em imóvel residencial conforme Decreto Municipal nº 29.987/2018',
        );
    }

    // ---- Regra 30: CNAE 8121-4/00 ----

    public function test_regra_30_ate_1250_fora_de_galpao_residencial_e_container_segue_o_expresso(): void
    {
        $this->assertEnquadra($this->resolver('8121-4/00', [11 => true]), '07.12.13', 'expresso');
        $this->assertEnquadra($this->resolver('8121-4/00', [11 => false]), '07.12.13', 'expresso');
    }

    public function test_regra_30_fora_das_condicoes_do_expresso_vai_ao_semiexpresso(): void
    {
        $this->assertSame('semiexpresso', $this->resolver('8121-4/00', [11 => true], area: 2000.0)->fluxo);
        $this->assertSame('semiexpresso', $this->resolver('8121-4/00', [11 => true], tipo: 'Galpão')->fluxo);
        $this->assertSame('semiexpresso', $this->resolver('8121-4/00', [11 => true], tipo: 'Edificação Residencial')->fluxo);
    }

    // ---- Regra 31: CNAE 4789-0/99 (perguntas 10 e 11) ----

    public function test_regra_31_nao_na_p11_enquadra_escritorio_no_expresso_em_qualquer_area(): void
    {
        $this->assertEnquadra($this->resolver('4789-0/99', [10 => 3, 11 => false]), '07.12.13', 'expresso');
        $this->assertEnquadra($this->resolver('4789-0/99', [10 => 3, 11 => false], area: 2000.0), '07.12.13', 'expresso');
    }

    public function test_regra_31_sim_na_p11_com_opcoes_diversificadas_enquadra_comercio_diversificado(): void
    {
        foreach ([1, 7, 11, 16] as $opcao) {
            $this->assertEnquadra($this->resolver('4789-0/99', [10 => $opcao, 11 => true]), '07.03.11', 'expresso');
        }

        $this->assertEnquadra($this->resolver('4789-0/99', [10 => 3, 11 => true], area: 2000.0), '07.03.11', 'expresso');
    }

    public function test_regra_31_opcao_10_com_sim_na_p11_enquadra_comercio_especializado(): void
    {
        $this->assertEnquadra($this->resolver('4789-0/99', [10 => 10, 11 => true]), '07.04.17', 'expresso');
        $this->assertEnquadra($this->resolver('4789-0/99', [10 => 10, 11 => true], area: 2000.0), '07.04.17', 'expresso');
    }

    public function test_regra_31_em_galpao_residencial_ou_container_vai_ao_semiexpresso_com_o_mesmo_enquadramento(): void
    {
        $this->assertEnquadra($this->resolver('4789-0/99', [10 => 3, 11 => false], tipo: 'Galpão'), '07.12.13', 'semiexpresso');
        $this->assertEnquadra($this->resolver('4789-0/99', [10 => 3, 11 => true], tipo: 'Container'), '07.03.11', 'semiexpresso');
        $this->assertEnquadra($this->resolver('4789-0/99', [10 => 10, 11 => true], tipo: 'Edificação Residencial'), '07.04.17', 'semiexpresso');
    }

    // ---- Regra 29: CNAE 5223-1/00 (perguntas 13 e 14, as ligadas ao CNAE) ----

    public function test_regra_29_opcoes_1_e_2_enquadram_estacionamento_no_semiexpresso(): void
    {
        $this->assertEnquadra($this->resolver('5223-1/00', [13 => true, 14 => 1]), '07.08.01', 'semiexpresso');
        $this->assertEnquadra($this->resolver('5223-1/00', [13 => true, 14 => 2]), '07.08.01', 'semiexpresso');
    }

    public function test_regra_29_opcao_3_enquadra_estacionamento_de_veiculos_pesados(): void
    {
        $this->assertEnquadra($this->resolver('5223-1/00', [13 => true, 14 => 3]), '07.08.05', 'semiexpresso');
    }

    // ---- Regra 37: lavanderia (9601-7/01, P21 e P22) e toalheiro (9601-7/03, P23 e P24) ----

    public function test_regra_37_opcao_1_ate_1250_enquadra_lavanderia_industrial_no_expresso(): void
    {
        $this->assertEnquadra($this->resolver('9601-7/01', [21 => true, 22 => 1]), '07.09.15', 'expresso');
        $this->assertEnquadra($this->resolver('9601-7/03', [23 => true, 24 => 1]), '07.09.15', 'expresso');
    }

    public function test_regra_37_opcao_2_enquadra_lavanderia_nao_industrial_no_expresso_em_qualquer_area(): void
    {
        $this->assertEnquadra($this->resolver('9601-7/01', [21 => true, 22 => 2]), '07.09.03', 'expresso');
        $this->assertEnquadra($this->resolver('9601-7/03', [23 => true, 24 => 2], area: 2000.0), '07.09.03', 'expresso');
    }

    public function test_regra_37_industrial_acima_de_1250_ou_em_galpao_vai_ao_semiexpresso(): void
    {
        $this->assertEnquadra($this->resolver('9601-7/01', [21 => true, 22 => 1], area: 2000.0), '07.09.15', 'semiexpresso');
        $this->assertEnquadra($this->resolver('9601-7/01', [21 => true, 22 => 1], tipo: 'Galpão'), '07.09.15', 'semiexpresso');
        $this->assertEnquadra($this->resolver('9601-7/03', [23 => true, 24 => 2], tipo: 'Container'), '07.09.03', 'semiexpresso');
    }

    // ---- Regra 43: organizações religiosas (9491-0/00, pergunta 1: Subdivisão CNAE) ----

    public function test_regra_43_opcao_1_culto_religioso_enquadra_templo(): void
    {
        $this->assertEnquadra($this->resolver('9491-0/00', [1 => 1]), '07.07.02', 'semiexpresso');
    }

    public function test_regra_43_organizacao_filosofica_ou_irmandade_enquadra_outras_associacoes(): void
    {
        $this->assertEnquadra($this->resolver('9491-0/00', [1 => 2]), '07.10.07', 'semiexpresso');
        $this->assertEnquadra($this->resolver('9491-0/00', [1 => 4]), '07.10.07', 'semiexpresso');
    }

    public function test_regra_43_convento_ou_mosteiro_enquadra_casa_de_geriatria_e_similares(): void
    {
        $this->assertEnquadra($this->resolver('9491-0/00', [1 => 3]), '07.13.02', 'semiexpresso');
    }

    // ---- Regra 40: outros serviços pessoais (9609-2/99, perguntas 1 e 11) ----

    public function test_regra_40_opcoes_de_escritorio_com_nao_na_p11_enquadram_escritorio_no_expresso(): void
    {
        foreach ([1, 2, 3, 5, 6, 7, 10, 11, 12, 13, 14, 15, 16, 17] as $opcao) {
            $this->assertEnquadra($this->resolver('9609-2/99', [1 => [$opcao], 11 => false]), '07.12.13', 'expresso');
        }

        $this->assertEnquadra($this->resolver('9609-2/99', [1 => [1, 2], 11 => false], area: 2000.0), '07.12.13', 'expresso');
    }

    public function test_regra_40_opcoes_de_escritorio_com_sim_na_p11_enquadram_o_segundo_enquadramento(): void
    {
        $this->assertEnquadra($this->resolver('9609-2/99', [1 => [10], 11 => true]), '07.12.14', 'expresso');
        $this->assertEnquadra($this->resolver('9609-2/99', [1 => [10], 11 => true], area: 2000.0), '07.12.14', 'expresso');
    }

    public function test_regra_40_manobrista_chuveiro_ou_sanitario_vai_ao_semiexpresso(): void
    {
        foreach ([4, 8, 9] as $opcao) {
            $this->assertEnquadra($this->resolver('9609-2/99', [1 => [$opcao], 11 => true]), '07.12.14', 'semiexpresso');
            $this->assertEnquadra($this->resolver('9609-2/99', [1 => [$opcao], 11 => false]), '07.12.13', 'semiexpresso');
        }
    }

    public function test_regra_40_mistura_de_opcoes_segue_a_mais_restritiva(): void
    {
        $this->assertEnquadra($this->resolver('9609-2/99', [1 => [2, 4], 11 => true]), '07.12.14', 'semiexpresso');
    }

    public function test_regra_40_em_galpao_residencial_ou_container_vai_ao_semiexpresso(): void
    {
        $this->assertEnquadra($this->resolver('9609-2/99', [1 => [10], 11 => false], tipo: 'Galpão'), '07.12.13', 'semiexpresso');
        $this->assertEnquadra($this->resolver('9609-2/99', [1 => [10], 11 => true], tipo: 'Edificação Residencial'), '07.12.14', 'semiexpresso');
    }

    // ---- Regra 9: alimentação em barracas, food truck e máquinas (5612-1/00, perguntas 1 e 11) ----

    private const AMBULANTE = 'A SEDUR não licencia serviços ambulantes de alimentação, o requerente deverá solicitar licença junto a SEMOP (Secretaria Municipal de Ordem Pública).';

    private const FOOD_TRUCK = 'A SEDUR licencia apenas o escritório para a atividade de Food Truck. Deverá solicitar licença específica no órgão competente para o local do funcionamento';

    public function test_regra_9_opcao_1_ambulante_nao_prossegue(): void
    {
        $this->assertBloqueia($this->resolver('5612-1/00', [1 => [1], 11 => true]), self::AMBULANTE);
        $this->assertBloqueia($this->resolver('5612-1/00', [1 => [1], 11 => false]), self::AMBULANTE);
    }

    public function test_regra_9_food_truck_com_sim_na_p11_nao_prossegue(): void
    {
        $this->assertBloqueia($this->resolver('5612-1/00', [1 => [2], 11 => true]), self::FOOD_TRUCK);
    }

    public function test_regra_9_food_truck_com_nao_na_p11_enquadra_escritorio_no_semiexpresso(): void
    {
        $this->assertEnquadra($this->resolver('5612-1/00', [1 => [2], 11 => false]), '07.12.13', 'semiexpresso');
    }

    public function test_regra_9_maquina_automatica_com_nao_na_p11_enquadra_escritorio_no_expresso(): void
    {
        $this->assertEnquadra($this->resolver('5612-1/00', [1 => [3], 11 => false]), '07.12.13', 'expresso');
        $this->assertEnquadra($this->resolver('5612-1/00', [1 => [3], 11 => false], area: 2000.0), '07.12.13', 'expresso');
    }

    public function test_regra_9_maquina_automatica_com_sim_na_p11_enquadra_o_segundo_enquadramento_no_semiexpresso(): void
    {
        $this->assertEnquadra($this->resolver('5612-1/00', [1 => [3], 11 => true]), '07.02.03', 'semiexpresso');
        $this->assertEnquadra($this->resolver('5612-1/00', [1 => [3], 11 => true], area: 2000.0), '07.02.03', 'semiexpresso');
    }

    public function test_regra_9_maquina_automatica_em_galpao_residencial_ou_container_vai_ao_semiexpresso(): void
    {
        $this->assertEnquadra($this->resolver('5612-1/00', [1 => [3], 11 => false], tipo: 'Galpão'), '07.12.13', 'semiexpresso');
        $this->assertEnquadra($this->resolver('5612-1/00', [1 => [3], 11 => true], tipo: 'Container'), '07.02.03', 'semiexpresso');
    }

    public function test_regra_9_imovel_residencial_nao_prossegue_qualquer_que_seja_a_opcao(): void
    {
        foreach ([[3], [2]] as $opcoes) {
            $this->assertBloqueia(
                $this->resolver('5612-1/00', [1 => $opcoes, 11 => false], tipo: 'Edificação Residencial'),
                'A atividade solicitada não é permitida em imóvel residencial conforme Decreto Municipal nº 29.987/2018',
            );
        }
    }

    public function test_regra_9_mistura_de_opcoes_segue_a_mais_restritiva(): void
    {
        $this->assertBloqueia($this->resolver('5612-1/00', [1 => [1, 3], 11 => false]), self::AMBULANTE);
        $this->assertBloqueia($this->resolver('5612-1/00', [1 => [2, 3], 11 => true]), self::FOOD_TRUCK);
        $this->assertEnquadra($this->resolver('5612-1/00', [1 => [2, 3], 11 => false]), '07.12.13', 'semiexpresso');
    }

    // ---- Regra 33: animais de estimação (9609-2/07, perguntas 1 e 11) ----

    public function test_regra_33_nao_na_p11_enquadra_escritorio_no_expresso_em_qualquer_area(): void
    {
        $this->assertEnquadra($this->resolver('9609-2/07', [1 => [1], 11 => false]), '07.12.13', 'expresso');
        $this->assertEnquadra($this->resolver('9609-2/07', [1 => [2], 11 => false], area: 2000.0), '07.12.13', 'expresso');
    }

    public function test_regra_33_sim_na_p11_enquadra_adestramento_e_hospedagem_de_animais_no_semiexpresso(): void
    {
        foreach ([[1], [2], [3], [1, 2, 3]] as $opcoes) {
            $this->assertEnquadra($this->resolver('9609-2/07', [1 => $opcoes, 11 => true]), '07.12.02', 'semiexpresso');
        }
    }

    public function test_regra_33_em_galpao_residencial_ou_container_vai_ao_semiexpresso(): void
    {
        $this->assertEnquadra($this->resolver('9609-2/07', [1 => [1], 11 => false], tipo: 'Galpão'), '07.12.13', 'semiexpresso');
        $this->assertEnquadra($this->resolver('9609-2/07', [1 => [1], 11 => true], tipo: 'Edificação Residencial'), '07.12.02', 'semiexpresso');
    }

    // ---- Regra 53: revenda de GLP (4682-6/00 e 4784-9/00, perguntas 25 a 29) ----

    public function test_regra_53_nao_na_p25_enquadra_escritorio_e_remete_a_critica(): void
    {
        $this->assertEnquadra($this->resolver('4682-6/00', [25 => false]), '07.12.13', 'semiexpresso');
    }

    public function test_regra_53_sem_plataformas_a_capacidade_da_p29_define_o_grupo(): void
    {
        foreach ([1, 2] as $opcao) {
            $ramo = $this->resolver('4682-6/00', [25 => true, 26 => false, 29 => $opcao]);
            $this->assertEnquadra($ramo, '07.04.16', 'semiexpresso');
            $this->assertSame('nR1-04', $ramo->subgrupo);
        }

        foreach ([3, 5, 8] as $opcao) {
            $ramo = $this->resolver('4784-9/00', [25 => true, 26 => false, 29 => $opcao]);
            $this->assertEnquadra($ramo, '07.04.16', 'semiexpresso');
            $this->assertSame('nR2-04', $ramo->subgrupo);
        }
    }

    public function test_regra_53_com_plataformas_o_produto_por_capacidade_define_o_grupo(): void
    {
        // 2 plataformas x 520 kg = 1.040 e 3 x 520 = 1.560: até 1.560, nR1-04.
        $this->assertSame('nR1-04', $this->resolver('4682-6/00', [25 => true, 26 => true, 27 => 1, 28 => 1])->subgrupo);
        $this->assertSame('nR1-04', $this->resolver('4682-6/00', [25 => true, 26 => true, 27 => 2, 28 => 1])->subgrupo);
        // 2 x 1.560 = 3.120: acima de 1.560, nR2-04.
        $ramo = $this->resolver('4682-6/00', [25 => true, 26 => true, 27 => 1, 28 => 2]);
        $this->assertEnquadra($ramo, '07.04.16', 'semiexpresso');
        $this->assertSame('nR2-04', $ramo->subgrupo);
    }
}
