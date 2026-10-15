<?php

namespace Tests\Feature\Tratamento;

use App\Enums\RuleDomain;
use App\Models\DecisionText;
use App\Models\RuleVersion;
use App\Models\TratamentoPergunta;
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use App\Services\Tratamento\TratamentoRamoInput;
use App\Services\Tratamento\TratamentoRamoResolver;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Os motivos pelos quais o ramo da planilha não resolve saem do catálogo de
 * textos decisórios: o gestor edita a frase e o processo registra o motivo
 * que o usuário lê.
 */
class TratamentoMotivosCatalogoTest extends TestCase
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

    public function test_textos_de_fabrica_dos_motivos_do_ramo(): void
    {
        $defaults = DecisionTextCatalog::defaults();

        $this->assertSame('Planilha de tratamento sem versão vigente', $defaults['tratamento.motivo.sem_versao']);
        $this->assertSame('CNAE sem regra de tratamento vigente', $defaults['tratamento.motivo.cnae_sem_regra']);
        $this->assertSame('Pergunta :numero sem resposta — :texto', $defaults['tratamento.motivo.pergunta_sem_resposta']);
        $this->assertSame('CNAE sem enquadramento no ramo escolhido', $defaults['tratamento.motivo.sem_linha']);
        $this->assertSame('A regra da planilha não permite o prosseguimento deste ramo', $defaults['tratamento.motivo.ramo_bloqueado']);
        $this->assertSame('A SER DEFINIDO PELA CNLU', $defaults['tratamento.motivo.cnlu']);
        $this->assertSame('Área utilizada ausente para o ramo que depende de faixa', $defaults['tratamento.motivo.area_ausente']);
        $this->assertSame('Tipo de imóvel ausente para o ramo que depende do tipo', $defaults['tratamento.motivo.tipo_ausente']);
        $this->assertSame('Tipo de imóvel desconhecido para o ramo que depende do tipo', $defaults['tratamento.motivo.tipo_desconhecido']);
    }

    public function test_pergunta_sem_resposta_cita_o_enunciado_da_planilha(): void
    {
        $enunciado = strtok(TratamentoPergunta::query()->where('numero', 11)->firstOrFail()->texto, "\n");

        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0111-3/01',
            respostas: [],
            areaUtilizada: 800.0,
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertSame("Pergunta 11 sem resposta — {$enunciado}", $ramo->motivo);
    }

    public function test_pergunta_1_sem_subdivisao_carregada_aponta_a_planilha_e_nao_o_requerente(): void
    {
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '3314-7/10',
            respostas: [],
            areaUtilizada: 100.0,
        ));

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertSame(
            'Subdivisão do CNAE 3314-7/10 ausente na planilha de tratamento carregada — rode o complemento da planilha (tratamento:verificar --corrigir)',
            $ramo->motivo,
        );
        $this->assertNull($ramo->perguntaSemResposta, 'Falha de carga da planilha não pode virar pergunta sem resposta (que indefere).');
    }

    public function test_motivo_editado_no_catalogo_vale_no_resolver(): void
    {
        DecisionText::query()->create([
            'key' => 'tratamento.motivo.cnae_sem_regra',
            'template' => 'Atividade sem regra na planilha vigente da SEDUR',
            'description' => 'Edição administrada',
        ]);

        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(cnae: '0000-0/00'));

        $this->assertSame('Atividade sem regra na planilha vigente da SEDUR', $ramo->motivo);
    }

    public function test_tipo_desconhecido_sai_do_catalogo(): void
    {
        DecisionText::query()->create([
            'key' => 'tratamento.motivo.tipo_desconhecido',
            'template' => 'Tipo de imóvel não reconhecido',
            'description' => 'Edição administrada',
        ]);

        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '0210-1/07',
            respostas: [11 => true],
            areaUtilizada: 400.0,
            tipoImovel: TipoImovel::fromRegin('PALAFITA', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('Tipo de imóvel não reconhecido', $ramo->motivo);
    }

    public function test_resultado_resolvido_aponta_onde_na_planilha_a_decisao_esta(): void
    {
        $ramo = $this->resolver()->resolver(new TratamentoRamoInput(
            cnae: '9609-2/99',
            respostas: [1 => [1, 2, 3], 11 => false],
            areaUtilizada: 30400.0,
        ));

        $this->assertSame('resolvido', $ramo->status);
        $this->assertSame('planilha-20-08-26', $ramo->planilha['versao']);
        $this->assertSame('9609-2/99', $ramo->planilha['cnae']);
        $this->assertSame(40, $ramo->planilha['regra']);
        $this->assertSame('Atualizada em 21/08/2026', $ramo->planilha['regra_referencia']);
        $this->assertSame('acima_1250', $ramo->planilha['faixa']);
        $this->assertSame('MÉDIO RISCO', $ramo->planilha['risco_planilha']);
        $this->assertSame('VALIDAÇÃO', $ramo->planilha['classificacao']);
        $this->assertContains($ramo->planilha['origem_fluxo'], ['ramo', 'classificacao', 'heuristica']);
        $this->assertNotSame('', $ramo->planilha['decisao_fluxo']);
    }

    private function resolver(): TratamentoRamoResolver
    {
        return app(TratamentoRamoResolver::class);
    }
}
