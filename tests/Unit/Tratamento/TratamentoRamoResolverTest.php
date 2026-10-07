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

    public function test_regra_25_industrial_e_alto_e_segue_para_critica(): void
    {
        // Planilha regras 24/25 (SEDUR, 23/09): resposta NÃO é produção em
        // série — família ID, alto risco. O texto manda crítica do analista
        // (semiexpresso), nunca o médio da linha de enquadramento.
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '1063-5/00',
            respostas: [2 => true, 3 => false],
            areaUtilizada: 80.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('09.02.18', $ramo->codigoLouos);
        $this->assertSame('alto', $ramo->risco);
        $this->assertSame('semiexpresso', $ramo->fluxo);
    }

    public function test_regra_25_artesanal_e_medio_expresso(): void
    {
        // Fora da família ID o risco da regra 25 é médio, no fluxo expresso.
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '1063-5/00',
            respostas: [2 => true, 3 => true],
            areaUtilizada: 80.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('07.09.02', $ramo->codigoLouos);
        $this->assertSame('medio', $ramo->risco);
        $this->assertSame('expresso', $ramo->fluxo);
    }

    public function test_regra_3_artesanal_entre_350_e_1250_permanece_baixo(): void
    {
        // Regras 3, 7, 19 e 57: o corte de risco é 1.250 m². A faixa de 350 m²
        // da linha 07.09.17 não antecipa o médio nem troca o subgrupo.
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '1340-5/01',
            respostas: [2 => true, 3 => true],
            areaUtilizada: 400.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('07.09.17', $ramo->codigoLouos);
        $this->assertSame('nR1-09', $ramo->subgrupo);
        $this->assertSame('baixo', $ramo->risco);
        $this->assertSame('expresso', $ramo->fluxo);
    }

    public function test_regra_3_artesanal_acima_de_1250_e_medio_expresso(): void
    {
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '1340-5/01',
            respostas: [2 => true, 3 => true],
            areaUtilizada: 2000.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('07.09.17', $ramo->codigoLouos);
        $this->assertSame('medio', $ramo->risco);
        $this->assertSame('expresso', $ramo->fluxo);
    }

    public function test_regra_7_artesanal_acima_de_1250_e_medio_expresso(): void
    {
        // Linha com faixa "Q": acima de 1.250 m² o risco sobe para médio
        // e o fluxo permanece expresso.
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '1020-1/01',
            respostas: [2 => true, 3 => true],
            areaUtilizada: 2000.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('07.09.02', $ramo->codigoLouos);
        $this->assertSame('medio', $ramo->risco);
        $this->assertSame('expresso', $ramo->fluxo);
    }

    public function test_regra_19_e_57_artesanal_acima_de_1250_e_medio_expresso(): void
    {
        $tipo = TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826());

        $regra19 = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '1622-6/02',
            respostas: [2 => true, 3 => true],
            areaUtilizada: 2000.0,
            tipoImovel: $tipo,
        ));
        $regra57 = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '1066-0/00',
            respostas: [2 => true, 3 => true],
            areaUtilizada: 2000.0,
            tipoImovel: $tipo,
        ));

        $this->assertSame('medio', $regra19->risco);
        $this->assertSame('expresso', $regra19->fluxo);
        $this->assertSame('07.09.08', $regra19->codigoLouos);
        $this->assertSame('medio', $regra57->risco);
        $this->assertSame('expresso', $regra57->fluxo);
        $this->assertSame('07.09.02', $regra57->codigoLouos);
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

    public function test_regra_1_no_local_remete_a_critica_semiexpresso(): void
    {
        // Relatório SEDUR 21/09 (item 20 — 990010): regra 1, P11=SIM →
        // "registrar e remeter para crítica do analista" (semiexpresso) —
        // nunca expresso com deferimento automático. Sem resposta que decida
        // entre as duas linhas "no local" da regra, o sistema não enquadra:
        // nao_resolvido → análise (decisão SEDUR 22/09).
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '4511-1/01',
            respostas: [11 => true],
            areaUtilizada: 80.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
    }

    public function test_regra_51_fora_do_local_e_expresso_mesmo_com_alto_risco(): void
    {
        // Item 25 (990018): regra 51 — P2=NÃO ≤ 1.250 m² → "Fluxo Expresso
        // (ALTO RISCO)": a planilha prevalece, expresso mesmo com risco alto.
        // Cobre também a pendência espúria: a P3 só é exigida quando P2=SIM
        // (regra 52: "Se SIM na pergunta 2, exibir a pergunta 3").
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '1032-5/01',
            respostas: [2 => false],
            areaUtilizada: 80.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('07.12.13', $ramo->codigoLouos);
        $this->assertSame('expresso', $ramo->fluxo);
    }

    public function test_regra_50_no_local_e_expresso_com_a_linha_da_regra(): void
    {
        // Item 18 (990008): regra 50 — P11=SIM ≤ 1.250 m² → expresso com a
        // linha 07.05.03 (clínica), não a primeira do arquivo (07.05.01).
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '8630-5/02',
            respostas: [11 => true],
            areaUtilizada: 80.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('07.05.03', $ramo->codigoLouos);
        $this->assertSame('expresso', $ramo->fluxo);
    }

    public function test_sem_resposta_decisiva_nao_enquadra(): void
    {
        // Item 21 (990011): várias linhas "no local" e nenhuma resposta que
        // decida entre elas → o sistema NÃO enquadra (análise), nunca assume
        // a primeira linha ID do arquivo.
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '1013-9/01',
            respostas: [11 => true],
            areaUtilizada: 80.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
    }

    public function test_regra_27_no_local_e_expresso_com_a_linha_da_regra(): void
    {
        // Regra 27: P19=SIM + P20 (opções 1/2/4) → 07.04.06 expresso (MÉDIO).
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '4789-0/04',
            respostas: [19 => true, 20 => true],
            areaUtilizada: 80.0,
            tipoImovel: TipoImovel::fromRegin('Edificação Comercial', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('07.04.06', $ramo->codigoLouos);
        $this->assertSame('expresso', $ramo->fluxo);
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

    public function test_resposta_em_opcao_vale_como_sim_e_nao(): void
    {
        $nao = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [11 => false],
            areaUtilizada: 800.0,
        ));
        $naoComoOpcao = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [11 => 2],
            areaUtilizada: 800.0,
        ));
        $simComoOpcao = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [11 => [1]],
            areaUtilizada: 800.0,
        ));
        $sim = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [11 => true],
            areaUtilizada: 800.0,
        ));

        $this->assertSame($nao->codigoLouos, $naoComoOpcao->codigoLouos);
        $this->assertSame($nao->fluxo, $naoComoOpcao->fluxo);
        $this->assertSame($sim->codigoLouos, $simComoOpcao->codigoLouos);
        $this->assertNotSame($nao->codigoLouos, $sim->codigoLouos);
    }

    private function resolver(): TratamentoRamoResolver
    {
        return new TratamentoRamoResolver;
    }
}
