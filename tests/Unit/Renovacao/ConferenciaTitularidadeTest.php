<?php

namespace Tests\Unit\Renovacao;

use App\Services\Cnpj\CnpjData;
use App\Services\Legado\TvlLegadaDetalhe;
use App\Services\Renovacao\ConferenciaResultado;
use App\Services\Renovacao\ConferenciaTitularidade;
use App\Services\Renovacao\EnderecoDoImovel;
use App\Services\Renovacao\EnderecoImovelIndisponivelException;
use App\Services\Renovacao\EnderecoImovelNaoEncontradoException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConferenciaTitularidadeTest extends TestCase
{
    private const RAZAO = 'BMS COMERCIO VAREJISTA DE ALIMENTOS LTDA';

    public function test_razao_e_endereco_do_imovel_iguais_conferem(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA DA ENGOMADEIRA', numero: '508', cep: '41200000'),
            $this->imovel('RUA DA ENGOMADEIRA', '508', '41200-000'),
        );

        $this->assertTrue($resultado->confere);
        $this->assertNull($resultado->motivo);
    }

    public function test_razao_igual_com_sufixo_e_pontuacao_diferentes_confere(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: 'Bms Comércio Varejista de Alimentos Ltda.', cep: '41.200-000'),
            $this->imovel(cep: '41200-000'),
        );

        $this->assertTrue($resultado->confere);
    }

    public function test_razao_com_sufixo_apenas_de_um_lado_confere(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: 'PADARIA ESTRELA ME'),
            $this->empresa(razao: 'Padaria Estrela', cep: '41200000'),
            $this->imovel(cep: '41200-000'),
        );

        $this->assertTrue($resultado->confere);
    }

    public function test_razao_diferente_nao_confere(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: 'OUTRA EMPRESA LTDA', rua: 'RUA A', numero: '1', cep: '41200000'),
            $this->imovel('RUA A', '1', '41200-000'),
        );

        $this->assertFalse($resultado->confere);
        $this->assertSame('A razão social deste CNPJ não confere com a da viabilidade anterior.', $resultado->motivo);
    }

    public function test_razao_contida_na_outra_nao_confere(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: 'BMS COMERCIO LTDA'),
            $this->empresa(razao: 'BMS COMERCIO VAREJISTA LTDA', cep: '41200000'),
            $this->imovel(cep: '41200-000'),
        );

        $this->assertFalse($resultado->confere);
    }

    public function test_razao_diferente_nao_chama_o_cadastro(): void
    {
        $resultado = (new ConferenciaTitularidade)->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: 'OUTRA EMPRESA LTDA', cep: '41200000'),
            function (): EnderecoDoImovel {
                throw new \LogicException('O cadastro não deveria ser consultado.');
            },
        );

        $this->assertFalse($resultado->confere);
        $this->assertSame('A razão social deste CNPJ não confere com a da viabilidade anterior.', $resultado->motivo);
    }

    public function test_tvl_sem_razao_social_nao_confere_e_nao_chama_o_cadastro(): void
    {
        foreach ([null, '', '  '] as $razao) {
            $resultado = (new ConferenciaTitularidade)->conferir(
                $this->tvl(razao: $razao),
                $this->empresa(razao: self::RAZAO, cep: '41200000'),
                function (): EnderecoDoImovel {
                    throw new \LogicException('O cadastro não deveria ser consultado.');
                },
            );

            $this->assertFalse($resultado->confere);
            $this->assertSame(
                'A viabilidade anterior não tem razão social registrada para conferir a titularidade.',
                $resultado->motivo,
            );
        }
    }

    public function test_divergencia_de_endereco_usa_m_diverge(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'AV SETE DE SETEMBRO', numero: '10', cep: '40060001'),
            $this->imovel('RUA DA ENGOMADEIRA', '508', '41200-000'),
        );

        $this->assertFalse($resultado->confere);
        $this->assertSame(ConferenciaTitularidade::M_DIVERGE, $resultado->motivo);
        $this->assertSame(
            'O endereço deste CNPJ não confere com o do imóvel da viabilidade anterior. Procure a SEDUR.',
            ConferenciaTitularidade::M_DIVERGE,
        );
    }

    public function test_endereco_gravado_na_tvl_nao_e_usado(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO, logradouro: 'RUA DA ENGOMADEIRA', numero: '508', cep: '41200000'),
            $this->empresa(razao: self::RAZAO, rua: 'RUA DA ENGOMADEIRA', numero: '508', cep: '41200000'),
            $this->imovel('AV SETE DE SETEMBRO', '10', '40060-001'),
        );

        $this->assertFalse($resultado->confere);
        $this->assertSame(ConferenciaTitularidade::M_DIVERGE, $resultado->motivo);
    }

    public function test_imovel_nao_encontrado_devolve_o_motivo_da_excecao(): void
    {
        $resultado = (new ConferenciaTitularidade)->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, cep: '41200000'),
            function (): EnderecoDoImovel {
                throw new EnderecoImovelNaoEncontradoException('Motivo do resolvedor.');
            },
        );

        $this->assertFalse($resultado->confere);
        $this->assertSame('Motivo do resolvedor.', $resultado->motivo);
    }

    public function test_cadastro_indisponivel_propaga_a_excecao(): void
    {
        $this->expectException(EnderecoImovelIndisponivelException::class);

        (new ConferenciaTitularidade)->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, cep: '41200000'),
            function (): EnderecoDoImovel {
                throw new EnderecoImovelIndisponivelException('fora do ar');
            },
        );
    }

    public function test_endereco_confere_por_logradouro_e_numero_quando_o_imovel_nao_tem_cep(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'R DA ENGOMADEIRA', numero: '508', cep: '41200000'),
            $this->imovel('RUA DA ENGOMADEIRA', '508', null),
        );

        $this->assertTrue($resultado->confere);
    }

    public function test_avenida_e_av_sao_equivalentes_e_espacos_duplos_sao_tolerados(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'AV. Paralela', numero: '100', cep: null),
            $this->imovel('AVENIDA  PARALELA', '100', null),
        );

        $this->assertTrue($resultado->confere);
    }

    public function test_cep_igual_confere_mesmo_com_logradouro_diferente(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA NOVA', numero: '9', cep: '41200000'),
            $this->imovel('1234', null, '41200-000'),
        );

        $this->assertTrue($resultado->confere);
    }

    public function test_cep_so_de_um_lado_nao_confere_por_cep(): void
    {
        $semCepNaEmpresa = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA NOVA', numero: '9', cep: null),
            $this->imovel('RUA VELHA', '1', '41200-000'),
        );
        $semCepNoImovel = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA NOVA', numero: '9', cep: '41200000'),
            $this->imovel('RUA VELHA', '1', null),
        );

        $this->assertFalse($semCepNaEmpresa->confere);
        $this->assertFalse($semCepNoImovel->confere);
    }

    public function test_numero_sem_numero_nao_confere_por_logradouro(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA A', numero: 'S/N', cep: null),
            $this->imovel('RUA A', 'S/N', null),
        );

        $this->assertFalse($resultado->confere);
        $this->assertSame(ConferenciaTitularidade::M_DIVERGE, $resultado->motivo);
    }

    public function test_numeros_diferentes_no_mesmo_logradouro_nao_conferem(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA A', numero: '12', cep: null),
            $this->imovel('RUA A', '10', null),
        );

        $this->assertFalse($resultado->confere);
    }

    public function test_imovel_sem_cep_utilizavel_nem_logradouro_e_numero_nao_casa_com_ninguem(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: '', numero: '', cep: ''),
            $this->imovel('', '', ''),
        );
        $soBairro = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA A', numero: '1', cep: '41200000'),
            new EnderecoDoImovel(null, null, 'Pituba', null),
        );

        $this->assertFalse($resultado->confere);
        $this->assertFalse($soBairro->confere);
    }

    #[DataProvider('cepsInutilizaveis')]
    public function test_cep_que_nao_tem_oito_digitos_ou_e_so_zeros_conta_como_ausente(?string $cep): void
    {
        $imovelComCepRuim = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA NOVA', numero: '9', cep: $cep),
            $this->imovel('RUA VELHA', '1', $cep),
        );
        $soNaEmpresa = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA NOVA', numero: '9', cep: $cep),
            $this->imovel('RUA VELHA', '1', '41200-000'),
        );
        $soNoImovel = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA NOVA', numero: '9', cep: '41200000'),
            $this->imovel('RUA VELHA', '1', $cep),
        );

        $this->assertFalse($imovelComCepRuim->confere);
        $this->assertFalse($soNaEmpresa->confere);
        $this->assertFalse($soNoImovel->confere);
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function cepsInutilizaveis(): array
    {
        return [
            'zeros' => ['00000000'],
            'zeros com máscara' => ['00000-000'],
            'curto' => ['4120000'],
            'longo' => ['412000001'],
            'vazio' => [''],
            'nulo' => [null],
        ];
    }

    #[DataProvider('numerosInutilizaveis')]
    public function test_numero_sem_digito_diferente_de_zero_conta_como_ausente(string $numero): void
    {
        $nosDoisLados = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA A', numero: $numero, cep: null),
            $this->imovel('RUA A', $numero, null),
        );
        $soNaEmpresa = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA A', numero: $numero, cep: null),
            $this->imovel('RUA A', '10', null),
        );
        $soNoImovel = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA A', numero: '10', cep: null),
            $this->imovel('RUA A', $numero, null),
        );

        $this->assertFalse($nosDoisLados->confere);
        $this->assertFalse($soNaEmpresa->confere);
        $this->assertFalse($soNoImovel->confere);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function numerosInutilizaveis(): array
    {
        return [
            's/n' => ['S/N'],
            'zero' => ['0'],
            'zeros' => ['00'],
            'vazio' => [''],
            'espacos' => ['  '],
        ];
    }

    public function test_numero_e_comparado_so_pelos_digitos(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'RUA A', numero: '508 A', cep: null),
            $this->imovel('RUA A', '508', null),
        );

        $this->assertTrue($resultado->confere);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function paresReaisDaConsultaSedur(): array
    {
        return [
            'thomaz gonzaga' => ['Rua Thomaz Gonzaga, 103, PERNAMBUÉS, 41100000', 'Rua Thomaz Gonzaga', '103', '41100-000'],
            'beira mar' => ['Avenida Beira Mar, 317, RIBEIRA, 40420340', 'Avenida Beira Mar', '317', '40420-340'],
            'desembargador' => ['Rua Desembargador Manoel de Andrade Teixeira, 2, STELLA MARIS, 41601115', 'Rua Desembargador Manoel de Andrade Teixeira', '2', '41601-115'],
            'artur gonzales' => ['Rua Doutor Artur Gonzales, 158, PAU DA LIMA, 41235005', 'Rua Doutor Artur Gonzales', '158', '41235-005'],
        ];
    }

    #[DataProvider('paresReaisDaConsultaSedur')]
    public function test_endereco_composto_da_consulta_sedur_confere_com_o_imovel(string $composto, string $logradouro, string $numero, string $cep): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur($composto),
            $this->imovel($logradouro, $numero, $cep),
        );

        $this->assertTrue($resultado->confere);
    }

    public function test_endereco_composto_de_outro_imovel_nao_confere(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur('Rua Professor Lourival Pimenta Bastos, 426, COSTA AZUL, 41760010'),
            $this->imovel('Avenida Jorge Amado', '76', '41720-040'),
        );

        $this->assertFalse($resultado->confere);
        $this->assertSame(ConferenciaTitularidade::M_DIVERGE, $resultado->motivo);
    }

    public function test_composto_com_municipio_e_uf_antes_do_cep_extrai_o_cep(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur('Rua X, 10, PITUBA, SALVADOR, BA, 41830000'),
            $this->imovel('Rua Y', '99', '41830-000'),
        );

        $this->assertTrue($resultado->confere);
    }

    public function test_composto_sem_cep_confere_por_logradouro_e_numero(): void
    {
        $igual = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur('Rua X, 10, PITUBA'),
            $this->imovel('Rua X', '10', null),
        );
        $numeroDiferente = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur('Rua X, 10, PITUBA'),
            $this->imovel('Rua X', '12', null),
        );

        $this->assertTrue($igual->confere);
        $this->assertFalse($numeroDiferente->confere);
    }

    public function test_composto_com_segundo_segmento_nao_numerico_deixa_o_numero_ausente(): void
    {
        $porLogradouro = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur('Rua X, S/N, PITUBA'),
            $this->imovel('Rua X', 'S/N', null),
        );
        $porCep = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur('Rua X, S/N, PITUBA, 41830000'),
            $this->imovel('Rua Z', '5', '41830-000'),
        );

        $this->assertFalse($porLogradouro->confere);
        $this->assertTrue($porCep->confere);
    }

    #[DataProvider('compostosComCepInutilizavel')]
    public function test_composto_com_cep_inutilizavel_conta_como_ausente(string $composto): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur($composto),
            $this->imovel('Rua Y', '99', '41830-000'),
        );

        $this->assertFalse($resultado->confere);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function compostosComCepInutilizavel(): array
    {
        return [
            'zeros' => ['Rua X, 10, PITUBA, 00000000'],
            'sete digitos' => ['Rua X, 10, PITUBA, 4183000'],
        ];
    }

    public function test_composto_com_cep_de_sete_digitos_nao_casa_com_cep_do_imovel(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur('Rua X, 10, PITUBA, 4183000'),
            $this->imovel('Rua Y', '99', '41830-000'),
        );

        $this->assertFalse($resultado->confere);
    }

    public function test_composto_com_dois_trechos_parecidos_com_cep_vale_o_ultimo(): void
    {
        $confere = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur('Rua X, Sala 12345-678, PITUBA, 41830000'),
            $this->imovel('Rua Y', '99', '41830-000'),
        );
        $espelho = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur('Rua X, Sala 12345-678, PITUBA, 41830000'),
            $this->imovel('Rua Y', '99', '12345-678'),
        );

        $this->assertTrue($confere->confere);
        $this->assertFalse($espelho->confere);
    }

    public function test_composto_com_segundo_segmento_de_texto_deixa_o_numero_ausente(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur('Rua Dr., João, 12, PITUBA'),
            $this->imovel('Rua Dr', '12', null),
        );

        $this->assertFalse($resultado->confere);
    }

    public function test_composto_cujo_primeiro_segmento_e_so_numero_tem_logradouro_ausente(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresaSedur('1234, 10, PITUBA'),
            $this->imovel('1234', '10', null),
        );

        $this->assertFalse($resultado->confere);
    }

    public function test_formato_estruturado_da_brasilapi_continua_funcionando(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO),
            $this->empresa(razao: self::RAZAO, rua: 'Rua X', numero: '10', cep: '41830-000'),
            $this->imovel('Rua X', '10', null),
        );

        $this->assertTrue($resultado->confere);
    }

    #[DataProvider('razoes')]
    public function test_normalizar_razao(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, ConferenciaTitularidade::normalizarRazao($entrada));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function razoes(): array
    {
        return [
            'caixa e acento' => ['Comércio São João', 'comercio sao joao'],
            'sufixo ltda com ponto' => ['Bms Alimentos Ltda.', 'bms alimentos'],
            'sufixo me' => ['PADARIA ESTRELA ME', 'padaria estrela'],
            'sufixo epp' => ['Loja Azul EPP', 'loja azul'],
            'sufixo eireli' => ['Loja Azul Eireli', 'loja azul'],
            'sufixo sa colado' => ['Banco Forte S/A', 'banco forte'],
            'sufixo s.a.' => ['Banco Forte S.A.', 'banco forte'],
            'sufixo s a' => ['Banco Forte S A', 'banco forte'],
            'sufixo sa' => ['Banco Forte SA', 'banco forte'],
            'sufixo mei' => ['Joao da Silva MEI', 'joao da silva'],
            'sufixos encadeados' => ['Loja Azul ME LTDA', 'loja azul'],
            'sufixo no meio permanece' => ['Me Ajuda Servicos Ltda', 'me ajuda servicos'],
            'pontuacao e espacos' => ['  A&B -  Comércio,  Ltda  ', 'a b comercio'],
            'vazio' => ['', ''],
        ];
    }

    private function conferir(TvlLegadaDetalhe $tvl, CnpjData $empresa, EnderecoDoImovel $imovel): ConferenciaResultado
    {
        return (new ConferenciaTitularidade)->conferir($tvl, $empresa, fn (): EnderecoDoImovel => $imovel);
    }

    private function imovel(?string $logradouro = null, ?string $numero = null, ?string $cep = null): EnderecoDoImovel
    {
        return new EnderecoDoImovel($logradouro, $numero, null, $cep);
    }

    private function tvl(
        ?string $razao,
        ?string $logradouro = null,
        ?string $numero = null,
        ?string $cep = null,
    ): TvlLegadaDetalhe {
        return new TvlLegadaDetalhe(
            numero: 1,
            tipo: 'provisoria',
            deferida: true,
            ativa: true,
            emissao: null,
            validade: null,
            situacao: null,
            diasParaVencer: null,
            renovadaDepois: false,
            ultimaRenovacaoEm: null,
            titularDocumento: null,
            titularTipo: null,
            titularOrigem: null,
            razaoSocial: $razao,
            inscricaoImobiliaria: null,
            endereco: ['logradouro' => $logradouro, 'numero' => $numero, 'bairro' => null, 'cep' => $cep],
            processoNumero: null,
            complemento: null,
            zona: null,
            via: null,
            flags: ['sede' => false, 'mei' => false, 'escritorio_virtual' => false, 'culto_religioso' => false, 'edificio_comercial' => false],
            condicionanteAdicional: null,
            atividades: [],
            renovacoes: [],
        );
    }

    private function empresa(string $razao, ?string $rua = null, ?string $numero = null, ?string $cep = null): CnpjData
    {
        return new CnpjData(
            cnpj: '12345678000190',
            legalName: $razao,
            tradeName: null,
            legalNatureCode: null,
            legalNature: null,
            sizeCode: null,
            size: null,
            primaryCnaeCode: null,
            secondaryCnaeCodes: [],
            street: $rua,
            number: $numero,
            complement: null,
            neighborhood: null,
            city: null,
            state: null,
            zipCode: $cep,
            phone: null,
            email: null,
            registrationStatus: null,
        );
    }

    private function empresaSedur(string $enderecoComposto): CnpjData
    {
        return $this->empresa(razao: self::RAZAO, rua: $enderecoComposto);
    }
}
