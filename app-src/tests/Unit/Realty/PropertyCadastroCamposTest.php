<?php

namespace Tests\Unit\Realty;

use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyRegistryResult;
use PHPUnit\Framework\TestCase;

class PropertyCadastroCamposTest extends TestCase
{
    public function test_vazios_produz_todos_os_campos_null(): void
    {
        $campos = PropertyCadastroCampos::vazios()->toArray();

        $this->assertNull($campos['inscricao']);
        $this->assertNull($campos['endereco']);
        $this->assertNull($campos['numero_metrico']);
        $this->assertNull($campos['loteamento']);
        $this->assertNull($campos['quadra']);
        $this->assertNull($campos['lote']);
        $this->assertNull($campos['conjunto_edificio']);
        $this->assertNull($campos['bloco']);
        $this->assertNull($campos['sub_unidade']);
        $this->assertNull($campos['numero_sub_unidade']);
        $this->assertNull($campos['bairro']);
        $this->assertNull($campos['cep']);
        $this->assertNull($campos['area_construida_m2']);
        $this->assertNull($campos['tipo_imovel']);
        $this->assertNull($campos['data_lancamento']);
        $this->assertNull($campos['situacao_cadastral']);
        $this->assertNull($campos['contribuinte']);
        $this->assertNull($campos['cpf_cnpj']);
        $this->assertNull($campos['numero_porta']);
        $this->assertNull($campos['area_terreno_m2']);
        $this->assertNull($campos['valor_venal_iptu']);
        $this->assertNull($campos['logradouro_tributario']);
        $this->assertNull($campos['situacao_fiscal']);
        $this->assertNull($campos['data_emissao_certidao']);
    }

    public function test_property_registry_result_inclui_cadastro_no_to_array(): void
    {
        $cadastro = new PropertyCadastroCampos(
            inscricao: '379387-7',
            endereco: 'Rua Martiniano Bonfim',
            numero_metrico: '224',
            loteamento: null,
            quadra: '0181',
            lote: '0043',
            conjunto_edificio: null,
            bloco: null,
            sub_unidade: 'GL - Galpão',
            numero_sub_unidade: null,
            bairro: 'CABULA',
            cep: null,
            area_construida_m2: '0,00',
            tipo_imovel: 'Residencial Horizontal',
            data_lancamento: '01/01/1986',
            situacao_cadastral: 'Ativo',
            contribuinte: 'ANTONIO COSTA NETO',
            cpf_cnpj: '035.385.595-20',
            numero_porta: '000224',
            area_terreno_m2: '704,00',
            valor_venal_iptu: 'R$ 769.120,00',
            logradouro_tributario: '3704 - Rua Martiniano Bonfim',
            situacao_fiscal: 'Contribuinte',
            data_emissao_certidao: '01/12/2023 10:22:28',
        );

        $result = new PropertyRegistryResult(
            latitude: -12.97,
            longitude: -38.50,
            inscricao: '379387-7',
            source: 'fake',
            raw: [],
            cadastro: $cadastro,
        );

        $this->assertSame('379387-7', $result->toArray()['cadastro']['inscricao']);
        $this->assertSame('0181', $result->toArray()['cadastro']['quadra']);
    }
}
