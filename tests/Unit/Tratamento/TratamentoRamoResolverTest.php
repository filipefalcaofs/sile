<?php

namespace Tests\Unit\Tratamento;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use App\Services\Tratamento\TratamentoRamoInput;
use App\Services\Tratamento\TratamentoRamoResolver;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TratamentoRamoResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-20-08-26',
        ]);

        (new TratamentoRegrasImportService)->import($versao, database_path('data/regras-20-08-26'));
    }

    public function test_gc01_p11_nao_area_ate_1250_e_escritorio_baixo_expresso(): void
    {
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [11 => false],
            areaUtilizada: 800.0,
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('07.12.13', $ramo->codigoLouos);
        $this->assertSame('nR1-12', $ramo->subgrupo);
        $this->assertSame('baixo', $ramo->risco);
        $this->assertSame('expresso', $ramo->fluxo);
    }

    public function test_gc02_p11_nao_area_acima_1250_e_escritorio_medio_expresso(): void
    {
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [11 => false],
            areaUtilizada: 1300.0,
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('07.12.13', $ramo->codigoLouos);
        $this->assertSame('nR2-12', $ramo->subgrupo);
        $this->assertSame('medio', $ramo->risco);
        $this->assertSame('expresso', $ramo->fluxo);
    }

    public function test_gc03_p11_sim_galpao_id_e_alto_semiexpresso(): void
    {
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0210-1/07',
            respostas: [11 => true],
            areaUtilizada: 400.0,
            tipoImovel: TipoImovel::fromRegin('GALPÃO', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('ID2-07', $ramo->subgrupo);
        $this->assertSame('alto', $ramo->risco);
        $this->assertSame('semiexpresso', $ramo->fluxo);
    }

    public function test_gc_cnlu_nao_resolve_e_nunca_e_expresso(): void
    {
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [11 => true],
            areaUtilizada: 400.0,
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertSame('analise', $ramo->fluxo);
        $this->assertStringContainsString('CNLU', $ramo->motivo ?? '');
    }

    public function test_1340_no_local_sem_artesanal_enquadra_industrial(): void
    {
        // Planilha de tratamento (regra 3): P3 = "modo artesanal?" — NÃO significa
        // produção em série/industrial: enquadra na família ID (09.11.20), nunca
        // no 07.09.xx artesanal. (Relatório SEDUR 21/09 — gravíssimo: os ramos
        // SIM/NÃO estavam invertidos no resolver.)
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '1340-5/01',
            respostas: [2 => true, 3 => false],
            areaUtilizada: 53.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('09.11.20', $ramo->codigoLouos);
        $this->assertSame('ID3-11', $ramo->subgrupo);
    }

    public function test_1340_no_local_artesanal_enquadra_servico_artesanal(): void
    {
        // P3 = SIM (artesanal): enquadra no 07.09.xx de serviço artesanal
        // (baixo risco), nunca na família ID industrial.
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '1340-5/01',
            respostas: [2 => true, 3 => true],
            areaUtilizada: 53.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('07.09.17', $ramo->codigoLouos);
        $this->assertSame('nR1-09', $ramo->subgrupo);
    }

    public function test_tipo_residencial_eleva_ramo_fora_do_id_para_medio(): void
    {
        // Planilha (regras 1, 5, 24, 51…): galpão/container/edificação residencial
        // fora da família ID eleva o ramo a MÉDIO e remete à crítica do analista.
        // (Relatório SEDUR 21/09, item 01 — o 13336/2026 classificou baixo no
        // escritório com edificação residencial.)
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '2539-0/01',
            respostas: [11 => false],
            areaUtilizada: 8.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Residencial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('07.12.13', $ramo->codigoLouos);
        $this->assertSame('medio', $ramo->risco);
        $this->assertSame('semiexpresso', $ramo->fluxo);
    }

    public function test_pergunta_faltando_nao_resolve(): void
    {
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [],
            areaUtilizada: 800.0,
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertStringContainsString('pergunta', mb_strtolower($ramo->motivo ?? ''));
    }

    public function test_cnae_sem_binding_nao_resolve(): void
    {
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0000-0/00',
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertStringContainsString('sem regra', mb_strtolower($ramo->motivo ?? ''));
    }

    public function test_area_ausente_quando_ramo_depende_nao_resolve(): void
    {
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [11 => false],
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertStringContainsString('área', mb_strtolower($ramo->motivo ?? ''));
    }

    public function test_tipo_ausente_quando_ramo_id_depende_nao_resolve(): void
    {
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0210-1/07',
            respostas: [11 => true],
            areaUtilizada: 400.0,
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertStringContainsString('tipo', mb_strtolower($ramo->motivo ?? ''));
    }

    public function test_tipo_desconhecido_quando_ramo_id_depende_nao_resolve(): void
    {
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0210-1/07',
            respostas: [11 => true],
            areaUtilizada: 400.0,
            tipoImovel: TipoImovel::fromRegin('PALAFITA', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertStringContainsString('desconhecido', mb_strtolower($ramo->motivo ?? ''));
    }

    private function resolver(): TratamentoRamoResolver
    {
        return new TratamentoRamoResolver;
    }
}
