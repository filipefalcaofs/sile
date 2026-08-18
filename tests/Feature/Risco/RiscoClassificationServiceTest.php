<?php

namespace Tests\Feature\Risco;

use App\Enums\RiscoMunicipal;
use App\Enums\RiscoSanitario;
use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Enums\TipoGatilho;
use App\Models\Activity;
use App\Models\Parameter;
use App\Models\RiskClassification;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Models\SanitaryRiskClassification;
use App\Services\Risco\RiscoClassificationService;
use App\Services\Risco\RiscoInput;
use App\Support\Audit\AuditService;
use Carbon\Carbon;
use Database\Seeders\RiskTriggerSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Motor de classificação de risco (HU-047 a HU-051), espelhando o
 * TerritoryService: dado um CNAE, classifica as dimensões municipal (Decreto
 * 32.636/2020) e sanitária (VISA) como dimensões SEPARADAS, aplica a
 * reclassificação por condicionante-pergunta, resolve o encaminhamento pelo
 * mapa parametrizado (HU-014), aplica os gatilhos semi-expresso e audita a
 * decisão com a versão das regras aplicadas (RN-002). Sem fachada: CNAE sem
 * regra vigente nunca é decidido automaticamente — segue para análise.
 */
class RiscoClassificationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): RiscoClassificationService
    {
        return new RiscoClassificationService(app(AuditService::class));
    }

    private function versaoMunicipal(string $version = 'decreto-32636-2020'): RuleVersion
    {
        return RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => $version,
            'rules_version' => $version,
        ]);
    }

    private function versaoSanitaria(string $version = 'visa-unificada-2026-04-30'): RuleVersion
    {
        return RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoSanitario,
            'version' => $version,
            'rules_version' => $version,
        ]);
    }

    public function test_classifica_municipal_e_sanitario_como_dimensoes_separadas(): void
    {
        $municipal = $this->versaoMunicipal();
        $sanitaria = $this->versaoSanitaria();

        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '1234567',
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);
        SanitaryRiskClassification::factory()->create([
            'rule_version_id' => $sanitaria->id,
            'cnae_code' => '1234567',
            'risco_sanitario' => RiscoSanitario::Alto,
        ]);

        $result = $this->service()->classify(RiscoInput::paraCnae('1234567'));

        $this->assertSame('classificado', $result->municipal['status']);
        $this->assertSame('baixo_a', $result->municipal['nivel']);
        $this->assertSame('Baixo', $result->municipal['nivel_label']);

        $this->assertSame('classificado', $result->sanitario['status']);
        $this->assertSame('alto', $result->sanitario['nivel_final']);

        // Dimensões SEPARADAS: cada uma carrega a versão do seu próprio domínio.
        $this->assertSame('decreto-32636-2020', $result->versoes()['municipal']);
        $this->assertSame('visa-unificada-2026-04-30', $result->versoes()['sanitario']);
        $this->assertNotSame($result->versoes()['municipal'], $result->versoes()['sanitario']);
    }

    public function test_baixo_risco_municipal_encaminha_para_expresso(): void
    {
        $municipal = $this->versaoMunicipal();
        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '2222222',
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $result = $this->service()->classify(RiscoInput::paraCnae('2222222'));

        $this->assertSame('expresso', $result->encaminhamento['fluxo']);
        $this->assertSame('municipal', $result->encaminhamento['dimensao_decisiva']);
        $this->assertFalse($result->encaminhadoParaAnalise());
    }

    public function test_baixo_b_tambem_encaminha_para_expresso_por_default(): void
    {
        $municipal = $this->versaoMunicipal();
        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '2222333',
            'risco_municipal' => RiscoMunicipal::BaixoB,
        ]);

        $result = $this->service()->classify(RiscoInput::paraCnae('2222333'));

        $this->assertSame('Médio', $result->municipal['nivel_label']);
        $this->assertSame('expresso', $result->encaminhamento['fluxo']);
    }

    public function test_alto_risco_encaminha_para_analise(): void
    {
        $municipal = $this->versaoMunicipal();
        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '3333333',
            'risco_municipal' => RiscoMunicipal::Alto,
        ]);

        $result = $this->service()->classify(RiscoInput::paraCnae('3333333'));

        $this->assertSame('analise', $result->encaminhamento['fluxo']);
        $this->assertTrue($result->encaminhadoParaAnalise());
    }

    public function test_condicionante_pergunta_reclassifica_sanitario(): void
    {
        $sanitaria = $this->versaoSanitaria();
        SanitaryRiskClassification::factory()->create([
            'rule_version_id' => $sanitaria->id,
            'cnae_code' => '1031700',
            'risco_sanitario' => RiscoSanitario::Baixo,
        ]);
        $condicionante = RiskCondicionante::factory()->create([
            'rule_version_id' => $sanitaria->id,
            'cnae_code' => '1031700',
            'pergunta' => 'O resultado do exercício da atividade econômica será diferente de produto artesanal?',
            'regra_reclassificacao' => [
                'resposta_gatilho' => true,
                'reclassifica_para' => 'alto',
                'fundamento' => 'Caso o produto seja diferente de artesanal, será considerado Alto Risco.',
            ],
        ]);

        // Resposta "Sim" (produto não artesanal) reclassifica de Baixo para Alto.
        $reclassificado = $this->service()->classify(
            new RiscoInput('1031700', [$condicionante->id => true]),
        );

        $this->assertSame('baixo', $reclassificado->sanitario['nivel_original']);
        $this->assertSame('alto', $reclassificado->sanitario['nivel_final']);
        $this->assertTrue($reclassificado->sanitario['reclassificado']);

        // Resposta "Não": permanece no nível original (a regra é real, não fixa).
        $mantido = $this->service()->classify(
            new RiscoInput('1031700', [$condicionante->id => false]),
        );

        $this->assertSame('baixo', $mantido->sanitario['nivel_final']);
        $this->assertFalse($mantido->sanitario['reclassificado']);
    }

    public function test_gatilho_ativo_derruba_baixo_risco_para_analise(): void
    {
        $this->seed(RiskTriggerSeeder::class);

        $municipal = $this->versaoMunicipal();
        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '4444444',
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        // Sem o gatilho, baixo_a iria para expresso; o gatilho derruba para análise.
        $result = $this->service()->classify(
            new RiscoInput('4444444', [], [TipoGatilho::EnquadramentoAusente->value]),
        );

        $this->assertSame('analise', $result->encaminhamento['fluxo']);

        $codigos = array_column($result->encaminhamento['gatilhos_acionados'], 'codigo');
        $this->assertContains('enquadramento_ausente', $codigos);
        $this->assertNotEmpty($result->encaminhamento['gatilhos_acionados'][0]['motivo']);
    }

    public function test_excecao_zeis_especial_encaminha_para_analise(): void
    {
        $this->seed(RiskTriggerSeeder::class);

        $municipal = $this->versaoMunicipal();
        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '5555555',
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $result = $this->service()->classify(
            new RiscoInput('5555555', [], [TipoGatilho::ZeisEspecial->value]),
        );

        $this->assertSame('analise', $result->encaminhamento['fluxo']);
        $codigos = array_column($result->encaminhamento['gatilhos_acionados'], 'codigo');
        $this->assertContains('zeis_especial', $codigos);
    }

    public function test_mapa_de_encaminhamento_parametrizavel_sem_deploy(): void
    {
        $municipal = $this->versaoMunicipal();
        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '6666666',
            'risco_municipal' => RiscoMunicipal::Alto,
        ]);

        // Com o mapa default, alto vai para análise.
        $antes = $this->service()->classify(RiscoInput::paraCnae('6666666'));
        $this->assertSame('analise', $antes->encaminhamento['fluxo']);

        // O admin grava um mapa que envia alto ao expresso (HU-014): a gravação
        // do Parameter invalida o cache da chave (Parameter::saved).
        Parameter::query()->create([
            'key' => 'risco.mapa_encaminhamento',
            'group' => 'risco',
            'type' => 'json',
            'value' => '{"alto":"expresso"}',
            'default_value' => '{"baixo_a":"expresso","baixo_b":"expresso","alto":"analise"}',
            'validation_rules' => ['required', 'json'],
            'description' => 'Mapa de encaminhamento por nível de risco.',
        ]);

        // Mesma entrada, novo resultado — troca de regra sem deploy.
        $depois = $this->service()->classify(RiscoInput::paraCnae('6666666'));
        $this->assertSame('expresso', $depois->encaminhamento['fluxo']);
    }

    public function test_cnae_sem_classificacao_segue_para_analise(): void
    {
        // Versão vigente existe, mas o CNAE não está classificado nela (FA-02).
        $this->versaoMunicipal();

        $result = $this->service()->classify(RiscoInput::paraCnae('9999999'));

        $this->assertSame('nao_classificado', $result->municipal['status']);
        $this->assertNull($result->municipal['nivel']);
        $this->assertSame('analise', $result->encaminhamento['fluxo']);
        $this->assertStringContainsString(
            'não parametrizada',
            (string) $result->encaminhamento['motivo'],
        );
    }

    public function test_classificacao_e_auditada_com_versao_de_regras(): void
    {
        $municipal = $this->versaoMunicipal();
        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '7777777',
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $this->service()->classify(RiscoInput::paraCnae('7777777'));

        $activity = Activity::query()
            ->where('log_name', 'risco')
            ->where('event', 'classificacao')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame('decreto-32636-2020', $activity->rules_version);
        $this->assertSame('7777777', $activity->properties['cnae']);
        $this->assertSame('expresso', $activity->properties['fluxo']);
        $this->assertSame('municipal', $activity->properties['dimensao_decisiva']);
    }

    public function test_reproducao_por_data_usa_a_versao_da_epoca(): void
    {
        // Versão antiga (substituída) classificava o CNAE como ALTO.
        $antiga = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-antigo',
            'rules_version' => 'decreto-antigo',
            'status' => RuleVersionStatus::Substituida,
            'valid_from' => '2020-01-01',
            'valid_to' => '2024-01-01',
        ]);
        RiskClassification::factory()->create([
            'rule_version_id' => $antiga->id,
            'cnae_code' => '8888888',
            'risco_municipal' => RiscoMunicipal::Alto,
        ]);

        // Versão vigente reclassificou o mesmo CNAE como BAIXO A.
        $nova = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-32636-2020',
            'rules_version' => 'decreto-32636-2020',
            'status' => RuleVersionStatus::Vigente,
            'valid_from' => '2024-01-01',
            'valid_to' => null,
        ]);
        RiskClassification::factory()->create([
            'rule_version_id' => $nova->id,
            'cnae_code' => '8888888',
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        // Reprodução por época: classificar com data antiga usa a versão antiga.
        $epoca = $this->service()->classify(
            new RiscoInput('8888888', data: Carbon::parse('2022-06-01')),
        );
        $this->assertSame('alto', $epoca->municipal['nivel']);
        $this->assertSame('decreto-antigo', $epoca->versoes()['municipal']);

        // Sem data, usa a versão vigente (nível atual).
        $vigente = $this->service()->classify(RiscoInput::paraCnae('8888888'));
        $this->assertSame('baixo_a', $vigente->municipal['nivel']);
        $this->assertSame('decreto-32636-2020', $vigente->versoes()['municipal']);
    }
}
