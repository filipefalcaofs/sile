<?php

namespace Tests\Feature\Viabilidade;

use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use App\Services\Tratamento\TratamentoRamoInput;
use App\Services\Tratamento\TratamentoRamoResolver;
use App\Services\Tratamento\TratamentoRamosSedur;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * O motor de tratamento informa, de forma estruturada, qual dado de entrada
 * impediu o enquadramento do ramo — a consulta prévia usa isso para classificar
 * a resposta como incompleta sem interpretar texto de motivo.
 */
class DadoFaltanteTratamentoTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    protected function setUp(): void
    {
        parent::setUp();

        $versao = $this->seedTratamentoPlanilha();
        (new TratamentoRamosSedur)->carregar($versao, database_path('data/regras-20-08-26/regras-fluxo-sedur.json'));
    }

    public function test_area_ausente_em_ramo_com_faixa_marca_area(): void
    {
        $ramo = (new TratamentoRamoResolver)->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [11 => false],
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertSame('area', $ramo->dadoFaltante);
        $this->assertSame('area', $ramo->toEnquadramentoDimensao()['dado_faltante']);
    }

    public function test_tipo_ausente_em_familia_id_marca_tipo_imovel(): void
    {
        $ramo = (new TratamentoRamoResolver)->resolver(new TratamentoRamoInput(
            cnae: '0210-1/07',
            respostas: [11 => true],
            areaUtilizada: 400.0,
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertSame('tipo_imovel', $ramo->dadoFaltante);
    }

    public function test_tipo_desconhecido_em_familia_id_marca_tipo_imovel(): void
    {
        $ramo = (new TratamentoRamoResolver)->resolver(new TratamentoRamoInput(
            cnae: '0210-1/07',
            respostas: [11 => true],
            areaUtilizada: 400.0,
            tipoImovel: TipoImovel::fromRegin('PALAFITA', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('tipo_imovel', $ramo->dadoFaltante);
    }

    public function test_categoria_ausente_em_regra_que_depende_dela_marca_categoria_empresa(): void
    {
        $ramo = (new TratamentoRamoResolver)->resolver(new TratamentoRamoInput(
            cnae: '8411-6/00',
            respostas: [7 => true],
            areaUtilizada: 120.0,
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertSame('categoria_empresa', $ramo->dadoFaltante);
    }

    public function test_ramo_resolvido_nao_tem_dado_faltante(): void
    {
        $ramo = (new TratamentoRamoResolver)->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [11 => false],
            areaUtilizada: 800.0,
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertNull($ramo->dadoFaltante);
        $this->assertNull($ramo->toEnquadramentoDimensao()['dado_faltante']);
    }

    public function test_pergunta_sem_resposta_nao_e_dado_faltante_estruturado(): void
    {
        $ramo = (new TratamentoRamoResolver)->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [],
            areaUtilizada: 800.0,
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertNull($ramo->dadoFaltante);
        $this->assertSame(11, $ramo->perguntaSemResposta['numero']);
    }
}
