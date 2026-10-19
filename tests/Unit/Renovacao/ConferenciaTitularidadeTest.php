<?php

namespace Tests\Unit\Renovacao;

use App\Services\Cnpj\CnpjData;
use App\Services\Legado\TvlLegadaDetalhe;
use App\Services\Renovacao\ConferenciaResultado;
use App\Services\Renovacao\ConferenciaTitularidade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConferenciaTitularidadeTest extends TestCase
{
    private const RAZAO = 'BMS COMERCIO VAREJISTA DE ALIMENTOS LTDA';

    public function test_razao_igual_e_inscricao_igual_confere(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO, logradouro: 'RUA DA ENGOMADEIRA', numero: '508', cep: '41200000'),
            $this->empresa(razao: self::RAZAO, rua: 'RUA DA ENGOMADEIRA', numero: '508', cep: '41200000'),
        );

        $this->assertTrue($resultado->confere);
        $this->assertNull($resultado->motivo);
    }

    public function test_razao_igual_com_sufixo_e_pontuacao_diferentes_confere(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO, cep: '41200000'),
            $this->empresa(razao: 'Bms Comércio Varejista de Alimentos Ltda.', cep: '41.200-000'),
        );

        $this->assertTrue($resultado->confere);
    }

    public function test_razao_com_sufixo_apenas_de_um_lado_confere(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: 'PADARIA ESTRELA ME', cep: '41200000'),
            $this->empresa(razao: 'Padaria Estrela', cep: '41200000'),
        );

        $this->assertTrue($resultado->confere);
    }

    public function test_razao_diferente_nao_confere(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO, logradouro: 'RUA A', numero: '1', cep: '41200000'),
            $this->empresa(razao: 'OUTRA EMPRESA LTDA', rua: 'RUA A', numero: '1', cep: '41200000'),
        );

        $this->assertFalse($resultado->confere);
        $this->assertSame('A razão social deste CNPJ não confere com a da viabilidade anterior.', $resultado->motivo);
    }

    public function test_razao_contida_na_outra_nao_confere(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: 'BMS COMERCIO LTDA', cep: '41200000'),
            $this->empresa(razao: 'BMS COMERCIO VAREJISTA LTDA', cep: '41200000'),
        );

        $this->assertFalse($resultado->confere);
    }

    public function test_razao_igual_mas_endereco_e_inscricao_diferentes_nao_confere(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO, logradouro: 'RUA DA ENGOMADEIRA', numero: '508', cep: '41200000'),
            $this->empresa(razao: self::RAZAO, rua: 'AV SETE DE SETEMBRO', numero: '10', cep: '40060001'),
        );

        $this->assertFalse($resultado->confere);
        $this->assertSame('O endereço deste CNPJ não confere com o da viabilidade anterior.', $resultado->motivo);
    }

    public function test_endereco_confere_por_logradouro_e_numero_quando_nao_ha_inscricao(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO, logradouro: 'RUA DA ENGOMADEIRA', numero: '508', cep: null),
            $this->empresa(razao: self::RAZAO, rua: 'R DA ENGOMADEIRA', numero: '508', cep: '41200000'),
        );

        $this->assertTrue($resultado->confere);
    }

    public function test_avenida_e_av_sao_equivalentes_e_espacos_duplos_sao_tolerados(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO, logradouro: 'AVENIDA  PARALELA', numero: '100'),
            $this->empresa(razao: self::RAZAO, rua: 'AV. Paralela', numero: '100', cep: null),
        );

        $this->assertTrue($resultado->confere);
    }

    public function test_cep_igual_confere_mesmo_com_logradouro_diferente(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO, logradouro: '1234', numero: null, cep: '41200-000'),
            $this->empresa(razao: self::RAZAO, rua: 'RUA NOVA', numero: '9', cep: '41200000'),
        );

        $this->assertTrue($resultado->confere);
    }

    public function test_numero_sem_numero_nao_confere_por_logradouro(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO, logradouro: 'RUA A', numero: 'S/N', cep: null),
            $this->empresa(razao: self::RAZAO, rua: 'RUA A', numero: 'S/N', cep: null),
        );

        $this->assertFalse($resultado->confere);
        $this->assertSame('O endereço deste CNPJ não confere com o da viabilidade anterior.', $resultado->motivo);
    }

    public function test_numeros_diferentes_no_mesmo_logradouro_nao_conferem(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO, logradouro: 'RUA A', numero: '10', cep: null),
            $this->empresa(razao: self::RAZAO, rua: 'RUA A', numero: '12', cep: null),
        );

        $this->assertFalse($resultado->confere);
    }

    public function test_enderecos_vazios_nao_conferem(): void
    {
        $resultado = $this->conferir(
            $this->tvl(razao: self::RAZAO, logradouro: '', numero: '', cep: ''),
            $this->empresa(razao: self::RAZAO, rua: '', numero: '', cep: ''),
        );

        $this->assertFalse($resultado->confere);
    }

    public function test_tvl_sem_razao_social_nao_confere(): void
    {
        foreach ([null, '', '  '] as $razao) {
            $resultado = $this->conferir(
                $this->tvl(razao: $razao, cep: '41200000'),
                $this->empresa(razao: self::RAZAO, cep: '41200000'),
            );

            $this->assertFalse($resultado->confere);
            $this->assertSame(
                'A viabilidade anterior não tem razão social registrada para conferir a titularidade.',
                $resultado->motivo,
            );
        }
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

    private function conferir(TvlLegadaDetalhe $tvl, CnpjData $empresa): ConferenciaResultado
    {
        return (new ConferenciaTitularidade)->conferir($tvl, $empresa);
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
}
